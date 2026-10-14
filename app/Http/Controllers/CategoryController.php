<?php

namespace App\Http\Controllers;

use App\Actions\CategoryActions;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function store(Request $request, CategoryActions $actions)
    {
        $category = $actions->create(Auth::user(), $request->all(), $request->file('image'));

        if ($request->header('X-Inertia')) {
            return back()
                ->with('success', 'Categoría creada.')
                ->with('created_category_id', $category->id);
        }

        return redirect()->route('categories.index');
    }

    public function update(Request $request, Category $category, CategoryActions $actions)
    {
        abort_unless($category->user_id === Auth::id(), 403);

        $actions->update(Auth::user(), $category, $request->all(), $request->file('image'));

        return back()->with('success', 'Categoría actualizada.');
    }

    public function destroy(Category $category, CategoryActions $actions)
    {
        abort_unless($category->user_id === Auth::id(), 403);

        $actions->delete(Auth::user(), $category);

        return back()->with('success', 'Categoría eliminada.');
    }
}
