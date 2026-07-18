<?php

namespace App\Actions;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Support\DefaultCategories;

/**
 * Crea los datos iniciales de un usuario nuevo:
 * una cuenta por defecto y el set de categorías de gasto/ingreso.
 */
class SeedDefaultUserData
{
    public function __invoke(User $user): void
    {
        // No duplicar si ya tiene datos (p. ej. re-ejecución del observer).
        if ($user->categories()->exists()) {
            return;
        }

        $currency = $user->default_currency ?: 'ARS';

        $user->accounts()->create([
            'name' => 'Efectivo',
            'type' => 'cash',
            'currency_code' => $currency,
            'initial_balance' => 0,
            'color' => '#22c55e',
            'icon' => 'cash',
            'sort_order' => 0,
        ]);

        $this->seedCategories($user, 'expense', DefaultCategories::expense());
        $this->seedCategories($user, 'income', DefaultCategories::income());
    }

    private function seedCategories(User $user, string $type, array $items): void
    {
        $rows = [];
        $now = now();

        foreach ($items as $i => $item) {
            $rows[] = [
                'user_id' => $user->id,
                'parent_id' => null,
                'type' => $type,
                'name' => $item['name'],
                'icon_type' => 'preset',
                'icon_value' => $item['icon'],
                'color' => $item['color'],
                'sort_order' => $i,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Category::insert($rows);
    }
}
