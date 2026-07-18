<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ChartsController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $range = in_array($request->query('range'), ['week', 'month', 'year'])
            ? $request->query('range')
            : 'month';

        $now = now();
        [$start, $end, $bucket] = match ($range) {
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek(), 'day'],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear(), 'month'],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'day'],
        };

        $transactions = $user->transactions()
            ->with('category:id,name,color')
            ->whereBetween('transaction_date', [$start, $end])
            ->get();

        $expenses = $transactions->where('type', 'expense');
        $incomes = $transactions->where('type', 'income');

        $base = fn ($t) => $t->amount * $t->exchange_rate;

        // Torta: gasto por categoría (convertido a moneda base)
        $byCategory = $expenses
            ->groupBy('category_id')
            ->map(function ($group) use ($base) {
                $cat = $group->first()->category;
                return [
                    'name' => $cat?->name ?? 'Sin categoría',
                    'color' => $cat?->color ?? '#9ca3af',
                    'total' => (float) $group->sum($base),
                ];
            })
            ->sortByDesc('total')
            ->values();

        // Tendencia por bucket
        $labels = [];
        $expenseSeries = [];
        $incomeSeries = [];

        if ($bucket === 'day') {
            $cursor = $start->copy();
            while ($cursor <= $end) {
                $key = $cursor->format('Y-m-d');
                $labels[] = $range === 'week' ? $cursor->translatedFormat('D') : $cursor->format('j');
                $expenseSeries[] = (float) $expenses->filter(fn ($t) => $t->transaction_date->format('Y-m-d') === $key)->sum($base);
                $incomeSeries[] = (float) $incomes->filter(fn ($t) => $t->transaction_date->format('Y-m-d') === $key)->sum($base);
                $cursor->addDay();
            }
        } else { // month
            for ($m = 1; $m <= 12; $m++) {
                $labels[] = Carbon::create()->month($m)->translatedFormat('M');
                $expenseSeries[] = (float) $expenses->filter(fn ($t) => (int) $t->transaction_date->format('n') === $m)->sum($base);
                $incomeSeries[] = (float) $incomes->filter(fn ($t) => (int) $t->transaction_date->format('n') === $m)->sum($base);
            }
        }

        return Inertia::render('Charts/Index', [
            'range' => $range,
            'periodLabel' => $this->periodLabel($range, $start, $end),
            'byCategory' => $byCategory,
            'totals' => [
                'income' => (float) $incomes->sum($base),
                'expense' => (float) $expenses->sum($base),
            ],
            'trend' => [
                'labels' => $labels,
                'expense' => $expenseSeries,
                'income' => $incomeSeries,
            ],
            'currencySymbol' => Currency::find($user->default_currency)?->symbol ?? '$',
        ]);
    }

    private function periodLabel(string $range, Carbon $start, Carbon $end): string
    {
        return match ($range) {
            'week' => 'Semana del '.$start->format('j').' al '.$end->translatedFormat('j \d\e F'),
            'year' => $start->format('Y'),
            default => ucfirst($start->translatedFormat('F Y')),
        };
    }
}
