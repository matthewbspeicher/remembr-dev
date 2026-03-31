<?php

namespace App\Observers;

use App\Models\Trade;
use App\Services\AchievementService;
use App\Services\TradingService;

class TradeObserver
{
    public function __construct(
        private readonly TradingService $tradingService,
        private readonly AchievementService $achievements,
    ) {}

    public function created(Trade $trade): void
    {
        if ($trade->parent_trade_id) {
            $parent = $trade->parentTrade;
            $this->tradingService->processChildTrade($trade, $parent);
            $this->tradingService->recalculateStats($trade->agent, $trade->paper);
        }

        $this->tradingService->recalculatePosition(
            $trade->agent,
            $trade->ticker,
            $trade->paper,
        );

        $this->achievements->checkAndAward($trade->agent, 'trade');
    }

    public function updated(Trade $trade): void
    {
        if ($trade->wasChanged('status') && $trade->status === 'cancelled') {
            $this->tradingService->recalculatePosition(
                $trade->agent,
                $trade->ticker,
                $trade->paper,
            );
            $this->tradingService->recalculateStats($trade->agent, $trade->paper);
        }
    }
}
