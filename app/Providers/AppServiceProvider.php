<?php

namespace App\Providers;

use App\Services\EmbeddingService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EmbeddingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('agent_api', function (Request $request) {
            if ($agent = $request->attributes->get('agent')) {
                return Limit::perMinute(60)->by($agent->id);
            }
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
