<?php

namespace App\Http\Controllers;

use App\Actions\SupercategoryActions;
use App\Models\Supercategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SupercategoryController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $categories = $user->categories()
            ->where('type', 'expense')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'icon_type', 'icon_value', 'color', 'supercategory_id']);

        $supercategories = $user->supercategories()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'color' => $s->color,
                'category_ids' => $categories->where('supercategory_id', $s->id)->pluck('id')->values(),
            ]);

        return Inertia::render('Settings/Supercategories', [
            'supercategories' => $supercategories,
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'icon_type' => $c->icon_type,
                'icon_value' => $c->icon_value,
                'icon_url' => $c->icon_url,
                'color' => $c->color,
            ])->values(),
        ]);
    }

    public function store(Request $request, SupercategoryActions $actions)
    {
        $actions->create(Auth::user(), $request->all());

        return back()->with('success', 'Supercategoría creada.');
    }

    public function update(Request $request, Supercategory $supercategory, SupercategoryActions $actions)
    {
        abort_unless($supercategory->user_id === Auth::id(), 403);

        $actions->update(Auth::user(), $supercategory, $request->all());

        return back()->with('success', 'Supercategoría actualizada.');
    }

    public function destroy(Supercategory $supercategory, SupercategoryActions $actions)
    {
        abort_unless($supercategory->user_id === Auth::id(), 403);

        $actions->delete(Auth::user(), $supercategory);

        return back()->with('success', 'Supercategoría eliminada.');
    }
}
