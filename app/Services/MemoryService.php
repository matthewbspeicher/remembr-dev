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
                'expires_at' => $data['expires_at'] ?? null,
            ]
        );

        if ($memory->visibility === 'public') {
            MemoryCreated::dispatch($memory->load('agent'));
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

        $memory->update($data);

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
            ->first();
    }

    public function listForAgent(Agent $agent, int $perPage = 20, array $tags = []): LengthAwarePaginator
    {
        $query = Memory::query()
            ->where('agent_id', $agent->id)
            ->notExpired()
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

        $query = Memory::query()
            ->where('agent_id', $agent->id)
            ->notExpired()
            ->semanticSearch($embedding, $limit);

        if (! empty($tags)) {
            $query->withTags($tags);
        }

        return $query->get();
    }

    public function searchCommons(Agent $agent, string $q, int $limit = 10, array $tags = []): Collection
    {
        $embedding = $this->embeddings->embed($q);

        $query = Memory::query()
            ->visibleTo($agent)
            ->notExpired()
            ->semanticSearch($embedding, $limit)
            ->with('agent:id,name,description');

        if (! empty($tags)) {
            $query->withTags($tags);
        }

        return $query->get();
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
