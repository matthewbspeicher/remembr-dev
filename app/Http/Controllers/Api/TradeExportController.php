<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TradeExportController extends Controller
{
    public function export(Request $request): JsonResponse|StreamedResponse
    {
        $request->validate([
            'format' => 'nullable|string|in:json,csv',
            'paper' => 'nullable',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'status' => 'nullable|string|in:open,closed,cancelled',
            'ticker' => 'nullable|string|max:64',
        ]);

        $agent = $request->attributes->get('agent');
        $paper = filter_var($request->input('paper', false), FILTER_VALIDATE_BOOLEAN);
        $format = $request->input('format', 'json');

        $query = Trade::where('agent_id', $agent->id)
            ->where('paper', $paper)
            ->whereNull('parent_trade_id')
            ->orderBy('entry_at');

        if ($request->has('from')) {
            $query->where('entry_at', '>=', $request->input('from'));
        }
        if ($request->has('to')) {
            $query->where('entry_at', '<=', $request->input('to'));
        }
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->has('ticker')) {
            $query->where('ticker', $request->input('ticker'));
        }

        $columns = [
            'id', 'ticker', 'direction', 'entry_price', 'exit_price',
            'quantity', 'fees', 'pnl', 'pnl_percent', 'strategy', 'tags',
            'entry_at', 'exit_at', 'status', 'confidence', 'paper',
        ];

        if ($format === 'csv') {
            return $this->streamCsv($query, $columns);
        }

        $trades = $query->get($columns)->map(function ($t) {
            $row = $t->toArray();
            $row['entry_at'] = $t->entry_at?->toIso8601String();
            $row['exit_at'] = $t->exit_at?->toIso8601String();
            return $row;
        });

        return response()->json(['data' => $trades]);
    }

    private function streamCsv($query, array $columns): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="trades-export-'.now()->format('Y-m-d').'.csv"',
        ];

        return response()->stream(function () use ($query, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            $query->chunk(500, function ($trades) use ($handle, $columns) {
                foreach ($trades as $trade) {
                    $row = [];
                    foreach ($columns as $col) {
                        $val = $trade->{$col};
                        if ($val instanceof \DateTimeInterface) {
                            $val = $val->toIso8601String();
                        } elseif (is_array($val)) {
                            $val = implode(';', $val);
                        }
                        $row[] = $val;
                    }
                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
