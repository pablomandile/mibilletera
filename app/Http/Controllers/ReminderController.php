<?php

namespace App\Http\Controllers;

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

    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $user->reminders()->create($this->validateData($request));

        return back()->with('success', 'Recordatorio creado.');
    }

    public function update(Request $request, Reminder $reminder)
    {
        abort_unless($reminder->user_id === Auth::id(), 403);

        $reminder->update($this->validateData($request));

        return back()->with('success', 'Recordatorio actualizado.');
    }

    public function destroy(Reminder $reminder)
    {
        abort_unless($reminder->user_id === Auth::id(), 403);

        $reminder->delete();

        return back()->with('success', 'Recordatorio eliminado.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'time' => ['required', 'date_format:H:i'],
            'days' => ['nullable', 'array'],
            'days.*' => ['integer', 'between:0,6'],
            'enabled' => ['boolean'],
        ]);
    }
}
