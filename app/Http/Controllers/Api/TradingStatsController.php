<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use App\Models\TradingStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradingStatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $paper = filter_var($request->input('paper', 'true'), FILTER_VALIDATE_BOOLEAN);

        $stats = TradingStats::where('agent_id', $agent->id)
            ->where('paper', $paper)
            ->first();

        if (! $stats) {
            return response()->json([
                'paper' => $paper,
                'total_trades' => 0,
                'win_count' => 0,
                'loss_count' => 0,
                'win_rate' => null,
                'profit_factor' => null,
                'total_pnl' => 0,
                'avg_pnl_percent' => null,
                'best_trade_pnl' => null,
                'worst_trade_pnl' => null,
                'sharpe_ratio' => null,
                'current_streak' => 0,
            ]);
        }

        return response()->json($stats);
    }

    public function byTicker(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $paper = filter_var($request->input('paper', 'true'), FILTER_VALIDATE_BOOLEAN);

        $data = Trade::where('agent_id', $agent->id)
            ->where('paper', $paper)
            ->where('status', 'closed')
            ->whereNull('parent_trade_id')
            ->select('ticker')
            ->selectRaw('COUNT(*) as total_trades')
            ->selectRaw('SUM(CASE WHEN pnl > 0 THEN 1 ELSE 0 END) as win_count')
            ->selectRaw('ROUND(SUM(CASE WHEN pnl > 0 THEN 1 ELSE 0 END)::numeric / NULLIF(COUNT(*), 0) * 100, 2) as win_rate')
            ->selectRaw('SUM(pnl) as total_pnl')
            ->selectRaw('CASE WHEN SUM(CASE WHEN pnl < 0 THEN ABS(pnl) ELSE 0 END) > 0 THEN ROUND(SUM(CASE WHEN pnl > 0 THEN pnl ELSE 0 END) / SUM(CASE WHEN pnl < 0 THEN ABS(pnl) ELSE 0 END), 4) ELSE NULL END as profit_factor')
            ->groupBy('ticker')
            ->orderByRaw('SUM(pnl) DESC')
            ->get();

        return response()->json(['data' => $data]);
    }

    public function byStrategy(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $paper = filter_var($request->input('paper', 'true'), FILTER_VALIDATE_BOOLEAN);

        $data = Trade::where('agent_id', $agent->id)
            ->where('paper', $paper)
            ->where('status', 'closed')
            ->whereNull('parent_trade_id')
            ->whereNotNull('strategy')
            ->select('strategy')
            ->selectRaw('COUNT(*) as total_trades')
            ->selectRaw('SUM(CASE WHEN pnl > 0 THEN 1 ELSE 0 END) as win_count')
            ->selectRaw('ROUND(SUM(CASE WHEN pnl > 0 THEN 1 ELSE 0 END)::numeric / NULLIF(COUNT(*), 0) * 100, 2) as win_rate')
            ->selectRaw('SUM(pnl) as total_pnl')
            ->selectRaw('CASE WHEN SUM(CASE WHEN pnl < 0 THEN ABS(pnl) ELSE 0 END) > 0 THEN ROUND(SUM(CASE WHEN pnl > 0 THEN pnl ELSE 0 END) / SUM(CASE WHEN pnl < 0 THEN ABS(pnl) ELSE 0 END), 4) ELSE NULL END as profit_factor')
            ->groupBy('strategy')
            ->orderByRaw('SUM(pnl) DESC')
            ->get();

        return response()->json(['data' => $data]);
    }

    public function equityCurve(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $paper = filter_var($request->input('paper', 'true'), FILTER_VALIDATE_BOOLEAN);

        $dailyPnl = Trade::where('agent_id', $agent->id)
            ->where('paper', $paper)
            ->where('status', 'closed')
            ->whereNull('parent_trade_id')
            ->selectRaw("DATE(exit_at) as date")
            ->selectRaw('SUM(pnl) as daily_pnl')
            ->groupByRaw('DATE(exit_at)')
            ->orderByRaw('DATE(exit_at)')
            ->get();

        $cumulative = '0';
        $data = $dailyPnl->map(function ($row) use (&$cumulative) {
            $cumulative = bcadd($cumulative, (string) $row->daily_pnl, 8);
            return [
                'date' => $row->date,
                'cumulative_pnl' => (float) $cumulative,
            ];
        });

        return response()->json(['data' => $data->values()]);
    }
}
