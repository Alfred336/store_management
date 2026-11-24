<?php

namespace App\Livewire\Actions\Users;

use App\Models\AdminAuditLog;
use App\Models\Location;
use App\Models\PosSession;
use App\Models\User;
use App\Services\Access\LocationAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** Update a user's name, email, roles, and location assignments. */
class UpdateUser
{
    /**
     * @param  string[]  $roleNames
     * @param  int[]|null  $locationIds  Null = do not change locations (e.g. target is Admin).
     */
    public function __invoke(User $target, string $name, string $email, array $roleNames, ?array $locationIds = null): User
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

        $assigningAdmin = in_array('Admin', $roleNames, true);
        if (! $assigningAdmin && $locationIds !== null) {
            $this->validateLocationAssignment($actor, $target, $locationIds);
        }

        return DB::transaction(function () use ($target, $name, $email, $roleNames, $locationIds, $assigningAdmin): User {
            if (User::where('email', $email)->where('id', '!=', $target->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'A user with this email already exists.']);
            }

            $oldLocations = $target->locations()->pluck('location_id')->map(fn ($id) => (int) $id)->all();
            $old = ['name' => $target->name, 'email' => $target->email, 'roles' => $target->getRoleNames()->all(), 'locations' => $oldLocations];

            $target->update([
                'name' => trim($name),
                'email' => strtolower(trim($email)),
            ]);

            $target->syncRoles($roleNames);

            if (! $assigningAdmin && $locationIds !== null) {
                $target->locations()->sync($locationIds);
                // Flush per-request cache so this request sees the new list immediately.
                app(LocationAccess::class)->flush($target->id);
            }

            // Changing roles/locations takes effect immediately — delete the user's sessions.
            $target->deleteSessions();

            $newLocations = $assigningAdmin ? 'all (Admin)' : ($locationIds ?? $oldLocations);
            AdminAuditLog::record('user.updated', User::class, $target->id, [
                'old' => $old,
                'new' => ['name' => $target->name, 'email' => $target->email, 'roles' => $roleNames, 'locations' => $newLocations],
            ]);

            return $target->fresh();
        });
    }

    /**
     * Validate that the actor may assign these locations to the target user.
     *
     * - Non-Admin actor: may only assign locations they hold themselves.
     * - Checks that no removed location has an open POS session for the target.
     *
     * @param  int[]  $locationIds
     */
    private function validateLocationAssignment(?User $actor, User $target, array $locationIds): void
    {
        if ($actor === null) {
            return;
        }

        // Non-admin can only assign locations they themselves hold.
        if (! $actor->hasRole('Admin')) {
            $allowed = app(LocationAccess::class)->allowedLocationIds($actor) ?? [];
            foreach ($locationIds as $lid) {
                if (! in_array((int) $lid, $allowed, true)) {
                    throw ValidationException::withMessages([
                        'locations' => 'You can only assign locations you have access to yourself.',
                    ]);
                }
            }
        }

        // Validate every id maps to an active location.
        $valid = Location::whereIn('id', $locationIds)->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $invalid = array_diff(array_map(fn ($id) => (int) $id, $locationIds), $valid);
        if (! empty($invalid)) {
            throw ValidationException::withMessages(['locations' => 'One or more selected locations are invalid.']);
        }

        // Block removing a location where the target has an open POS session.
        $currentIds = $target->locations()->pluck('location_id')->map(fn ($id) => (int) $id)->all();
        $removed = array_diff($currentIds, array_map(fn ($id) => (int) $id, $locationIds));

        foreach ($removed as $lid) {
            $hasOpen = PosSession::where('user_id', $target->id)
                ->where('location_id', $lid)
                ->where('status', 'open')
                ->exists();

            if ($hasOpen) {
                $locName = Location::find($lid)?->name ?? "#{$lid}";
                throw ValidationException::withMessages([
                    'locations' => "Cannot remove {$locName}: the user has an open POS session there.",
                ]);
            }
        }
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
