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
        $income = (float) $transactions->where('type', 'income')->sum(fn ($t) => $t->amount * $t->exchange_rate);
        $expense = (float) $transactions->where('type', 'expense')->sum(fn ($t) => $t->amount * $t->exchange_rate);

        $symbols = Currency::pluck('symbol', 'code');

        return Inertia::render('Dashboard', [
            'transactions' => $transactions->map(fn ($t) => [
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
            'baseSymbol' => $symbols[$user->default_currency] ?? '$',
            'symbols' => $symbols,
        ]);
    }
}
