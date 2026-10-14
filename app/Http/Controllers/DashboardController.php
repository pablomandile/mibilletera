<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $month = $request->query('month');
        $date = $month
            ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
            : now()->startOfMonth();

        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();

        // Filtro del listado (los totales siguen siendo los del mes completo).
        $filter = $request->query('type');
        if (! in_array($filter, ['income', 'expense', 'transfer'], true)) {
            $filter = null;
        }

        $transactions = $user->transactions()
            ->with([
                'category:id,name,icon_type,icon_value,color',
                'account:id,name',
                'toAccount:id,name',
            ])
            ->whereBetween('transaction_date', [$start, $end])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        // Totales convertidos a la moneda base (amount * exchange_rate).
        $income = (float) $transactions->where('type', 'income')->sum(fn ($t) => $t->base_amount);
        $expense = (float) $transactions->where('type', 'expense')->sum(fn ($t) => $t->base_amount);

        // Subtotales del separador de cada día. Se calculan sobre la colección sin
        // filtrar: igual que los totales del mes, el filtro del listado no los toca.
        $dayTotals = $transactions
            ->groupBy(fn ($t) => $t->transaction_date->format('Y-m-d'))
            ->map(fn ($ofDay) => [
                'income' => (float) $ofDay->where('type', 'income')->sum(fn ($t) => $t->base_amount),
                'expense' => (float) $ofDay->where('type', 'expense')->sum(fn ($t) => $t->base_amount),
            ]);

        $symbols = Currency::pluck('symbol', 'code');

        $visible = $filter
            ? $transactions->where('type', $filter)->values()
            : $transactions;

        return Inertia::render('Dashboard', [
            'transactions' => $visible->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => (float) $t->amount,
                'currency_code' => $t->currency_code,
                'note' => $t->note,
                'date' => $t->transaction_date->format('Y-m-d'),
                'category' => $t->category ? [
                    'name' => $t->category->name,
                    'icon_type' => $t->category->icon_type,
                    'icon_value' => $t->category->icon_value,
                    'icon_url' => $t->category->icon_url,
                    'color' => $t->category->color,
                ] : null,
                'from_account' => $t->account?->name,
                'to_account' => $t->toAccount?->name,
                'transfer_amount' => $t->transfer_amount !== null ? (float) $t->transfer_amount : null,
                'transfer_currency' => $t->transfer_currency,
                // Para poder abrir la edición sin conexión, armada desde esta fila.
                'account_id' => $t->account_id,
                'to_account_id' => $t->to_account_id,
                'category_id' => $t->category_id,
                'exchange_rate' => (float) $t->exchange_rate,
                'photo_url' => $t->photo_url,
            ]),
            'period' => [
                'month' => $date->format('Y-m'),
                'label' => ucfirst($date->translatedFormat('F Y')),
                'prev' => $date->copy()->subMonth()->format('Y-m'),
                'next' => $date->copy()->addMonth()->format('Y-m'),
                'isCurrent' => $date->isSameMonth(now()),
            ],
            'totals' => [
                'income' => $income,
                'expense' => $expense,
                'balance' => $income - $expense,
            ],
            'dayTotals' => $dayTotals,
            'filter' => $filter,
            'baseSymbol' => $symbols[$user->default_currency] ?? '$',
            'symbols' => $symbols,
        ]);
    }
}
