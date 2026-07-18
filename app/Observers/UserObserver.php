<?php

namespace App\Observers;

use App\Actions\SeedDefaultUserData;
use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        (new SeedDefaultUserData())($user);
    }
}
