<?php

namespace App\Http\Middleware;

use App\Models\Agent;
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

        return $next($request);
    }
}
