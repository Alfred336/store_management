<?php

namespace App\Livewire\Actions\Users;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Create a new user account with one or more roles.
 * Returns ['user' => User, 'password' => string] — the plain password is shown
 * once in the UI and never logged.
 *
 * @return array{user: User, password: string}
 */
class CreateUser
{
    /**
     * @param  string[]  $roleNames
     * @return array{user: User, password: string}
     */
    public function __invoke(
        string $name,
        string $email,
        array $roleNames,
        ?string $plainPassword = null,
    ): array {
        Gate::authorize('create', User::class);

        $actor = auth()->user();

        if (empty($roleNames)) {
            throw ValidationException::withMessages(['roles' => 'At least one role is required.']);
        }

        $this->validateRoleAssignment($actor, $roleNames);

        $password = $plainPassword && trim($plainPassword) !== ''
            ? trim($plainPassword)
            : Str::password(16);

        return DB::transaction(function () use ($name, $email, $roleNames, $password): array {
            if (User::where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'A user with this email already exists.']);
            }

            $user = User::create([
                'name' => trim($name),
                'email' => strtolower(trim($email)),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $user->syncRoles($roleNames);

            AdminAuditLog::record('user.created', User::class, $user->id, [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roleNames,
            ]);

            return ['user' => $user, 'password' => $password];
        });
    }

    /**
     * Ensure the actor can assign the requested roles.
     * - Only an Admin can assign the Admin role.
     * - Non-admins can only assign roles whose permissions are a subset of their own.
     *
     * @param  string[]  $roleNames
     */
    private function validateRoleAssignment(User $actor, array $roleNames): void
    {
        $isAdmin = $actor->hasRole('Admin');

        foreach ($roleNames as $roleName) {
            $role = Role::findByName($roleName, 'web');

            if ($roleName === 'Admin' && ! $isAdmin) {
                throw ValidationException::withMessages(['roles' => 'Only an Admin can assign the Admin role.']);
            }

            if (! $isAdmin) {
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
    }
}
