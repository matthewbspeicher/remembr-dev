<?php

namespace App\Services;

use App\Events\MemoryCreated;
use App\Models\Agent;
use App\Models\Memory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MemoryService
{
    public function __construct(
        private readonly EmbeddingService $embeddings,
    ) {}

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    public function store(Agent $agent, array $data): Memory
    {
        // Enforce per-agent quota — only for genuinely new memories (not updates)
        $key = $data['key'] ?? null;
        $isUpdate = $key && Memory::where('agent_id', $agent->id)->where('key', $key)->exists();

        if (! $isUpdate && $agent->memories()->count() >= $agent->max_memories) {
            abort(422, "Memory quota exceeded. This agent is limited to {$agent->max_memories} memories.");
        }

        if (isset($data['ttl'])) {
            $data['expires_at'] = $this->parseTtl($data['ttl']);
        }

        $metadata = $data['metadata'] ?? [];
        if (isset($data['tags'])) {
            $metadata['tags'] = $data['tags'];
        }

        $embedding = $this->embeddings->embed($data['value']);

        $memory = Memory::updateOrCreate(
            [
                'agent_id' => $agent->id,
                'key' => $data['key'] ?? null,
            ],
            [
                'value' => $data['value'],
                'embedding' => '['.implode(',', $embedding).']',
                'metadata' => $metadata,
                'visibility' => $data['visibility'] ?? 'private',
                'workspace_id' => $data['workspace_id'] ?? null,
                'importance' => $data['importance'] ?? 5,
                'confidence' => $data['confidence'] ?? 1.0,
                'expires_at' => $data['expires_at'] ?? null,
            ]
        );

        if ($memory->visibility === 'public') {
            MemoryCreated::dispatch($memory->load('agent'));
        }

        if (isset($data['relations'])) {
            $syncData = [];
            foreach ($data['relations'] as $relation) {
                $syncData[$relation['id']] = ['type' => $relation['type'] ?? 'related'];
            }
            $memory->relatedTo()->sync($syncData);
            $memory->load('relatedTo');
        }

        return $memory;
    }

    public function update(Memory $memory, array $data): Memory
    {
        if (isset($data['value']) && $data['value'] !== $memory->value) {
            $data['embedding'] = '['.implode(',', $this->embeddings->embed($data['value'])).']';
        }

        if (isset($data['ttl'])) {
            $data['expires_at'] = $this->parseTtl($data['ttl']);
            unset($data['ttl']);
        }

        if (isset($data['tags'])) {
            $metadata = $data['metadata'] ?? $memory->metadata ?? [];
            $metadata['tags'] = $data['tags'];
            $data['metadata'] = $metadata;
            unset($data['tags']);
        } elseif (isset($data['metadata'])) {
            if (isset($memory->metadata['tags'])) {
                $data['metadata']['tags'] = $memory->metadata['tags'];
            }
        }

        if (isset($data['relations'])) {
            $syncData = [];
            foreach ($data['relations'] as $relation) {
                $syncData[$relation['id']] = ['type' => $relation['type'] ?? 'related'];
            }
            $memory->relatedTo()->sync($syncData);
            unset($data['relations']);
        }

        $memory->update($data);
        
        $memory->load('relatedTo');

        return $memory->fresh();
    }

    private function parseTtl(string $ttl): \Illuminate\Support\Carbon
    {
        $value = (int) substr($ttl, 0, -1);
        $unit = substr($ttl, -1);

        return match ($unit) {
            'm' => now()->addMinutes($value),
            'h' => now()->addHours($value),
            'd' => now()->addDays($value),
            default => throw new \InvalidArgumentException("Invalid TTL format: {$ttl}"),
        };
    }

    public function delete(Memory $memory): void
    {
        $memory->delete();
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    public function findByKey(Agent $agent, string $key): ?Memory
    {
        return Memory::query()
            ->where('agent_id', $agent->id)
            ->where('key', $key)
            ->notExpired()
            ->with('relatedTo')
            ->first();
    }

    public function listForAgent(Agent $agent, int $perPage = 20, array $tags = []): LengthAwarePaginator
    {
        $query = Memory::query()
            ->accessibleBy($agent)
            ->notExpired()
            ->with('relatedTo')
            ->latest();

        if (! empty($tags)) {
            $query->withTags($tags);
        }

        return $query->paginate($perPage);
    }

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------

    public function searchForAgent(Agent $agent, string $q, int $limit = 10, array $tags = []): Collection
    {
        $embedding = $this->embeddings->embed($q);

        $start = microtime(true);
        
        // 1. Vector Search
        $vectorQuery = Memory::query()
            ->accessibleBy($agent)
            ->notExpired()
            ->with('relatedTo')
            ->semanticSearch($embedding, $limit * 2); // fetch more for RRF

        if (! empty($tags)) {
            $vectorQuery->withTags($tags);
        }
        $vectorResults = $vectorQuery->get();

        // 2. Keyword Search
        $keywordQuery = Memory::query()
            ->accessibleBy($agent)
            ->notExpired()
            ->with('relatedTo')
            ->keywordSearch($q, $limit * 2);

        if (! empty($tags)) {
            $keywordQuery->withTags($tags);
        }
        $keywordResults = $keywordQuery->get();

        // 3. Reciprocal Rank Fusion
        $results = $this->fuseResults($vectorResults, $keywordResults, $limit);

        $duration = (microtime(true) - $start) * 1000;
        \Illuminate\Support\Facades\Log::info("Hybrid search (Agent) completed in {$duration}ms", ['agent_id' => $agent->id, 'limit' => $limit, 'tags' => $tags]);

        return collect($results);
    }

    public function searchCommons(Agent $agent, string $q, int $limit = 10, array $tags = []): Collection
    {
        $embedding = $this->embeddings->embed($q);

        $start = microtime(true);
        
        // 1. Vector Search
        $vectorQuery = Memory::query()
            ->visibleTo($agent)
            ->notExpired()
            ->with(['agent:id,name,description', 'relatedTo'])
            ->semanticSearch($embedding, $limit * 2);

        if (! empty($tags)) {
            $vectorQuery->withTags($tags);
        }
        $vectorResults = $vectorQuery->get();

        // 2. Keyword Search
        $keywordQuery = Memory::query()
            ->visibleTo($agent)
            ->notExpired()
            ->with(['agent:id,name,description', 'relatedTo'])
            ->keywordSearch($q, $limit * 2);

        if (! empty($tags)) {
            $keywordQuery->withTags($tags);
        }
        $keywordResults = $keywordQuery->get();

        // 3. Reciprocal Rank Fusion
        $results = $this->fuseResults($vectorResults, $keywordResults, $limit);

        $duration = (microtime(true) - $start) * 1000;
        \Illuminate\Support\Facades\Log::info("Hybrid search (Commons) completed in {$duration}ms", ['agent_id' => $agent->id, 'limit' => $limit, 'tags' => $tags]);

        return collect($results);
    }

    /**
     * Perform Reciprocal Rank Fusion on two sets of results, augmented with metadata.
     * 
     * Base RRF score for an item is: sum(1 / (k + rank))
     * Time Decay: e^(-lambda * days_old) where lambda controls the decay rate.
     * Importance: Scaled 1-10 multiplier.
     * Confidence: 0.0-1.0 multiplier.
     */
    private function fuseResults(Collection $vectorResults, Collection $keywordResults, int $limit = 10, int $k = 60): array
    {
        $scores = [];
        $memories = [];

        $processMemory = function($memory, $rank) use (&$scores, &$memories, $k) {
            $id = $memory->id;
            if (!isset($scores[$id])) {
                $scores[$id] = 0.0;
                $memories[$id] = $memory;
            }
            $scores[$id] += 1 / ($k + $rank + 1);
        };

        // 1. Calculate base RRF scores
        foreach ($vectorResults as $rank => $memory) {
            $processMemory($memory, $rank);
        }

        foreach ($keywordResults as $rank => $memory) {
            $processMemory($memory, $rank);
        }

        // 2. Apply advanced ranking modifiers
        $now = now();
        $decayLambda = 0.01; // Controls how fast older memories lose value

        foreach ($scores as $id => $baseScore) {
            $memory = $memories[$id];
            
            // Importance Multiplier (1-10 mapped to 0.5-2.0 or similar)
            // A default importance of 5 yields a 1.0 multiplier (no change)
            // An importance of 10 yields a 1.5 multiplier
            // An importance of 1 yields a 0.6 multiplier
            $importanceMultiplier = 0.5 + ($memory->importance / 10.0);
            
            // Confidence Multiplier (0.0 to 1.0)
            // A default confidence of 1.0 yields a 1.0 multiplier (no change)
            $confidenceMultiplier = $memory->confidence;
            
            // Time Decay Multiplier (exponential decay)
            $daysOld = max(0, $memory->created_at->diffInDays($now));
            $timeDecayMultiplier = exp(-$decayLambda * $daysOld);
            
            // Calculate final augmented score
            $scores[$id] = $baseScore * $importanceMultiplier * $confidenceMultiplier * $timeDecayMultiplier;
        }

        // Sort by final score descending
        arsort($scores);

        // Return top results
        $finalResults = [];
        foreach (array_slice(array_keys($scores), 0, $limit) as $id) {
            $finalResults[] = $memories[$id];
        }

        return $finalResults;
    }

    // -------------------------------------------------------------------------
    // Sharing
    // -------------------------------------------------------------------------

    public function shareWith(Memory $memory, Agent $recipient): void
    {
        $memory->sharedWith()->syncWithoutDetaching([$recipient->id]);
        \App\Events\MemoryShared::dispatch($memory, $recipient);
    }

    public function revokeShare(Memory $memory, Agent $recipient): void
    {
        $memory->sharedWith()->detach($recipient->id);
    }
}
