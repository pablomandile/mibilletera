<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Los números de un mes que muestran Informes y Gráficos, y que van al PDF.
 * Todo en la moneda base del usuario (`base_amount`).
 */
class MonthlyReport
{
    /**
     * El mes pedido como `YYYY-MM`. Sin mes, mal formado o futuro: el mes actual.
     */
    public static function monthFromQuery(mixed $month): Carbon
    {
        if (is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $date = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
            if ($date->lte(now())) {
                return $date;
            }
        }

        return now()->startOfMonth();
    }

    public function build(User $user, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $current = $this->periodTotals($user, $start);
        $previous = $this->periodTotals($user, $start->copy()->subMonth());

        $expenses = $current['expenses'];
        $incomes = $current['incomes'];
        $supercategories = $user->supercategories()->get(['id', 'name', 'color'])->keyBy('id');

        $days = [];
        for ($cursor = $start->copy(); $cursor <= $end; $cursor->addDay()) {
            $key = $cursor->format('Y-m-d');
            $days[] = [
                'day' => (int) $cursor->format('j'),
                'label' => $cursor->translatedFormat('D j'),
                'income' => (float) $incomes->filter(fn ($t) => $t->transaction_date->format('Y-m-d') === $key)->sum(self::base(...)),
                'expense' => (float) $expenses->filter(fn ($t) => $t->transaction_date->format('Y-m-d') === $key)->sum(self::base(...)),
            ];
        }

        return [
            'month' => $start->format('Y-m'),
            'label' => ucfirst($start->translatedFormat('F Y')),
            'totals' => [
                'income' => $current['income'],
                'expense' => $current['expense'],
                'balance' => $current['income'] - $current['expense'],
                'prevIncome' => $previous['income'],
                'prevExpense' => $previous['expense'],
                'incomeDelta' => self::delta($current['income'], $previous['income']),
                'expenseDelta' => self::delta($current['expense'], $previous['expense']),
            ],
            'fixed' => [
                ...$this->fixedExpenses($expenses),
                'prevTotal' => $previous['fixed'],
                'delta' => self::delta($current['fixed'], $previous['fixed']),
            ],
            'expenseByCategory' => $this->breakdown($expenses),
            'incomeByCategory' => $this->breakdown($incomes),
            'hasSupercategories' => $supercategories->isNotEmpty(),
            'bySupercategory' => $this->bySupercategory($expenses, $supercategories)->all(),
            'days' => $days,
            'symbol' => Currency::find($user->default_currency)?->symbol ?? '$',
        ];
    }

    /**
     * Gasto sumado por supercategoría. Lo que está en categorías sin agrupar va
     * junto en una porción gris. Sin supercategorías, vacío.
     */
    public function bySupercategory(Collection $expenses, Collection $supercategories): Collection
    {
        if ($supercategories->isEmpty()) {
            return collect();
        }

        $total = (float) $expenses->sum(self::base(...));

        return $expenses
            ->groupBy(fn ($t) => $supercategories->has($t->category?->supercategory_id) ? $t->category->supercategory_id : 0)
            ->map(function ($group, $id) use ($supercategories, $total) {
                $super = $supercategories->get($id);
                $sum = (float) $group->sum(self::base(...));

                return [
                    'name' => $super?->name ?? 'Sin supercategoría',
                    'color' => $super?->color ?? '#9ca3af',
                    'total' => $sum,
                    'pct' => $total > 0 ? round($sum / $total * 1000) / 10 : 0,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Los gastos fijos dentro de un conjunto de gastos: cuánto suman, qué parte
     * del gasto son, cuánto está pagado y cuánto falta, y uno por serie (en un
     * mes, cada fijo aparece una vez; en un año, sumado con la cantidad de meses).
     * Espera cargadas `category` y `recurring` (con id, kind, is_active y note).
     */
    public function fixedExpenses(Collection $expenses): array
    {
        $fixed = $expenses->filter(fn ($t) => $t->inFixedSeries());

        $all = round((float) $expenses->sum(self::base(...)), 2);
        $total = round((float) $fixed->sum(self::base(...)), 2);
        $pending = round((float) $fixed->where('is_paid', false)->sum(self::base(...)), 2);

        $items = $fixed
            ->groupBy('recurring_transaction_id')
            ->map(function ($group) {
                $last = $group->sortBy('transaction_date')->last();
                $category = $last->category;

                return [
                    'id' => $last->recurring_transaction_id,
                    // La nota de la serie es el nombre del fijo ("Alquiler"); la de cada
                    // repetición puede variar ("Alquiler de marzo").
                    'name' => $last->recurring->note ?: ($last->note ?: ($category?->name ?? 'Sin categoría')),
                    'category' => $category?->name ?? 'Sin categoría',
                    'color' => $category?->color ?? '#9ca3af',
                    'total' => round((float) $group->sum(self::base(...)), 2),
                    'pending' => round((float) $group->where('is_paid', false)->sum(self::base(...)), 2),
                    'count' => $group->count(),
                    'date' => $last->transaction_date->format('Y-m-d'),
                    'isPaid' => $group->every(fn ($t) => $t->is_paid),
                    'active' => (bool) $last->recurring->is_active,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'total' => $total,
            'variable' => max(round($all - $total, 2), 0.0),
            'paid' => round($total - $pending, 2),
            'pending' => $pending,
            'pct' => $all > 0 ? round($total / $all * 1000) / 10 : 0,
            'items' => $items,
        ];
    }

    private function periodTotals(User $user, Carbon $start): array
    {
        $txns = $user->transactions()
            ->with(['category:id,name,color,supercategory_id', 'recurring:id,kind,is_active,note'])
            ->whereBetween('transaction_date', [$start->copy()->startOfMonth(), $start->copy()->endOfMonth()])
            ->get();

        $expenses = $txns->where('type', 'expense');
        $incomes = $txns->where('type', 'income');

        return [
            'expenses' => $expenses,
            'incomes' => $incomes,
            'income' => (float) $incomes->sum(self::base(...)),
            'expense' => (float) $expenses->sum(self::base(...)),
            'fixed' => round((float) $expenses->filter(fn ($t) => $t->inFixedSeries())->sum(self::base(...)), 2),
        ];
    }

    private function breakdown(Collection $collection): array
    {
        $total = (float) $collection->sum(self::base(...));

        return $collection
            ->groupBy('category_id')
            ->map(function ($group) use ($total) {
                $cat = $group->first()->category;
                $sum = (float) $group->sum(self::base(...));

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

    private static function base($t): float
    {
        return (float) $t->base_amount;
    }

    private static function delta(float $current, float $prev): ?float
    {
        return $prev ? round(($current - $prev) / $prev * 1000) / 10 : null;
    }
}
