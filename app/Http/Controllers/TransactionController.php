<?php

namespace App\Http\Controllers;

use App\Actions\TransactionActions;
use App\Models\Currency;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TransactionController extends Controller
{
    /**
     * Pantalla "Agregar" (calculadora).
     */
    public function create()
    {
        return Inertia::render('Transactions/Create', $this->formProps());
    }

    /**
     * Misma pantalla, precargada con una transacción existente (modo edición).
     */
    public function edit(Transaction $transaction)
    {
        abort_unless($transaction->user_id === Auth::id(), 403);

        return Inertia::render('Transactions/Create', [
            ...$this->formProps(),
            'transaction' => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => (float) $transaction->amount,
                'account_id' => $transaction->account_id,
                'category_id' => $transaction->category_id,
                'to_account_id' => $transaction->to_account_id,
                'exchange_rate' => (float) $transaction->exchange_rate,
                'transfer_amount' => $transaction->transfer_amount !== null ? (float) $transaction->transfer_amount : null,
                'note' => $transaction->note,
                'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
                'photo_url' => $transaction->photo_url,
                'currency_code' => $transaction->currency_code,
                'transfer_currency' => $transaction->transfer_currency,
            ],
        ]);
    }

    public function store(Request $request, TransactionActions $actions)
    {
        $transaction = $actions->create(Auth::user(), $request->all(), $request->file('photo'));

        return redirect()->route('dashboard')->with('success', $transaction->type === 'transfer' ? 'Transferencia guardada.' : 'Transacción guardada.');
    }

    public function update(Request $request, Transaction $transaction, TransactionActions $actions)
    {
        abort_unless($transaction->user_id === Auth::id(), 403);

        $actions->update(Auth::user(), $transaction, $request->all(), $request->file('photo'));

        return redirect()->route('dashboard')->with('success', $transaction->type === 'transfer' ? 'Transferencia actualizada.' : 'Transacción actualizada.');
    }

    public function destroy(Transaction $transaction, TransactionActions $actions)
    {
        abort_unless($transaction->user_id === Auth::id(), 403);

        $actions->delete(Auth::user(), $transaction);

        return back()->with('success', 'Transacción eliminada.');
    }

    // ----------------------------------------------------------------------

    private function formProps(): array
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return [
            'categories' => $user->categories()
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->get(['id', 'type', 'name', 'icon_type', 'icon_value', 'color']),
            'accounts' => $user->accounts()
                ->where('is_archived', false)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'type', 'currency_code', 'color', 'icon']),
            'currencies' => Currency::orderBy('code')->get(['code', 'name', 'symbol']),
            'defaultCurrency' => $user->default_currency,
        ];
    }
}
