<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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

    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $data = $this->validateAccount($request);

        $data['sort_order'] = (int) $user->accounts()->max('sort_order') + 1;
        $user->accounts()->create($data);

        return back()->with('success', 'Cuenta creada.');
    }

    public function update(Request $request, Account $account)
    {
        abort_unless($account->user_id === Auth::id(), 403);

        $account->update($this->validateAccount($request));

        return back()->with('success', 'Cuenta actualizada.');
    }

    public function destroy(Account $account)
    {
        abort_unless($account->user_id === Auth::id(), 403);

        if ($account->transactions()->exists()) {
            return back()->withErrors(['account' => 'No se puede eliminar una cuenta con movimientos. Archivala en su lugar.']);
        }

        if (Auth::user()->accounts()->where('is_archived', false)->count() <= 1) {
            return back()->withErrors(['account' => 'Debe existir al menos una cuenta activa.']);
        }

        $account->delete();

        return back()->with('success', 'Cuenta eliminada.');
    }

    private function validateAccount(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'type' => ['required', Rule::in(['cash', 'bank', 'card', 'other'])],
            'currency_code' => ['required', Rule::exists('currencies', 'code')],
            'initial_balance' => ['required', 'numeric'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:40'],
            'is_archived' => ['boolean'],
        ]);
    }
}
