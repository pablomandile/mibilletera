<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PreferencesController extends Controller
{
    public function update(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $data = $request->validate([
            'default_currency' => ['sometimes', 'required', Rule::exists('currencies', 'code')],
            'theme' => ['sometimes', 'required', Rule::in(['dark', 'light'])],
        ]);

        $user->fill($data)->save();

        return back()->with('success', 'Preferencias actualizadas.');
    }
}
