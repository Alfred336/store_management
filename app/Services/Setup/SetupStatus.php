<?php

namespace App\Services\Setup;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

/** Determine whether a database still needs its first administrator setup. */
class SetupStatus
{
    public function isRequired(): bool
    {
        if (! Schema::hasTable('settings') || ! Schema::hasTable('users')) {
            return false;
        }

        // Setup is disabled permanently as soon as any user exists.
        return ! User::query()->exists();
    }
}
