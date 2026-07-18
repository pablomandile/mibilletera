<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'role', 'avatar', 'created_at'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'avatar' => $u->avatar,
                'created_at' => $u->created_at->translatedFormat('d M Y'),
                'is_self' => $u->id === Auth::id(),
            ]);

        return Inertia::render('Admin/Users', [
            'users' => $users,
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_USER])],
        ]);

        // Evita que un admin se quite a sí mismo el rol (riesgo de quedar sin acceso).
        if ($user->id === Auth::id() && $data['role'] !== User::ROLE_ADMIN) {
            return back()->withErrors(['role' => 'No podés quitarte el rol de administrador a vos mismo.']);
        }

        $user->role = $data['role'];
        $user->save();

        return back()->with('success', 'Rol actualizado.');
    }
}
