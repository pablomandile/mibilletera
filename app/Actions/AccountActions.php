<?php

namespace App\Actions;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountActions
{
    public function create(User $user, array $input): Account
    {
        $data = $this->validate($input);
        $data['sort_order'] = (int) $user->accounts()->max('sort_order') + 1;

        return $user->accounts()->create($data);
    }

    public function update(User $user, Account $account, array $input): Account
    {
        $account->update($this->validate($input));

        return $account;
    }

    public function delete(User $user, Account $account): void
    {
        if ($account->transactions()->exists()) {
            throw ValidationException::withMessages(['account' => 'No se puede eliminar una cuenta con movimientos. Archivala en su lugar.']);
        }

        if ($user->accounts()->where('is_archived', false)->count() <= 1) {
            throw ValidationException::withMessages(['account' => 'Debe existir al menos una cuenta activa.']);
        }

        $account->delete();
    }

    private function validate(array $input): array
    {
        return Validator::make($input, [
            'name' => ['required', 'string', 'max:60'],
            'type' => ['required', Rule::in(['cash', 'bank', 'card', 'other'])],
            'currency_code' => ['required', Rule::exists('currencies', 'code')],
            'initial_balance' => ['required', 'numeric'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:40'],
            'is_archived' => ['boolean'],
        ])->validate();
    }
}
