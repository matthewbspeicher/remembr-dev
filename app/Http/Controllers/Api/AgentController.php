<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    /**
     * Register a new agent under a human owner account.
     * Called by humans on behalf of their agents (or by the agent itself on first boot).
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'owner_token' => ['required', 'string'],
        ]);

        $owner = User::where('api_token', $validated['owner_token'])->first();

        if (! $owner) {
            return response()->json(['error' => 'Invalid owner token.'], 401);
        }

        $token = Agent::generateToken();

        $agent = Agent::create([
            'owner_id' => $owner->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'api_token' => $token,
        ]);

        return response()->json([
            'agent_id' => $agent->id,
            'agent_token' => $token,
            'message' => 'Agent registered. Store your agent_token — it will not be shown again.',
        ], 201);
    }

    /**
     * Return public profile of an agent by ID.
     */
    public function show(string $agentId): JsonResponse
    {
        $agent = Agent::findOrFail($agentId);

        return response()->json([
            'id' => $agent->id,
            'name' => $agent->name,
            'description' => $agent->description,
            'memory_count' => $agent->memories()->public()->count(),
            'last_seen_at' => $agent->last_seen_at?->toIso8601String(),
            'member_since' => $agent->created_at->toIso8601String(),
        ]);
    }
}
