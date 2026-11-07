<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\AdminAccountProtection;

/**
 * Authorise user-management operations.
 *
 * Self-deletion via the settings screen uses delete()/deactivate() with $actor === $target.
 * Admin user-management Actions check the users.* permissions directly and enforce
 * self-protection and last-admin rules inside each Action.
 */
class UserPolicy
{
    /** Settings screen: allow a user to delete their own account (not the last Admin). */
    public function delete(User $actor, User $target): bool
    {
        return $actor->is($target) && ! app(AdminAccountProtection::class)->isLastAdmin($target);
    }

    /** Settings screen: allow a user to deactivate their own account (not the last Admin). */
    public function deactivate(User $actor, User $target): bool
    {
        return $actor->is($target) && ! app(AdminAccountProtection::class)->isLastAdmin($target);
    }

    /** Admin screen: view user list / details. */
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    /** Admin screen: create a new user. */
    public function create(User $actor): bool
    {
        return $actor->can('users.create');
    }

    /** Admin screen: update name, email, roles (self-update blocked). */
    public function update(User $actor, User $target): bool
    {
        return $actor->can('users.update') && ! $actor->is($target);
    }

    /** Admin screen: deactivate another user (last-admin check is in the Action). */
    public function deactivateAdmin(User $actor, User $target): bool
    {
        return $actor->can('users.deactivate') && ! $actor->is($target);
    }

    /** Admin screen: reset another user's password. */
    public function resetPassword(User $actor, User $target): bool
    {
        return $actor->can('users.reset_password') && ! $actor->is($target);
    }

    /** Admin screen: delete another user (activity check is in the Action). */
    public function deleteAdmin(User $actor, User $target): bool
    {
        return $actor->can('users.delete') && ! $actor->is($target);
    }
}
