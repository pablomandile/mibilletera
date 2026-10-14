<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CategoryActions
{
    public function create(User $user, array $input, ?UploadedFile $image = null): Category
    {
        $data = $this->validate($user, ['image' => $image] + $input);
        $iconValue = $this->resolveIcon($user, $data, $image);

        $sortOrder = (int) $user->categories()->where('type', $data['type'])->max('sort_order') + 1;

        return $user->categories()->create([
            'parent_id' => $data['parent_id'] ?? null,
            'type' => $data['type'],
            'name' => $data['name'],
            'icon_type' => $data['icon_type'],
            'icon_value' => $iconValue,
            'color' => $data['color'] ?? '#f59e0b',
            'sort_order' => $sortOrder,
            'is_system' => false,
        ]);
    }

    public function update(User $user, Category $category, array $input, ?UploadedFile $image = null): Category
    {
        $data = $this->validate($user, ['image' => $image] + $input, $category);
        $iconValue = $this->resolveIcon($user, $data, $image, $category);

        $category->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'icon_type' => $data['icon_type'],
            'icon_value' => $iconValue,
            'color' => $data['color'] ?? $category->color,
            'parent_id' => $data['parent_id'] ?? null,
        ]);

        return $category;
    }

    public function delete(User $user, Category $category): void
    {
        if ($category->icon_type === 'image') {
            Storage::disk('public')->delete($category->icon_value);
        }

        $category->delete();
    }

    private function validate(User $user, array $input, ?Category $category = null): array
    {
        return Validator::make($input, [
            'type' => ['required', Rule::in(['expense', 'income'])],
            'name' => ['required', 'string', 'max:60'],
            'icon_type' => ['required', Rule::in(['preset', 'image'])],
            'icon_value' => ['required_if:icon_type,preset', 'nullable', 'string', 'max:120'],
            'image' => $category
                ? ['nullable', 'image', 'max:5120']
                : ['nullable', 'required_if:icon_type,image', 'image', 'max:5120'],
            'color' => ['nullable', 'string', 'max:20'],
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('user_id', $user->id),
            ],
        ])->validate();
    }

    /**
     * Devuelve el valor del ícono: clave de preset o la ruta de la imagen subida.
     */
    private function resolveIcon(User $user, array $data, ?UploadedFile $image, ?Category $category = null): string
    {
        if ($data['icon_type'] === 'image' && $image) {
            // Elimina la imagen anterior si se reemplaza.
            if ($category && $category->icon_type === 'image') {
                Storage::disk('public')->delete($category->icon_value);
            }

            return $image->store('categories/'.$user->id, 'public');
        }

        if ($data['icon_type'] === 'preset') {
            return $data['icon_value'];
        }

        // icon_type image sin archivo nuevo (edición): conserva el actual.
        return $category?->icon_value ?? 'category';
    }
}
