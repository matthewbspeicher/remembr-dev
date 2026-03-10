<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Memory;
use App\Services\MemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemoryController extends Controller
{
    public function __construct(
        private readonly MemoryService $memories,
    ) {}

    // -------------------------------------------------------------------------
    // Store a memory
    // POST /v1/memories
    // -------------------------------------------------------------------------

    public function store(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $validated = $request->validate([
            'key'        => ['nullable', 'string', 'max:255'],
            'value'      => ['required', 'string', 'max:10000'],
            'visibility' => ['nullable', 'in:private,shared,public'],
            'metadata'   => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $memory = $this->memories->store($agent, $validated);

        return response()->json($this->formatMemory($memory), 201);
    }

    // -------------------------------------------------------------------------
    // Get memory by key
    // GET /v1/memories/{key}
    // -------------------------------------------------------------------------

    public function show(Request $request, string $key): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $memory = $this->memories->findByKey($agent, $key);

        if (! $memory) {
            return response()->json(['error' => 'Memory not found.'], 404);
        }

        return response()->json($this->formatMemory($memory));
    }

    // -------------------------------------------------------------------------
    // List own memories
    // GET /v1/memories
    // -------------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $paginated = $this->memories->listForAgent($agent);

        return response()->json([
            'data'  => collect($paginated->items())->map(fn ($m) => $this->formatMemory($m)),
            'meta'  => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Update a memory
    // PATCH /v1/memories/{key}
    // -------------------------------------------------------------------------

    public function update(Request $request, string $key): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $memory = $this->memories->findByKey($agent, $key);

        if (! $memory) {
            return response()->json(['error' => 'Memory not found.'], 404);
        }

        $validated = $request->validate([
            'value'      => ['sometimes', 'string', 'max:10000'],
            'visibility' => ['sometimes', 'in:private,shared,public'],
            'metadata'   => ['sometimes', 'array'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $memory = $this->memories->update($memory, $validated);

        return response()->json($this->formatMemory($memory));
    }

    // -------------------------------------------------------------------------
    // Delete a memory
    // DELETE /v1/memories/{key}
    // -------------------------------------------------------------------------

    public function destroy(Request $request, string $key): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $memory = $this->memories->findByKey($agent, $key);

        if (! $memory) {
            return response()->json(['error' => 'Memory not found.'], 404);
        }

        $this->memories->delete($memory);

        return response()->json(['message' => 'Memory deleted.']);
    }

    // -------------------------------------------------------------------------
    // Semantic search — own memories
    // GET /v1/memories/search?q=...&limit=10
    // -------------------------------------------------------------------------

    public function search(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $request->validate([
            'q'     => ['required', 'string', 'min:1', 'max:500'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $results = $this->memories->searchForAgent(
            $agent,
            $request->string('q'),
            $request->integer('limit', 10)
        );

        return response()->json([
            'data' => $results->map(fn ($m) => [
                ...$this->formatMemory($m),
                'similarity' => round($m->similarity ?? 0, 4),
            ]),
        ]);
    }

    // -------------------------------------------------------------------------
    // Semantic search — public commons
    // GET /v1/commons/search?q=...&limit=10
    // -------------------------------------------------------------------------

    public function commonsSearch(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $request->validate([
            'q'     => ['required', 'string', 'min:1', 'max:500'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $results = $this->memories->searchCommons(
            $agent,
            $request->string('q'),
            $request->integer('limit', 10)
        );

        return response()->json([
            'data' => $results->map(fn ($m) => [
                ...$this->formatMemory($m),
                'agent'      => [
                    'id'          => $m->agent->id,
                    'name'        => $m->agent->name,
                    'description' => $m->agent->description,
                ],
                'similarity' => round($m->similarity ?? 0, 4),
            ]),
        ]);
    }

    // -------------------------------------------------------------------------
    // Share a memory with another agent
    // POST /v1/memories/{key}/share
    // -------------------------------------------------------------------------

    public function share(Request $request, string $key): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $memory = $this->memories->findByKey($agent, $key);

        if (! $memory) {
            return response()->json(['error' => 'Memory not found.'], 404);
        }

        $validated = $request->validate([
            'agent_id' => ['required', 'uuid', 'exists:agents,id'],
        ]);

        $recipient = Agent::findOrFail($validated['agent_id']);
        $this->memories->shareWith($memory, $recipient);

        return response()->json(['message' => "Memory shared with agent {$recipient->name}."]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function formatMemory(Memory $memory): array
    {
        return [
            'id'         => $memory->id,
            'key'        => $memory->key,
            'value'      => $memory->value,
            'visibility' => $memory->visibility,
            'metadata'   => $memory->metadata,
            'created_at' => $memory->created_at->toIso8601String(),
            'updated_at' => $memory->updated_at->toIso8601String(),
            'expires_at' => $memory->expires_at?->toIso8601String(),
        ];
    }
}
