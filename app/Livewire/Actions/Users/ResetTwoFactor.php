<?php

namespace App\Livewire\Actions\Users;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Clear a user's two-factor authentication settings. */
class ResetTwoFactor
{
    public function __invoke(User $target): User
    {
        Gate::authorize('resetPassword', $target);

        return DB::transaction(function () use ($target): User {
            $target->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            // Invalidate sessions so the 2FA change takes effect immediately.
            $target->deleteSessions();

            AdminAuditLog::record('user.two_factor_reset', User::class, $target->id);

            return $target->fresh();
        });
    }
}
