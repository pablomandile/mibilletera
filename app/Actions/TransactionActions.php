<?php

namespace App\Actions;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Altas, cambios y bajas de movimientos. La usan el controlador de siempre y la
 * sincronización de cambios hechos sin conexión (SyncController), así las
 * reglas viven en un solo lugar.
 */
class TransactionActions
{
    public function create(User $user, array $input, ?UploadedFile $photo = null): Transaction
    {
        if (($input['type'] ?? null) === 'transfer') {
            $data = $this->validateTransfer($input, $user);
            [$from, $to] = $this->transferAccounts($user, $data);

            return $user->transactions()->create($this->transferAttributes($data, $from, $to));
        }

        $data = $this->validateExpenseIncome(['photo' => $photo] + $input, $user);
        $account = Account::where('user_id', $user->id)->findOrFail($data['account_id']);

        $attributes = $this->expenseIncomeAttributes($data, $account);
        $attributes['photo_path'] = $photo
            ? $photo->store('transactions/'.$user->id, 'public')
            : null;

        return $user->transactions()->create($attributes);
    }

    public function update(User $user, Transaction $transaction, array $input, ?UploadedFile $photo = null): Transaction
    {
        if (($input['type'] ?? null) === 'transfer') {
            $data = $this->validateTransfer($input, $user);
            [$from, $to] = $this->transferAccounts($user, $data);

            // Si venía con foto (era gasto/ingreso), se descarta al pasar a transferencia.
            $this->deletePhoto($transaction);

            $transaction->update($this->transferAttributes($data, $from, $to));

            return $transaction;
        }

        $data = $this->validateExpenseIncome(['photo' => $photo] + $input, $user);
        $account = Account::where('user_id', $user->id)->findOrFail($data['account_id']);

        $attributes = $this->expenseIncomeAttributes($data, $account);

        if ($photo) {
            $this->deletePhoto($transaction);
            $attributes['photo_path'] = $photo->store('transactions/'.$user->id, 'public');
        }
        // Si no viene foto nueva, se conserva la existente (no se toca photo_path).

        $transaction->update($attributes);

        return $transaction;
    }

    public function delete(User $user, Transaction $transaction): void
    {
        $this->deletePhoto($transaction);
        $transaction->delete();
    }

    private function validateExpenseIncome(array $input, User $user): array
    {
        return Validator::make($input, [
            'type' => ['required', Rule::in(['expense', 'income'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('user_id', $user->id)],
            'category_id' => ['required', Rule::exists('categories', 'id')->where('user_id', $user->id)],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ])->validate();
    }

    private function validateTransfer(array $input, User $user): array
    {
        return Validator::make($input, [
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('user_id', $user->id)],
            'to_account_id' => ['required', 'different:account_id', Rule::exists('accounts', 'id')->where('user_id', $user->id)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transfer_amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
        ])->validate();
    }

    private function transferAccounts(User $user, array $data): array
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
