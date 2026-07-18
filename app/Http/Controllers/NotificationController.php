<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Marca todas las alertas del usuario como leídas.
     */
    public function markRead()
    {
        Alert::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    public function destroy(Alert $alert)
    {
        abort_unless($alert->user_id === Auth::id(), 403);

        $alert->delete();

        return back();
    }
}
