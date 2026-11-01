<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\AdminAccountProtection;

/** Authorize account self-deletion without permitting removal of the final Admin. */
class UserPolicy
{
    public function delete(User $actor, User $target): bool
    {
        return $actor->is($target) && ! app(AdminAccountProtection::class)->isLastAdmin($target);
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $actor->is($target) && ! app(AdminAccountProtection::class)->isLastAdmin($target);
    }
}
