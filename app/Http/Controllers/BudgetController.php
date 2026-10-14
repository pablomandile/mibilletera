<?php

namespace App\Http\Controllers;

use App\Actions\BudgetActions;
use App\Models\Budget;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class BudgetController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        // Gasto del mes por categoría, convertido a moneda base.
        $expenses = $user->transactions()
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$start, $end])
            ->get(['category_id', 'amount', 'exchange_rate']);

        $spentByCat = $expenses
            ->groupBy('category_id')
            ->map(fn ($g) => (float) $g->sum(fn ($t) => $t->amount * $t->exchange_rate));
        $totalSpent = (float) $expenses->sum(fn ($t) => $t->amount * $t->exchange_rate);

        $budgets = $user->budgets()->get()->keyBy('category_id');

        $categories = $user->categories()
            ->where('type', 'expense')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'icon_type', 'icon_value', 'color'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'icon_type' => $c->icon_type,
                'icon_value' => $c->icon_value,
                'color' => $c->color,
                'budget' => isset($budgets[$c->id]) ? (float) $budgets[$c->id]->amount : null,
                'budget_id' => isset($budgets[$c->id]) ? $budgets[$c->id]->id : null,
                'spent' => $spentByCat[$c->id] ?? 0.0,
            ]);

        $globalBudget = $budgets->get(null);

        return Inertia::render('Settings/Budgets', [
            'categories' => $categories,
            'global' => [
                'id' => $globalBudget?->id,
                'amount' => $globalBudget ? (float) $globalBudget->amount : null,
                'spent' => $totalSpent,
            ],
            'baseSymbol' => Currency::find($user->default_currency)?->symbol ?? '$',
            'periodLabel' => ucfirst(now()->translatedFormat('F Y')),
        ]);
    }

    public function store(Request $request, BudgetActions $actions)
    {
        $actions->create(Auth::user(), $request->all());

        return back()->with('success', 'Presupuesto guardado.');
    }

    public function destroy(Budget $budget, BudgetActions $actions)
    {
        abort_unless($budget->user_id === Auth::id(), 403);

        $actions->delete(Auth::user(), $budget);

        return back()->with('success', 'Presupuesto eliminado.');
    }
}
