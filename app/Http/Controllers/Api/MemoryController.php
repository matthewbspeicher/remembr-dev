<?php

namespace App\Http\Controllers\Api;

use App\Concerns\FormatsMemories;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Memory;
use App\Services\MemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemoryController extends Controller
{
    use FormatsMemories;
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
            'key' => ['nullable', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:10000'],
            'visibility' => ['nullable', 'in:private,shared,public,workspace'],
            'workspace_id' => ['nullable', 'required_if:visibility,workspace', 'uuid', 'exists:workspaces,id'],
            'metadata' => ['nullable', 'array'],
            'importance' => ['nullable', 'integer', 'min:1', 'max:10'],
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'expires_at' => ['nullable', 'date', 'after:now', 'prohibits:ttl'],
            'ttl' => ['nullable', 'string', 'regex:/^\d+[hmd]$/', 'prohibits:expires_at'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:50'],
            'relations' => ['nullable', 'array', 'max:50'],
            'relations.*.id' => ['required', 'uuid', 'exists:memories,id'],
            'relations.*.type' => ['nullable', 'string', 'max:50'],
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
        $tags = $request->has('tags') ? explode(',', $request->input('tags')) : [];
        $paginated = $this->memories->listForAgent($agent, 20, $tags);

        return response()->json([
            'data' => collect($paginated->items())->map(fn ($m) => $this->formatMemory($m)),
            'meta' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
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
            'value' => ['sometimes', 'string', 'max:10000'],
            'visibility' => ['sometimes', 'in:private,shared,public,workspace'],
            'workspace_id' => ['sometimes', 'nullable', 'required_if:visibility,workspace', 'uuid', 'exists:workspaces,id'],
            'metadata' => ['sometimes', 'array'],
            'importance' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'confidence' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now', 'prohibits:ttl'],
            'ttl' => ['sometimes', 'nullable', 'string', 'regex:/^\d+[hmd]$/', 'prohibits:expires_at'],
            'tags' => ['sometimes', 'array', 'max:10'],
            'tags.*' => ['string', 'max:50'],
            'relations' => ['sometimes', 'array', 'max:50'],
            'relations.*.id' => ['required', 'uuid', 'exists:memories,id'],
            'relations.*.type' => ['nullable', 'string', 'max:50'],
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
    // Compact memories
    // POST /v1/memories/compact
    // -------------------------------------------------------------------------

    public function compact(Request $request, \App\Services\SummarizationService $summarizer): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $validated = $request->validate([
            'keys' => ['required', 'array', 'min:2', 'max:50'],
            'keys.*' => ['string'],
            'summary_key' => ['required', 'string', 'max:255'],
        ]);

        $memories = \App\Models\Memory::where('agent_id', $agent->id)
            ->whereIn('key', $validated['keys'])
            ->get();

        if ($memories->count() < 2) {
            return response()->json(['error' => 'Not enough valid memories found to compact.'], 422);
        }

        try {
            $summaryText = $summarizer->summarize($memories, $agent);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate summary: ' . $e->getMessage()], 500);
        }

        $relations = $memories->map(fn($m) => ['id' => $m->id, 'type' => 'compacted_from'])->toArray();

        $summaryMemory = $this->memories->store($agent, [
            'key' => $validated['summary_key'],
            'value' => $summaryText,
            'importance' => 8, // Summaries are usually important
            'visibility' => 'private',
            'relations' => $relations,
        ]);

        foreach ($memories as $memory) {
            $this->memories->update($memory, ['visibility' => 'archived']);
        }

        return response()->json($this->formatMemory($summaryMemory), 201);
    }

    // -------------------------------------------------------------------------
    // Semantic search — own memories
    // GET /v1/memories/search?q=...&limit=10
    // -------------------------------------------------------------------------

    public function search(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:500'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'tags' => ['nullable', 'string'],
        ]);

        $tags = $request->has('tags') ? explode(',', $request->input('tags')) : [];

        $results = $this->memories->searchForAgent(
            $agent,
            $request->string('q'),
            $request->integer('limit', 10),
            $tags
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
    // GET /v1/commons
    // -------------------------------------------------------------------------

    public function commonsIndex(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string'],
            'tags' => ['nullable', 'string'],
        ]);

        $limit = $request->integer('limit', 10);
        $cursor = $request->input('cursor');
        $tags = $request->has('tags') ? explode(',', $request->input('tags')) : [];

        // Only cache the "Front Page" (no cursor, default limit, no tags)
        if ($cursor === null && $limit === 10 && empty($tags)) {
            return response()->json(
                \Illuminate\Support\Facades\Cache::remember('commons_front_page', 5, function () use ($limit) {
                    return $this->getCommonsData($limit, []);
                })
            );
        }

        return response()->json($this->getCommonsData($limit, $tags));
    }

    private function getCommonsData(int $limit, array $tags = []): array
    {
        $query = Memory::query()
            ->public()
            ->notExpired()
            ->latest()
            ->with('agent:id,name,description');

        if (! empty($tags)) {
            $query->withTags($tags);
        }

        $paginated = $query->cursorPaginate($limit);

        return [
            'data' => collect($paginated->items())->map(fn (Memory $m) => [
                ...$this->formatMemory($m),
                'agent' => [
                    'id' => $m->agent->id,
                    'name' => $m->agent->name,
                    'description' => $m->agent->description,
                ],
            ]),
            'meta' => [
                'next_cursor' => $paginated->nextCursor()?->encode(),
                'prev_cursor' => $paginated->previousCursor()?->encode(),
                'per_page' => $paginated->perPage(),
                'has_more' => $paginated->hasMorePages(),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Semantic search — public commons
    // GET /v1/commons/search?q=...&limit=10
    // -------------------------------------------------------------------------

    public function commonsSearch(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:500'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'tags' => ['nullable', 'string'],
        ]);

        $tags = $request->has('tags') ? explode(',', $request->input('tags')) : [];

        $results = $this->memories->searchCommons(
            $agent,
            $request->string('q'),
            $request->integer('limit', 10),
            $tags
        );

        return response()->json([
            'data' => $results->map(fn ($m) => [
                ...$this->formatMemory($m),
                'agent' => [
                    'id' => $m->agent->id,
                    'name' => $m->agent->name,
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

}
