<?php

namespace App\Actions;

use App\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class ReminderActions
{
    public function create(User $user, array $input): Reminder
    {
        return $user->reminders()->create($this->validate($input));
    }

    public function update(User $user, Reminder $reminder, array $input): Reminder
    {
        $reminder->update($this->validate($input));

        return $reminder;
    }

    public function delete(User $user, Reminder $reminder): void
    {
        $reminder->delete();
    }

    private function validate(array $input): array
    {
        return Validator::make($input, [
            'title' => ['required', 'string', 'max:80'],
            'time' => ['required', 'date_format:H:i'],
            'days' => ['nullable', 'array'],
            'days.*' => ['integer', 'between:0,6'],
            'enabled' => ['boolean'],
        ])->validate();
    }
}
