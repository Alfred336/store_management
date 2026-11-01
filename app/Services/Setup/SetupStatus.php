<?php

namespace App\Services\Setup;

use App\Models\Setting;
use App\Models\User;

/** Determine whether a database still needs its first administrator setup. */
class SetupStatus
{
    private const CACHE_KEY = 'setup:completed';

    public function isRequired(): bool
    {
        if (cache()->get(self::CACHE_KEY) === true) {
            return false;
        }

        if (Setting::query()->whereKey('setup_completed_at')->exists()) {
            cache()->forever(self::CACHE_KEY, true);

            return false;
        }

        $hasUsers = User::query()->exists();

        if ($hasUsers) {
            cache()->forever(self::CACHE_KEY, true);
        }

        return ! $hasUsers;
    }

    /** Invalidate the cached first-user check after a user is created or removed. */
    public function forget(): void
    {
        cache()->forget(self::CACHE_KEY);
    }

    public function markComplete(): void
    {
        cache()->forever(self::CACHE_KEY, true);
    }
}
