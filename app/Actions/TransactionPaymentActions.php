<?php

namespace App\Actions;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/**
 * Marcar un gasto fijo como pagado o impago. Va aparte de TransactionActions
 * porque no es una edición: no toca el monto ni la serie, y por eso tampoco
 * rehace las repeticiones adelantadas.
 */
class TransactionPaymentActions
{
    public function update(User $user, Transaction $transaction, array $input): Transaction
    {
        $data = Validator::make($input, [
            'is_paid' => ['required', 'boolean'],
        ])->validate();

        $transaction->update(['is_paid' => (bool) $data['is_paid']]);

        return $transaction;
    }
}
