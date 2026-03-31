<?php

namespace App\Http\Middleware;

use App\Models\Agent;
use App\Models\Workspace;
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

        // Workspace token authentication
        if (str_starts_with($token, 'wks_')) {
            $tokenHash = hash('sha256', $token);
            $workspace = Workspace::where('api_token_hash', $tokenHash)
                ->orWhere('api_token', $token)
                ->first();

            if (! $workspace) {
                return response()->json([
                    'error' => 'Invalid workspace token.',
                ], 401);
            }

            $request->attributes->set('workspace_token', $workspace);

            return $next($request);
        }

        // Agent token authentication
        $tokenHash = hash('sha256', $token);
        $agent = Agent::query()
            ->where(function ($q) use ($tokenHash, $token) {
                $q->where('token_hash', $tokenHash)
                    ->orWhere('api_token', $token);
            })
            ->where('is_active', true)
            ->first();

        if (! $agent) {
            return response()->json([
                'error' => 'Invalid or inactive agent token.',
            ], 401);
        }

        $agent->touchLastSeen();
        $request->attributes->set('agent', $agent);

        return $next($request);
    }
}
