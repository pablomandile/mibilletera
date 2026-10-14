<?php

namespace App\Http\Controllers;

use App\Actions\ReminderActions;
use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ReminderController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $reminders = $user->reminders()
            ->orderBy('time')
            ->get()
            ->map(fn (Reminder $r) => [
                'id' => $r->id,
                'title' => $r->title,
                'time' => substr((string) $r->time, 0, 5), // HH:MM
                'days' => $r->days ?? [],
                'enabled' => $r->enabled,
            ]);

        return Inertia::render('Settings/Reminders', [
            'reminders' => $reminders,
        ]);
    }

    public function store(Request $request, ReminderActions $actions)
    {
        $actions->create(Auth::user(), $request->all());

        return back()->with('success', 'Recordatorio creado.');
    }

    public function update(Request $request, Reminder $reminder, ReminderActions $actions)
    {
        abort_unless($reminder->user_id === Auth::id(), 403);

        $actions->update(Auth::user(), $reminder, $request->all());

        return back()->with('success', 'Recordatorio actualizado.');
    }

    public function destroy(Reminder $reminder, ReminderActions $actions)
    {
        abort_unless($reminder->user_id === Auth::id(), 403);

        $actions->delete(Auth::user(), $reminder);

        return back()->with('success', 'Recordatorio eliminado.');
    }
}
