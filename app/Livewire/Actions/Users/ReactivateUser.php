<?php

namespace App\Livewire\Actions\Users;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Reactivate a previously deactivated user account. */
class ReactivateUser
{
    public function __invoke(User $target): User
    {
        Gate::authorize('deactivateAdmin', $target);

        if ($target->anonymized_at !== null) {
            throw ValidationException::withMessages([
                'account' => 'Anonymized users cannot be reactivated.',
            ]);
        }

        return DB::transaction(function () use ($target): User {
            $target->update(['is_active' => true]);

            AdminAuditLog::record('user.reactivated', User::class, $target->id);

            return $target->fresh();
        });
    }
}
