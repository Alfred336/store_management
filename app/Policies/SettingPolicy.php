<?php

namespace App\Policies;

use App\Models\User;

/** Authorize access to the singleton application settings screen. */
class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('settings.view');
    }

    public function update(User $user): bool
    {
        return $user->can('settings.update');
    }
}
