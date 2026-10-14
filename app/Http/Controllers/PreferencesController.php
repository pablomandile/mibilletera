<?php

namespace App\Http\Controllers;

use App\Actions\PreferencesActions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PreferencesController extends Controller
{
    public function update(Request $request, PreferencesActions $actions)
    {
        $actions->update(Auth::user(), $request->all());

        return back()->with('success', 'Preferencias actualizadas.');
    }
}
