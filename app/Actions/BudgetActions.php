<?php

namespace App\Actions;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BudgetActions
{
    /**
     * Crea o reemplaza el presupuesto de la categoría (o el global, sin categoría).
     */
    public function create(User $user, array $input): Budget
    {
        $data = Validator::make($input, [
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('user_id', $user->id)->where('type', 'expense'),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
        ])->validate();

        return $user->budgets()->updateOrCreate(
            ['category_id' => $data['category_id'] ?? null],
            ['amount' => $data['amount'], 'currency_code' => $user->default_currency ?: 'ARS', 'period' => 'monthly'],
        );
    }

    public function delete(User $user, Budget $budget): void
    {
        $budget->delete();
    }
}
