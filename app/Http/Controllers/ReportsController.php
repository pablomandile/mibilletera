<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $month = $request->query('month');
        $date = $month
            ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
            : now()->startOfMonth();

        $base = fn ($t) => $t->amount * $t->exchange_rate;

        $current = $this->periodTotals($user, $date, $base);
        $previous = $this->periodTotals($user, $date->copy()->subMonth(), $base);

        return Inertia::render('Reports/Index', [
            'period' => [
                'month' => $date->format('Y-m'),
                'label' => ucfirst($date->translatedFormat('F Y')),
                'prev' => $date->copy()->subMonth()->format('Y-m'),
                'next' => $date->copy()->addMonth()->format('Y-m'),
                'isCurrent' => $date->isSameMonth(now()),
            ],
            'totals' => [
                'income' => $current['income'],
                'expense' => $current['expense'],
                'balance' => $current['income'] - $current['expense'],
                'prevIncome' => $previous['income'],
                'prevExpense' => $previous['expense'],
            ],
            'expenseByCategory' => $current['expenseByCategory'],
            'incomeByCategory' => $current['incomeByCategory'],
            'baseSymbol' => Currency::find($user->default_currency)?->symbol ?? '$',
        ]);
    }

    private function periodTotals($user, Carbon $date, callable $base): array
    {
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();

        $txns = $user->transactions()
            ->with('category:id,name,color')
            ->whereBetween('transaction_date', [$start, $end])
            ->get();

        $expenses = $txns->where('type', 'expense');
        $incomes = $txns->where('type', 'income');

        return [
            'income' => (float) $incomes->sum($base),
            'expense' => (float) $expenses->sum($base),
            'expenseByCategory' => $this->breakdown($expenses, $base),
            'incomeByCategory' => $this->breakdown($incomes, $base),
        ];
    }

    private function breakdown($collection, callable $base): array
    {
        $total = (float) $collection->sum($base);

        return $collection
            ->groupBy('category_id')
            ->map(function ($group) use ($base, $total) {
                $cat = $group->first()->category;
                $sum = (float) $group->sum($base);

                return [
                    'name' => $cat?->name ?? 'Sin categoría',
                    'color' => $cat?->color ?? '#9ca3af',
                    'total' => $sum,
                    'pct' => $total > 0 ? round($sum / $total * 1000) / 10 : 0,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }
}
