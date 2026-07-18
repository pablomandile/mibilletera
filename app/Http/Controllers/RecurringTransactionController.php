<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Currency;
use App\Models\RecurringTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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
            ]);

        return Inertia::render('Settings/Recurring', [
            'items' => $items,
            'accounts' => $user->accounts()->where('is_archived', false)->get(['id', 'name', 'currency_code']),
            'categories' => $user->categories()->whereNull('parent_id')->orderBy('sort_order')->get(['id', 'type', 'name', 'icon_type', 'icon_value', 'color']),
            'symbols' => Currency::pluck('symbol', 'code'),
        ]);
    }

    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $data = $this->validateData($request, $user);
        $account = Account::where('user_id', $user->id)->findOrFail($data['account_id']);

        $user->recurringTransactions()->create([
            ...$data,
            'currency_code' => $account->currency_code,
            'exchange_rate' => 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Recurrente creada.');
    }

    public function update(Request $request, RecurringTransaction $recurring)
    {
        abort_unless($recurring->user_id === Auth::id(), 403);

        $data = $this->validateData($request, Auth::user());
        $account = Account::where('user_id', Auth::id())->findOrFail($data['account_id']);

        $recurring->update([
            ...$data,
            'currency_code' => $account->currency_code,
            'is_active' => $request->boolean('is_active', $recurring->is_active),
        ]);

        return back()->with('success', 'Recurrente actualizada.');
    }

    public function destroy(RecurringTransaction $recurring)
    {
        abort_unless($recurring->user_id === Auth::id(), 403);

        $recurring->delete();

        return back()->with('success', 'Recurrente eliminada.');
    }

    private function validateData(Request $request, $user): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['expense', 'income'])],
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('user_id', $user->id)],
            'category_id' => ['required', Rule::exists('categories', 'id')->where('user_id', $user->id)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'interval' => ['required', 'integer', 'min:1', 'max:365'],
            'next_run_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:next_run_date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
