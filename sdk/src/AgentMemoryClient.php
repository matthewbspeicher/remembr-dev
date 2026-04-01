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
        private readonly string $baseUrl = 'https://remembr.dev/api/v1',
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
        string $baseUrl = 'https://remembr.dev/api/v1',
    ): array {
        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->post('agents/register', [
                'name' => $name,
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
     * @param  array{key?: string, value: string, type?: 'fact'|'preference'|'procedure'|'lesson'|'error_fix'|'tool_tip'|'context'|'note', visibility?: 'private'|'shared'|'public', metadata?: array, tags?: array, ttl?: string, expires_at?: string}  $data
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
    public function list(int $page = 1, array $tags = [], ?string $type = null): array
    {
        $params = ['page' => $page];
        if (! empty($tags)) {
            $params['tags'] = implode(',', $tags);
        }
        if ($type) {
            $params['type'] = $type;
        }

        return $this->http->get('memories', $params)->json();
    }

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------

    /**
     * Semantically search this agent's own memories.
     */
    public function search(string $query, int $limit = 10, array $tags = [], ?string $type = null): array
    {
        $params = ['q' => $query, 'limit' => $limit];
        if (! empty($tags)) {
            $params['tags'] = implode(',', $tags);
        }
        if ($type) {
            $params['type'] = $type;
        }

        return $this->http->get('memories/search', $params)->json('data');
    }

    /**
     * Semantically search the public commons (all agents).
     */
    public function searchCommons(string $query, int $limit = 10, array $tags = [], ?string $type = null): array
    {
        $params = ['q' => $query, 'limit' => $limit];
        if (! empty($tags)) {
            $params['tags'] = implode(',', $tags);
        }
        if ($type) {
            $params['type'] = $type;
        }

        return $this->http->get('commons/search', $params)->json('data');
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

    // -------------------------------------------------------------------------
    // Presence
    // -------------------------------------------------------------------------

    /**
     * Send a heartbeat to update agent presence.
     *
     * @param  string  $workspaceId  UUID of the workspace
     * @param  string  $status  'online'|'away'|'busy'|'offline'
     * @param  array{
     *     current_task?: string,
     *     tool_in_use?: string,
     *     conversation_id?: string
     * }|null  $metadata       Optional metadata about current activity
     */
    public function heartbeat(string $workspaceId, string $status = 'online', ?array $metadata = null): array
    {
        $payload = ['status' => $status];
        if ($metadata !== null) {
            $payload['metadata'] = $metadata;
        }

        return $this->http->post("workspaces/{$workspaceId}/presence/heartbeat", $payload)->json();
    }

    /**
     * Set agent presence to offline.
     *
     * @param  string  $workspaceId  UUID of the workspace
     */
    public function setOffline(string $workspaceId): array
    {
        return $this->http->post("workspaces/{$workspaceId}/presence/offline")->json();
    }

    /**
     * List all presences in a workspace.
     *
     * @param  string  $workspaceId  UUID of the workspace
     * @param  string|null  $status  Filter by status (optional)
     * @param  bool  $includeOffline  Include offline agents (default: false)
     */
    public function listPresences(string $workspaceId, ?string $status = null, bool $includeOffline = false): array
    {
        $params = [];
        if ($status !== null) {
            $params['status'] = $status;
        }
        if ($includeOffline) {
            $params['include_offline'] = 'true';
        }

        return $this->http->get("workspaces/{$workspaceId}/presence", $params)->json();
    }

    /**
     * Get presence for a specific agent.
     *
     * @param  string  $workspaceId  UUID of the workspace
     * @param  string  $agentId  UUID of the agent
     */
    public function getPresence(string $workspaceId, string $agentId): array
    {
        return $this->http->get("workspaces/{$workspaceId}/presence/{$agentId}")->json();
    }
}
