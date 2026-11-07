<?php

namespace App\Livewire\Actions\Users;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Reset a user's password to a new temporary password.
 * Returns the plain password — shown once and never logged.
 *
 * @return array{user: User, password: string}
 */
class ResetUserPassword
{
    /**
     * @return array{user: User, password: string}
     */
    public function __invoke(User $target, ?string $plainPassword = null): array
    {
        Gate::authorize('resetPassword', $target);

        $password = $plainPassword && trim($plainPassword) !== ''
            ? trim($plainPassword)
            : Str::password(16);

        return DB::transaction(function () use ($target, $password): array {
            $target->forceFill([
                'password' => Hash::make($password),
                'must_change_password' => true,
            ])->save();

            // Invalidate all existing sessions so the new password takes effect immediately.
            $target->deleteSessions();

            AdminAuditLog::record('user.password_reset', User::class, $target->id);

            return ['user' => $target->fresh(), 'password' => $password];
        });
    }
}
