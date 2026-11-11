<?php

namespace App\Livewire\Actions\Users;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** Update a user's name, email, and roles. */
class UpdateUser
{
    /**
     * @param  string[]  $roleNames
     */
    public function __invoke(User $target, string $name, string $email, array $roleNames): User
    {
        Gate::authorize('update', $target);

        if ($target->anonymized_at !== null) {
            throw ValidationException::withMessages([
                'account' => 'Anonymized users cannot be edited.',
            ]);
        }

        $actor = auth()->user();

        if (empty($roleNames)) {
            throw ValidationException::withMessages(['roles' => 'At least one role is required.']);
        }

        $this->validateRoleAssignment($actor, $target, $roleNames);

        return DB::transaction(function () use ($target, $name, $email, $roleNames): User {
            if (User::where('email', $email)->where('id', '!=', $target->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'A user with this email already exists.']);
            }

            $old = ['name' => $target->name, 'email' => $target->email, 'roles' => $target->getRoleNames()->all()];

            $target->update([
                'name' => trim($name),
                'email' => strtolower(trim($email)),
            ]);

            $target->syncRoles($roleNames);

            // Changing roles takes effect immediately — delete the user's sessions.
            $target->deleteSessions();

            AdminAuditLog::record('user.updated', User::class, $target->id, [
                'old' => $old,
                'new' => ['name' => $target->name, 'email' => $target->email, 'roles' => $roleNames],
            ]);

            return $target->fresh();
        });
    }

    /**
     * @param  string[]  $roleNames
     */
    private function validateRoleAssignment(User $actor, User $target, array $roleNames): void
    {
        $isAdmin = $actor->hasRole('Admin');
        $targetHadAdmin = $target->hasRole('Admin');

        foreach ($roleNames as $roleName) {
            if ($roleName === 'Admin' && ! $isAdmin) {
                throw ValidationException::withMessages(['roles' => 'Only an Admin can assign the Admin role.']);
            }

            if (! $isAdmin) {
                $role = Role::findByName($roleName, 'web');
                $rolePermissions = $role->permissions->pluck('name')->all();
                $actorPermissions = $actor->getAllPermissions()->pluck('name')->all();
                $excess = array_diff($rolePermissions, $actorPermissions);

                if (! empty($excess)) {
                    throw ValidationException::withMessages([
                        'roles' => "You cannot assign the '{$roleName}' role: it contains permissions you do not hold.",
                    ]);
                }
            }
        }

        // Non-admin removing Admin role from someone else is also blocked.
        if ($targetHadAdmin && ! in_array('Admin', $roleNames, true) && ! $isAdmin) {
            throw ValidationException::withMessages(['roles' => 'Only an Admin can remove the Admin role.']);
        }
    }
}
