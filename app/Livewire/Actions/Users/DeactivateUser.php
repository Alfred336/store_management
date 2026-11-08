<?php

namespace App\Livewire\Actions\Users;

use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use App\Services\Authorization\AdminAccountProtection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Deactivate a user account. The user is immediately logged out. */
class DeactivateUser
{
    public function __construct(private readonly AdminAccountProtection $protection) {}

    public function __invoke(User $target): User
    {
        Gate::authorize('deactivateAdmin', $target);

        return DB::transaction(function () use ($target): User {
            $lockedAdminCount = $this->protection->lockAdmins();
            $this->protection->assertCanRemove($target, $lockedAdminCount);

            // Refuse if the user has an open POS session.
            $openSession = DB::table('pos_sessions')
                ->where('user_id', $target->id)
                ->where('status', 'open')
                ->first();

            if ($openSession !== null) {
                throw ValidationException::withMessages([
                    'account' => 'This user has an open POS session. Close it before deactivating.',
                ]);
            }

            $target->update(['is_active' => false]);
            $target->deleteSessions();

            // Delete any pending verification codes so they cannot be used after
            // the account is reactivated (a fresh code will be issued then).
            app(EmailVerificationService::class)->deletePendingCodes($target);

            AdminAuditLog::record('user.deactivated', User::class, $target->id);

            return $target->fresh();
        });
    }
}
