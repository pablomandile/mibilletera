<?php

namespace App\Actions;

use App\Models\Supercategory;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SupercategoryActions
{
    public function create(User $user, array $input): Supercategory
    {
        $data = $this->validate($user, $input);

        $supercategory = $user->supercategories()->create([
            'name' => $data['name'],
            'color' => $data['color'] ?? '#6366f1',
            'sort_order' => (int) $user->supercategories()->max('sort_order') + 1,
        ]);

        $this->assign($user, $supercategory, $data['category_ids'] ?? []);

        return $supercategory;
    }

    public function update(User $user, Supercategory $supercategory, array $input): Supercategory
    {
        $data = $this->validate($user, $input);

        $supercategory->update([
            'name' => $data['name'],
            'color' => $data['color'] ?? $supercategory->color,
        ]);

        $this->assign($user, $supercategory, $data['category_ids'] ?? []);

        return $supercategory;
    }

    public function delete(User $user, Supercategory $supercategory): void
    {
        // La FK ya lo hace (nullOnDelete), pero no todas las bases la respetan igual.
        $user->categories()->where('supercategory_id', $supercategory->id)->update(['supercategory_id' => null]);

        $supercategory->delete();
    }

    /**
     * Deja en la supercategoría exactamente esas categorías. Una categoría que
     * estaba en otra se muda: pertenece como mucho a una.
     */
    private function assign(User $user, Supercategory $supercategory, array $categoryIds): void
    {
        $user->categories()
            ->where('supercategory_id', $supercategory->id)
            ->whereNotIn('id', $categoryIds)
            ->update(['supercategory_id' => null]);

        if ($categoryIds) {
            $user->categories()->whereIn('id', $categoryIds)->update(['supercategory_id' => $supercategory->id]);
        }
    }

    private function validate(User $user, array $input): array
    {
        return Validator::make($input, [
            'name' => ['required', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:20'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => [
                'integer',
                Rule::exists('categories', 'id')
                    ->where('user_id', $user->id)
                    ->where('type', 'expense')
                    ->whereNull('parent_id'),
            ],
        ], [
            'category_ids.*.exists' => 'Una de las categorías elegidas ya no existe.',
        ])->validate();
    }
}
