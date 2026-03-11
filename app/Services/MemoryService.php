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

        $embedding = $this->embeddings->embed($data['value']);

        $memory = Memory::updateOrCreate(
            [
                'agent_id' => $agent->id,
                'key' => $data['key'] ?? null,
            ],
            [
                'value' => $data['value'],
                'embedding' => '[' . implode(',', $embedding) . ']',
                'metadata' => $data['metadata'] ?? [],
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
            $data['embedding'] = '[' . implode(',', $this->embeddings->embed($data['value'])) . ']';
        }

        $memory->update($data);

        return $memory->fresh();
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

    public function listForAgent(Agent $agent, int $perPage = 20): LengthAwarePaginator
    {
        return Memory::query()
            ->where('agent_id', $agent->id)
            ->notExpired()
            ->latest()
            ->paginate($perPage);
    }

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------

    public function searchForAgent(Agent $agent, string $query, int $limit = 10): Collection
    {
        $embedding = $this->embeddings->embed($query);

        return Memory::query()
            ->where('agent_id', $agent->id)
            ->notExpired()
            ->semanticSearch($embedding, $limit)
            ->get();
    }

    public function searchCommons(Agent $agent, string $query, int $limit = 10): Collection
    {
        $embedding = $this->embeddings->embed($query);

        return Memory::query()
            ->visibleTo($agent)
            ->notExpired()
            ->semanticSearch($embedding, $limit)
            ->with('agent:id,name,description')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Sharing
    // -------------------------------------------------------------------------

    public function shareWith(Memory $memory, Agent $recipient): void
    {
        $memory->sharedWith()->syncWithoutDetaching([$recipient->id]);
    }

    public function revokeShare(Memory $memory, Agent $recipient): void
    {
        $memory->sharedWith()->detach($recipient->id);
    }
}
