<?php

namespace App\Providers;

use App\Events\PositionChanged;
use App\Events\TradeClosed;
use App\Events\TradeOpened;
use App\Listeners\EvaluateTradeAlerts;
use App\Listeners\SyncAgentQuotas;
use App\Listeners\TriggerWebhooks;
use App\Models\Trade;
use App\Observers\TradeObserver;
use App\Services\EmbeddingService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Events\WebhookReceived;

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
        RateLimiter::for('api', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('agent_api', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }
            if ($agent = $request->attributes->get('agent')) {
                return Limit::perMinute(300)->by($agent->id);
            }

            return Limit::perMinute(300)->by($request->ip());
        });

        Event::listen(WebhookReceived::class, SyncAgentQuotas::class);

        Event::listen(TradeOpened::class, [TriggerWebhooks::class, 'handleTradeOpened']);
        Event::listen(TradeClosed::class, [TriggerWebhooks::class, 'handleTradeClosed']);
        Event::listen(PositionChanged::class, [TriggerWebhooks::class, 'handlePositionChanged']);

        Event::listen(TradeOpened::class, [EvaluateTradeAlerts::class, 'handleTradeOpened']);
        Event::listen(TradeClosed::class, [EvaluateTradeAlerts::class, 'handleTradeClosed']);

        Trade::observe(TradeObserver::class);
    }
}
