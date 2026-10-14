<?php

namespace App\Http\Controllers;

use App\Actions\AccountActions;
use App\Models\Account;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AccountController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $accounts = $user->accounts()
            ->orderBy('is_archived')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type,
                'currency_code' => $a->currency_code,
                'initial_balance' => (float) $a->initial_balance,
                'balance' => $a->currentBalance(),
                'color' => $a->color,
                'icon' => $a->icon,
                'is_archived' => $a->is_archived,
            ]);

        return Inertia::render('Settings/Accounts', [
            'accounts' => $accounts,
            'currencies' => Currency::orderBy('code')->get(['code', 'name', 'symbol']),
        ]);
    }

    public function store(Request $request, AccountActions $actions)
    {
        $actions->create(Auth::user(), $request->all());

        return back()->with('success', 'Cuenta creada.');
    }

    public function update(Request $request, Account $account, AccountActions $actions)
    {
        abort_unless($account->user_id === Auth::id(), 403);

        $actions->update(Auth::user(), $account, $request->all());

        return back()->with('success', 'Cuenta actualizada.');
    }

    public function destroy(Account $account, AccountActions $actions)
    {
        abort_unless($account->user_id === Auth::id(), 403);

        $actions->delete(Auth::user(), $account);

        return back()->with('success', 'Cuenta eliminada.');
    }
}
