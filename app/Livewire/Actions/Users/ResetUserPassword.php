<?php

namespace App\Livewire\Actions\Users;

use App\Mail\TemporaryPasswordMail;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Reset a user's password to a new temporary password and email it to them.
 * Mail failure rolls back the password change so the user is not left without credentials.
 *
 * @return array{user: User}
 */
class ResetUserPassword
{
    /**
     * @return array{user: User}
     */
    public function __invoke(User $target): array
    {
        Gate::authorize('resetPassword', $target);

        $password = Str::password(16);

        $user = DB::transaction(function () use ($target, $password): User {
            $target->forceFill([
                'password' => Hash::make($password),
                'must_change_password' => true,
            ])->save();

            // Invalidate all existing sessions so the new password takes effect immediately.
            $target->deleteSessions();

            AdminAuditLog::record('user.password_reset', User::class, $target->id);

            // Send the new credentials email inside the transaction so a mail failure
            // rolls back the password change — the user keeps their current credentials.
            Mail::to($target)->send(new TemporaryPasswordMail($target, $password));

            return $target->fresh();
        });

        return ['user' => $user];
    }
}
