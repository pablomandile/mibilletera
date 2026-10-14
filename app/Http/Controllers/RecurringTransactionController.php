<?php

namespace App\Http\Controllers;

use App\Actions\RecurringTransactionActions;
use App\Models\Currency;
use App\Models\RecurringTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RecurringTransactionController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $items = $user->recurringTransactions()
            ->with(['category:id,name,icon_type,icon_value,color', 'account:id,name'])
            ->orderByDesc('is_active')
            ->orderBy('next_run_date')
            ->get()
            ->map(fn (RecurringTransaction $r) => [
                'id' => $r->id,
                'type' => $r->type,
                'amount' => (float) $r->amount,
                'currency_code' => $r->currency_code,
                'note' => $r->note,
                'frequency' => $r->frequency,
                'interval' => $r->interval,
                'next_run_date' => $r->next_run_date->format('Y-m-d'),
                'end_date' => $r->end_date?->format('Y-m-d'),
                'is_active' => $r->is_active,
                'category' => $r->category ? [
                    'name' => $r->category->name,
                    'icon_type' => $r->category->icon_type,
                    'icon_value' => $r->category->icon_value,
                    'icon_url' => $r->category->icon_url,
                    'color' => $r->category->color,
                ] : null,
                'account' => $r->account?->name,
                'account_id' => $r->account_id,
                'category_id' => $r->category_id,
            ]);

        return Inertia::render('Settings/Recurring', [
            'items' => $items,
            'accounts' => $user->accounts()->where('is_archived', false)->get(['id', 'name', 'currency_code']),
            'categories' => $user->categories()->whereNull('parent_id')->orderBy('sort_order')->get(['id', 'type', 'name', 'icon_type', 'icon_value', 'color']),
            'symbols' => Currency::pluck('symbol', 'code'),
        ]);
    }

    public function store(Request $request, RecurringTransactionActions $actions)
    {
        $actions->create(Auth::user(), $request->all());

        return back()->with('success', 'Recurrente creada.');
    }

    public function update(Request $request, RecurringTransaction $recurring, RecurringTransactionActions $actions)
    {
        abort_unless($recurring->user_id === Auth::id(), 403);

        $actions->update(Auth::user(), $recurring, $request->all());

        return back()->with('success', 'Recurrente actualizada.');
    }

    public function destroy(RecurringTransaction $recurring, RecurringTransactionActions $actions)
    {
        abort_unless($recurring->user_id === Auth::id(), 403);

        $actions->delete(Auth::user(), $recurring);

        return back()->with('success', 'Recurrente eliminada.');
    }
}
