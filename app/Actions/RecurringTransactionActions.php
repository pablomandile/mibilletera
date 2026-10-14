<?php

namespace App\Actions;

use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RecurringTransactionActions
{
    public function create(User $user, array $input): RecurringTransaction
    {
        $data = $this->validate($user, $input);
        $account = Account::where('user_id', $user->id)->findOrFail($data['account_id']);

        return $user->recurringTransactions()->create([
            ...$data,
            'currency_code' => $account->currency_code,
            'exchange_rate' => 1,
            'is_active' => true,
        ]);
    }

    public function update(User $user, RecurringTransaction $recurring, array $input): RecurringTransaction
    {
        $data = $this->validate($user, $input);
        $account = Account::where('user_id', $user->id)->findOrFail($data['account_id']);

        $isActive = array_key_exists('is_active', $input)
            ? filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN)
            : $recurring->is_active;

        $recurring->update([
            ...$data,
            'currency_code' => $account->currency_code,
            'is_active' => $isActive,
        ]);

        return $recurring;
    }

    public function delete(User $user, RecurringTransaction $recurring): void
    {
        $recurring->delete();
    }

    private function validate(User $user, array $input): array
    {
        return Validator::make($input, [
            'type' => ['required', Rule::in(['expense', 'income'])],
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('user_id', $user->id)],
            'category_id' => ['required', Rule::exists('categories', 'id')->where('user_id', $user->id)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'interval' => ['required', 'integer', 'min:1', 'max:365'],
            'next_run_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:next_run_date'],
            'note' => ['nullable', 'string', 'max:255'],
        ])->validate();
    }
}
