<?php

namespace App\Services\Authorization;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Prevent removal of the final Admin account. */
class AdminAccountProtection
{
    private const ADMIN_ROLES = ['Admin'];

    public function isAdmin(User $user): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    public function isLastAdmin(User $user): bool
    {
        if (! $this->isAdmin($user)) {
            return false;
        }

        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', self::ADMIN_ROLES))
            ->count() <= 1;
    }

    /** Lock all administrator rows so concurrent deletions cannot remove the final admin. */
    public function lockAdmins(): int
    {
        $lockedAdmins = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', self::ADMIN_ROLES))
            ->orderBy('users.id')
            ->lockForUpdate()
            ->get(['users.id']);

        return count($lockedAdmins);
    }

    public function assertCanRemove(User $user, ?int $lockedAdminCount = null): void
    {
        if ($this->isAdmin($user) && ($lockedAdminCount ?? $this->adminCount()) <= 1) {
            throw ValidationException::withMessages([
                'account' => 'You cannot delete or deactivate the last Admin account. Assign Admin to another user first.',
            ]);
        }
    }

    public function adminCount(): int
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', self::ADMIN_ROLES))
            ->count();
    }
}
