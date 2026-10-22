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

        // Cualquier día del período a mostrar; las flechas mandan el inicio del anterior
        // o del siguiente. Sin fecha, inválida o futura: el período actual.
        $anchor = now();
        $date = $request->query('date');
        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            try {
                $parsed = Carbon::createFromFormat('!Y-m-d', $date);
                if ($parsed->format('Y-m-d') === $date && $parsed->lte(now())) {
                    $anchor = $parsed;
                }
            } catch (\Throwable) {
                // fecha imposible: queda el período actual
            }
        }

        [$start, $end, $bucket] = match ($range) {
            'week' => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek(), 'day'],
            'year' => [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear(), 'month'],
            default => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth(), 'day'],
        };

        [$prev, $next] = match ($range) {
            'week' => [$start->copy()->subWeek(), $start->copy()->addWeek()],
            'year' => [$start->copy()->subYear(), $start->copy()->addYear()],
            default => [$start->copy()->subMonth(), $start->copy()->addMonth()],
        };

        $transactions = $user->transactions()
            ->with('category:id,name,color')
            ->whereBetween('transaction_date', [$start, $end])
            ->get();

        $expenses = $transactions->where('type', 'expense');
        $incomes = $transactions->where('type', 'income');

        $base = fn ($t) => $t->base_amount;

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
            'period' => [
                'date' => $start->format('Y-m-d'),
                'prev' => $prev->format('Y-m-d'),
                'next' => $next->format('Y-m-d'),
                'isCurrent' => $end->gte(now()),
            ],
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
