<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken as Middleware;

class ValidateAgentCsrf extends Middleware
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, \Closure $next)
    {
        $token = $request->bearerToken();

        if ($token && (str_starts_with($token, 'amc_') || str_starts_with($token, 'wks_'))) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
