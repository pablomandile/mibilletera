<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PreferencesActions
{
    public function update(User $user, array $input): User
    {
        $data = Validator::make($input, [
            'default_currency' => ['sometimes', 'required', Rule::exists('currencies', 'code')],
            'theme' => ['sometimes', 'required', Rule::in(['dark', 'light'])],
        ])->validate();

        $user->fill($data)->save();

        return $user;
    }
}
