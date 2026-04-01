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
            ->selectRaw('AVG(pnl_percent) as avg_pnl_percent')
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
            ->selectRaw('AVG(pnl_percent) as avg_pnl_percent')
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
            ->selectRaw('DATE(exit_at) as date')
            ->selectRaw('SUM(pnl) as daily_pnl')
            ->groupByRaw('DATE(exit_at)')
            ->orderByRaw('DATE(exit_at)')
            ->get();

        $cumulative = '0';
        $data = $dailyPnl->map(function ($row) use (&$cumulative) {
            $cumulative = bcadd($cumulative, (string) $row->daily_pnl, 8);

            return [
                'date' => $row->date,
                'daily_pnl' => (float) $row->daily_pnl,
                'cumulative_pnl' => (float) $cumulative,
            ];
        });

        return response()->json(['data' => $data->values()]);
    }

    public function correlations(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $paper = filter_var($request->input('paper', false), FILTER_VALIDATE_BOOLEAN);

        $trades = Trade::where('agent_id', $agent->id)
            ->where('paper', $paper)
            ->where('status', 'closed')
            ->whereNull('parent_trade_id')
            ->whereNotNull('pnl')
            ->orderBy('exit_at')
            ->get(['ticker', 'pnl', 'exit_at']);

        // Group PnL series by ticker
        $series = [];
        foreach ($trades as $trade) {
            $series[$trade->ticker][] = (float) $trade->pnl;
        }

        // Need at least 2 tickers with 3+ trades each
        $series = array_filter($series, fn ($s) => count($s) >= 3);
        $tickers = array_keys($series);

        if (count($tickers) < 2) {
            return response()->json(['data' => (object) []]);
        }

        // Compute Pearson correlation for each pair
        $matrix = [];
        foreach ($tickers as $a) {
            $matrix[$a] = [];
            foreach ($tickers as $b) {
                if ($a === $b) {
                    $matrix[$a][$b] = 1.0;

                    continue;
                }
                $matrix[$a][$b] = $this->pearson($series[$a], $series[$b]);
            }
        }

        return response()->json(['data' => $matrix]);
    }

    private function pearson(array $x, array $y): ?float
    {
        $n = min(count($x), count($y));
        if ($n < 3) {
            return null;
        }

        $x = array_slice($x, 0, $n);
        $y = array_slice($y, 0, $n);

        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;

        $num = 0;
        $denomX = 0;
        $denomY = 0;

        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $meanX;
            $dy = $y[$i] - $meanY;
            $num += $dx * $dy;
            $denomX += $dx * $dx;
            $denomY += $dy * $dy;
        }

        $denom = sqrt($denomX * $denomY);

        return $denom > 0 ? round($num / $denom, 4) : null;
    }
}
