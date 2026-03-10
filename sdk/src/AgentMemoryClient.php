<?php

namespace AgentMemory;

use AgentMemory\Exceptions\AgentMemoryException;
use AgentMemory\Exceptions\AuthenticationException;
use AgentMemory\Exceptions\MemoryNotFoundException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AgentMemoryClient
{
    private PendingRequest $http;

    public function __construct(
        private readonly string $agentToken,
        private readonly string $baseUrl = 'https://api.agentmemory.dev/v1',
    ) {
        $this->http = Http::withToken($agentToken)
            ->baseUrl($baseUrl)
            ->acceptJson()
            ->throw(function ($response, $e) {
                match ($response->status()) {
                    401 => throw new AuthenticationException($response->json('error', 'Unauthorized')),
                    404 => throw new MemoryNotFoundException($response->json('error', 'Not found')),
                    default => throw new AgentMemoryException($response->json('error', $e->getMessage())),
                };
            });
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function register(
        string $ownerToken,
        string $name,
        ?string $description = null,
        string $baseUrl = 'https://api.agentmemory.dev/v1',
    ): array {
        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->post('agents/register', [
                'name'        => $name,
                'description' => $description,
                'owner_token' => $ownerToken,
            ]);

        if ($response->failed()) {
            throw new AgentMemoryException($response->json('error', 'Registration failed'));
        }

        return $response->json(); // ['agent_id', 'agent_token', 'message']
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Store or update a memory.
     *
     * @param  array{key?: string, value: string, visibility?: 'private'|'shared'|'public', metadata?: array, expires_at?: string}  $data
     */
    public function remember(array $data): array
    {
        return $this->http->post('memories', $data)->json();
    }

    /**
     * Convenience: store a simple key → value pair.
     */
    public function set(string $key, string $value, string $visibility = 'private'): array
    {
        return $this->remember(compact('key', 'value', 'visibility'));
    }

    /**
     * Update an existing memory by key.
     */
    public function update(string $key, array $data): array
    {
        return $this->http->patch("memories/{$key}", $data)->json();
    }

    /**
     * Delete a memory by key.
     */
    public function forget(string $key): void
    {
        $this->http->delete("memories/{$key}");
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    /**
     * Retrieve a memory by key.
     */
    public function get(string $key): array
    {
        return $this->http->get("memories/{$key}")->json();
    }

    /**
     * List all memories for this agent (paginated).
     */
    public function list(int $page = 1): array
    {
        return $this->http->get('memories', ['page' => $page])->json();
    }

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------

    /**
     * Semantically search this agent's own memories.
     */
    public function search(string $query, int $limit = 10): array
    {
        return $this->http->get('memories/search', ['q' => $query, 'limit' => $limit])->json('data');
    }

    /**
     * Semantically search the public commons (all agents).
     */
    public function searchCommons(string $query, int $limit = 10): array
    {
        return $this->http->get('commons/search', ['q' => $query, 'limit' => $limit])->json('data');
    }

    // -------------------------------------------------------------------------
    // Sharing
    // -------------------------------------------------------------------------

    /**
     * Share a memory with a specific agent.
     */
    public function shareWith(string $key, string $agentId): void
    {
        $this->http->post("memories/{$key}/share", ['agent_id' => $agentId]);
    }
}
