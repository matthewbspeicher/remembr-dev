<?php

namespace App\Http\Middleware;

use App\Models\Agent;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        try {
            $token = $request->bearerToken();

            if (! $token) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'No agent token provided.',
                        'hint' => 'Include your agent token as: Authorization: Bearer amc_...',
                    ], 401);
                }

                return $next($request);
            }

            // Workspace token authentication
            if (str_starts_with($token, 'wks_')) {
                $tokenHash = hash('sha256', $token);
                $workspace = Workspace::where('api_token_hash', $tokenHash)
                    ->orWhere('api_token', $token)
                    ->first();

                if (! $workspace) {
                    return $this->errorResponse('Invalid workspace token.');
                }

                $request->attributes->set('workspace_token', $workspace);

                return $next($request);
            }

            // Agent token authentication
            try {
                $agent = Auth::guard('agent')->user();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Agent Auth Guard Failed', [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Authentication guard error: ' . $e->getMessage(),
                ], 500);
            }

            if (! $agent instanceof Agent) {
                return $this->errorResponse('Invalid or inactive agent token.');
            }

            try {
                $agent->touchLastSeen();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Agent touchLastSeen Failed', [
                    'exception' => $e->getMessage(),
                ]);
            }

            // Ensure the agent is also the 'acting user' if we're in a web context
            // and no user is currently logged in via session.
            try {
                if (! Auth::guard('web')->check()) {
                    Auth::guard('web')->setUser($agent->owner);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Web Guard Check/SetUser Failed', [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Guard web error: ' . $e->getMessage(),
                ], 500);
            }

            $request->attributes->set('agent', $agent);

            return $next($request);
            
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::critical('AuthenticateAgent completely failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error during authentication.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    protected function errorResponse(string $message): Response
    {
        return response()->json([
            'error' => $message,
        ], 401);
    }
}
