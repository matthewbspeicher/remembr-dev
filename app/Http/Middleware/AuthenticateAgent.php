<?php

namespace App\Http\Middleware;

use App\Models\Agent;
use App\Models\Memory;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'error' => 'No agent token provided.',
                'hint' => 'Include your agent token as: Authorization: Bearer amc_...',
            ], 401);
        }

        // Workspace token authentication (preserved from existing code)
        if (str_starts_with($token, 'wks_')) {
            $workspace = \App\Models\Workspace::where('api_token', $token)->first();

            if (! $workspace) {
                return response()->json([
                    'error' => 'Invalid workspace token.',
                ], 401);
            }

            $request->attributes->set('workspace_token', $workspace);
            return $next($request);
        }

        $agent = Agent::query()
            ->where('api_token', $token)
            ->where('is_active', true)
            ->first();

        if (! $agent) {
            return response()->json([
                'error' => 'Invalid or inactive agent token.',
            ], 401);
        }

        $agent->touchLastSeen();

        $request->attributes->set('agent', $agent);

        // Soft-lock check for write operations on memory endpoints
        if ($this->isWriteOperation($request) && $this->isMemoryEndpoint($request)) {
            $user = $agent->owner;

            if ($user && $user->isDowngraded()) {
                $response = $this->enforceSoftLock($request, $agent, $user);
                if ($response) {
                    return $response;
                }
            }
        }

        return $next($request);
    }

    private function isWriteOperation(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    private function isMemoryEndpoint(Request $request): bool
    {
        $path = $request->path();

        return preg_match('#v1/memories($|/)#', $path) === 1;
    }

    private function enforceSoftLock(Request $request, Agent $agent, $user): ?Response
    {
        // Check if this agent is outside the first 3 (order by id for determinism)
        $allowedAgentIds = $user->agents()
            ->orderBy('id')
            ->limit(3)
            ->pluck('id');

        if (! $allowedAgentIds->contains($agent->id)) {
            return response()->json([
                'error' => 'This agent is in read-only mode. Upgrade to Pro to restore write access.',
            ], 403);
        }

        // Check workspace memory writes
        if ($request->isMethod('POST') && $request->input('workspace_id')) {
            return response()->json([
                'error' => 'Workspace memories are read-only. Upgrade to Pro to restore write access.',
            ], 403);
        }

        // For updates/deletes, check if target memory belongs to a workspace
        if (in_array($request->method(), ['PUT', 'PATCH', 'DELETE'])) {
            $key = $request->route('key');
            if ($key) {
                $memory = Memory::where('agent_id', $agent->id)->where('key', $key)->first();
                if ($memory && $memory->workspace_id) {
                    return response()->json([
                        'error' => 'Workspace memories are read-only. Upgrade to Pro to restore write access.',
                    ], 403);
                }
            }
        }

        return null;
    }
}
