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
        // Internal use: no plan limits enforced.
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
