<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TransactionController extends Controller
{
    /**
     * Pantalla "Agregar" (calculadora): categorías, cuentas y monedas del usuario.
     */
    public function create()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $categories = $user->categories()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get(['id', 'type', 'name', 'icon_type', 'icon_value', 'color']);

        $accounts = $user->accounts()
            ->where('is_archived', false)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'type', 'currency_code', 'color', 'icon']);

        return Inertia::render('Transactions/Create', [
            'categories' => $categories,
            'accounts' => $accounts,
            'currencies' => Currency::orderBy('code')->get(['code', 'name', 'symbol']),
            'defaultCurrency' => $user->default_currency,
        ]);
    }

    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $type = $request->input('type');

        if ($type === 'transfer') {
            return $this->storeTransfer($request, $user);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(['expense', 'income'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('user_id', $user->id)],
            'category_id' => ['required', Rule::exists('categories', 'id')->where('user_id', $user->id)],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $account = Account::where('user_id', $user->id)->findOrFail($data['account_id']);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('transactions/'.$user->id, 'public');
        }

        $user->transactions()->create([
            'account_id' => $account->id,
            'category_id' => $data['category_id'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'currency_code' => $account->currency_code,
            'exchange_rate' => $data['exchange_rate'] ?? 1,
            'note' => $data['note'] ?? null,
            'photo_path' => $photoPath,
            'transaction_date' => $data['transaction_date'],
        ]);

        return redirect()->route('dashboard')->with('success', 'Transacción guardada.');
    }

    private function storeTransfer(Request $request, $user)
    {
        $data = $request->validate([
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('user_id', $user->id)],
            'to_account_id' => ['required', 'different:account_id', Rule::exists('accounts', 'id')->where('user_id', $user->id)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transfer_amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
        ]);

        $from = Account::where('user_id', $user->id)->findOrFail($data['account_id']);
        $to = Account::where('user_id', $user->id)->findOrFail($data['to_account_id']);

        $user->transactions()->create([
            'account_id' => $from->id,
            'to_account_id' => $to->id,
            'category_id' => null,
            'type' => 'transfer',
            'amount' => $data['amount'],
            'currency_code' => $from->currency_code,
            'exchange_rate' => 1,
            'transfer_amount' => $data['transfer_amount'],
            'transfer_currency' => $to->currency_code,
            'note' => $data['note'] ?? null,
            'transaction_date' => $data['transaction_date'],
        ]);

        return redirect()->route('dashboard')->with('success', 'Transferencia guardada.');
    }

    public function destroy(Transaction $transaction)
    {
        abort_unless($transaction->user_id === Auth::id(), 403);

        $transaction->delete();

        return back()->with('success', 'Transacción eliminada.');
    }
}
