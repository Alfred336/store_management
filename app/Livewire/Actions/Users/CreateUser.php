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
use Spatie\Permission\Models\Role;

/**
 * Create a new user account with one or more roles.
 *
 * Password may be generated automatically (default) or supplied by the admin.
 * When the password will be emailed the mail is sent inside the transaction so
 * a mail failure rolls back the entire operation — no half-created user is left.
 *
 * @return array{user: User}
 */
class CreateUser
{
    /**
     * @param  string[]  $roleNames
     * @param  'generate'|'manual'  $passwordMode
     * @return array{user: User}
     */
    public function __invoke(
        string $name,
        string $email,
        array $roleNames,
        string $passwordMode = 'generate',
        ?string $plainPassword = null,
        bool $mustChange = true,
        bool $emailPassword = true,
    ): array {
        Gate::authorize('create', User::class);

        $actor = auth()->user();

        if (empty($roleNames)) {
            throw ValidationException::withMessages(['roles' => 'At least one role is required.']);
        }

        $this->validateRoleAssignment($actor, $roleNames);

        if ($passwordMode === 'manual' && empty($plainPassword)) {
            throw ValidationException::withMessages(['plainPassword' => 'Password is required.']);
        }

        $password = $passwordMode === 'generate' ? Str::password(16) : $plainPassword;
        $willEmail = $passwordMode === 'generate' || $emailPassword;

        $user = DB::transaction(function () use ($name, $email, $roleNames, $password, $mustChange, $willEmail, $passwordMode): User {
            if (User::where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'A user with this email already exists.']);
            }

            $user = User::create([
                'name' => trim($name),
                'email' => strtolower(trim($email)),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_active' => true,
                'must_change_password' => $mustChange,
            ]);

            $user->syncRoles($roleNames);

            AdminAuditLog::record('user.created', User::class, $user->id, [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roleNames,
                'pwd_mode' => $passwordMode,
                'must_change' => $mustChange,
                'emailed' => $willEmail,
            ]);

            if ($willEmail) {
                // Mail inside the transaction: failure rolls back user creation entirely.
                Mail::to($user)->send(new TemporaryPasswordMail($user, $password));
            }

            return $user;
        });

        return ['user' => $user];
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
