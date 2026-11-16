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
use Illuminate\Validation\ValidationException;

/**
 * Reset a user's password.
 *
 * Password may be generated automatically (default) or supplied by the admin.
 * When the password will be emailed the mail is sent inside the transaction so
 * a mail failure rolls back the password change — the user keeps their current credentials.
 *
 * @return array{user: User}
 */
class ResetUserPassword
{
    /**
     * @param  'generate'|'manual'  $passwordMode
     * @return array{user: User}
     */
    public function __invoke(
        User $target,
        string $passwordMode = 'generate',
        ?string $plainPassword = null,
        bool $mustChange = true,
        bool $emailPassword = true,
    ): array {
        Gate::authorize('resetPassword', $target);

        if (! $target->is_active) {
            throw ValidationException::withMessages(['target' => 'Inactive users cannot have their password reset.']);
        }

        if ($target->anonymized_at !== null) {
            throw ValidationException::withMessages(['target' => 'Anonymized users cannot have their password reset.']);
        }

        if ($passwordMode === 'manual' && empty($plainPassword)) {
            throw ValidationException::withMessages(['plainPassword' => 'Password is required.']);
        }

        $password = $passwordMode === 'generate' ? Str::password(16) : $plainPassword;
        $willEmail = $passwordMode === 'generate' || $emailPassword;

        $user = DB::transaction(function () use ($target, $password, $mustChange, $willEmail, $passwordMode): User {
            $target->forceFill([
                'password' => Hash::make($password),
                'must_change_password' => $mustChange,
            ])->save();

            // Invalidate all existing sessions and pending reset tokens.
            $target->deleteSessions();
            DB::table('password_reset_tokens')->where('email', $target->email)->delete();

            AdminAuditLog::record('user.password_reset', User::class, $target->id, [
                'pwd_mode' => $passwordMode,
                'must_change' => $mustChange,
                'emailed' => $willEmail,
            ]);

            if ($willEmail) {
                // Mail inside the transaction: failure rolls back the password change.
                Mail::to($target)->send(new TemporaryPasswordMail($target, $password));
            }

            return $target->fresh();
        });

        return ['user' => $user];
    }
}
