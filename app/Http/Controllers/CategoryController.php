<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $categories = $user->categories()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Settings/Categories', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $data = $this->validateCategory($request);

        $iconValue = $this->resolveIcon($request, $data);

        $sortOrder = (int) $user->categories()->where('type', $data['type'])->max('sort_order') + 1;

        $category = $user->categories()->create([
            'parent_id' => $data['parent_id'] ?? null,
            'type' => $data['type'],
            'name' => $data['name'],
            'icon_type' => $data['icon_type'],
            'icon_value' => $iconValue,
            'color' => $data['color'] ?? '#f59e0b',
            'sort_order' => $sortOrder,
            'is_system' => false,
        ]);

        if ($request->header('X-Inertia')) {
            return back()
                ->with('success', 'Categoría creada.')
                ->with('created_category_id', $category->id);
        }

        return redirect()->route('categories.index');
    }

    public function update(Request $request, Category $category)
    {
        abort_unless($category->user_id === Auth::id(), 403);

        $data = $this->validateCategory($request, $category);
        $iconValue = $this->resolveIcon($request, $data, $category);

        $category->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'icon_type' => $data['icon_type'],
            'icon_value' => $iconValue,
            'color' => $data['color'] ?? $category->color,
            'parent_id' => $data['parent_id'] ?? null,
        ]);

        return back()->with('success', 'Categoría actualizada.');
    }

    public function destroy(Category $category)
    {
        abort_unless($category->user_id === Auth::id(), 403);

        if ($category->icon_type === 'image') {
            Storage::disk('public')->delete($category->icon_value);
        }

        $category->delete();

        return back()->with('success', 'Categoría eliminada.');
    }

    private function validateCategory(Request $request, ?Category $category = null): array
    {
        return $request->validate([
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
                Rule::exists('categories', 'id')->where('user_id', Auth::id()),
            ],
        ]);
    }

    /**
     * Devuelve el valor del ícono: clave de preset o la ruta de la imagen subida.
     */
    private function resolveIcon(Request $request, array $data, ?Category $category = null): string
    {
        if ($data['icon_type'] === 'image' && $request->hasFile('image')) {
            // Elimina la imagen anterior si se reemplaza.
            if ($category && $category->icon_type === 'image') {
                Storage::disk('public')->delete($category->icon_value);
            }

            return $request->file('image')->store('categories/'.Auth::id(), 'public');
        }

        if ($data['icon_type'] === 'preset') {
            return $data['icon_value'];
        }

        // icon_type image sin archivo nuevo (edición): conserva el actual.
        return $category?->icon_value ?? 'category';
    }
}
