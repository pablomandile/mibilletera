<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($request->input('type') === 'transfer') {
            $data = $this->validateTransfer($request, $user);
            [$from, $to] = $this->transferAccounts($user, $data);

            $user->transactions()->create($this->transferAttributes($data, $from, $to));

            return redirect()->route('dashboard')->with('success', 'Transferencia guardada.');
        }

        $data = $this->validateExpenseIncome($request, $user);
        $account = Account::where('user_id', $user->id)->findOrFail($data['account_id']);

        $attributes = $this->expenseIncomeAttributes($data, $account);
        $attributes['photo_path'] = $request->hasFile('photo')
            ? $request->file('photo')->store('transactions/'.$user->id, 'public')
            : null;

        $user->transactions()->create($attributes);

        return redirect()->route('dashboard')->with('success', 'Transacción guardada.');
    }

    public function update(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->user_id === Auth::id(), 403);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($request->input('type') === 'transfer') {
            $data = $this->validateTransfer($request, $user);
            [$from, $to] = $this->transferAccounts($user, $data);

            // Si venía con foto (era gasto/ingreso), se descarta al pasar a transferencia.
            $this->deletePhoto($transaction);

            $transaction->update($this->transferAttributes($data, $from, $to));

            return redirect()->route('dashboard')->with('success', 'Transferencia actualizada.');
        }

        $data = $this->validateExpenseIncome($request, $user);
        $account = Account::where('user_id', $user->id)->findOrFail($data['account_id']);

        $attributes = $this->expenseIncomeAttributes($data, $account);

        if ($request->hasFile('photo')) {
            $this->deletePhoto($transaction);
            $attributes['photo_path'] = $request->file('photo')->store('transactions/'.$user->id, 'public');
        }
        // Si no viene foto nueva, se conserva la existente (no se toca photo_path).

        $transaction->update($attributes);

        return redirect()->route('dashboard')->with('success', 'Transacción actualizada.');
    }

    public function destroy(Transaction $transaction)
    {
        abort_unless($transaction->user_id === Auth::id(), 403);

        $this->deletePhoto($transaction);
        $transaction->delete();

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

    private function validateExpenseIncome(Request $request, $user): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['expense', 'income'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('user_id', $user->id)],
            'category_id' => ['required', Rule::exists('categories', 'id')->where('user_id', $user->id)],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);
    }

    private function validateTransfer(Request $request, $user): array
    {
        return $request->validate([
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('user_id', $user->id)],
            'to_account_id' => ['required', 'different:account_id', Rule::exists('accounts', 'id')->where('user_id', $user->id)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transfer_amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
        ]);
    }

    private function transferAccounts($user, array $data): array
    {
        return [
            Account::where('user_id', $user->id)->findOrFail($data['account_id']),
            Account::where('user_id', $user->id)->findOrFail($data['to_account_id']),
        ];
    }

    /**
     * Atributos comunes de un gasto/ingreso (sin la foto).
     */
    private function expenseIncomeAttributes(array $data, Account $account): array
    {
        return [
            'account_id' => $account->id,
            'category_id' => $data['category_id'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'currency_code' => $account->currency_code,
            'exchange_rate' => $data['exchange_rate'] ?? 1,
            'note' => $data['note'] ?? null,
            'transaction_date' => $data['transaction_date'],
            'to_account_id' => null,
            'transfer_amount' => null,
            'transfer_currency' => null,
        ];
    }

    private function transferAttributes(array $data, Account $from, Account $to): array
    {
        return [
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
            'photo_path' => null,
        ];
    }

    private function deletePhoto(Transaction $transaction): void
    {
        if ($transaction->photo_path) {
            Storage::disk('public')->delete($transaction->photo_path);
        }
    }
}
