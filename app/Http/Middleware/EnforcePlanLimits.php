<?php

namespace App\Http\Middleware;

use App\Models\Memory;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePlanLimits
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isWriteOperation($request) || ! $this->isMemoryEndpoint($request)) {
            return $next($request);
        }

        $agent = $request->attributes->get('agent');
        $workspace = $request->attributes->get('workspace_token');

        if ($workspace) {
            $owner = $workspace->owner;
            if ($owner && $owner->isDowngraded()) {
                return response()->json([
                    'error' => 'Workspace memories are read-only. Upgrade to Pro to restore write access.',
                ], 403);
            }

            return $next($request);
        }

        if (! $agent) {
            return $next($request);
        }

        $user = $agent->owner;
        if (! $user || ! $user->isDowngraded()) {
            return $next($request);
        }

        $allowedAgentIds = $user->agents()
            ->orderBy('id')
            ->limit($user->maxAgents())
            ->pluck('id');

        if (! $allowedAgentIds->contains($agent->id)) {
            return response()->json([
                'error' => 'This agent is in read-only mode. Upgrade to Pro to restore write access.',
            ], 403);
        }

        if ($request->isMethod('POST') && $request->input('workspace_id')) {
            return response()->json([
                'error' => 'Workspace memories are read-only. Upgrade to Pro to restore write access.',
            ], 403);
        }

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

        return $next($request);
    }

    private function isWriteOperation(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    private function isMemoryEndpoint(Request $request): bool
    {
        return preg_match('#v1/memories($|/)#', $request->path()) === 1;
    }
}
