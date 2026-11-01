<?php

namespace App\Livewire\Actions\Users;

use App\Models\User;
use App\Services\Authorization\AdminAccountProtection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Delete an account only when policy allows and another Admin will remain. */
class DeleteUser
{
    public function __invoke(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $lockedAdminCount = app(AdminAccountProtection::class)->lockAdmins();
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            app(AdminAccountProtection::class)->assertCanRemove($lockedUser, $lockedAdminCount);
            Gate::authorize('delete', $lockedUser);

            $lockedUser->delete();
        });
    }
}
