<?php

namespace App\Livewire\Actions\Roles;

use App\Models\AdminAuditLog;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Persist a new permission set for a role, enforcing privilege-escalation rules.
 *
 * Rules enforced:
 *  1. The actor cannot change permissions of a role they hold.
 *  2. Non-admins cannot grant permissions they themselves don't have.
 *  3. Only Admin can edit roles that hold any users.* or roles.* permission.
 *  4. Admin role itself is immutable from the screen.
 *
 * @param  string[]  $newPermissions  Desired permission names after save.
 */
class SaveRolePermissions
{
    /** @param  string[]  $newPermissions */
    public function __invoke(Role $role, array $newPermissions): void
    {
        $actor = auth()->user();
        abort_unless($actor !== null, 403);
        abort_unless($actor->can('roles.update'), 403);

        // Admin role is read-only.
        abort_if($role->name === 'Admin', 403, 'The Admin role cannot be modified.');

        // Rule 1: cannot edit a role you hold.
        abort_if($actor->hasRole($role->name), 403, 'You cannot modify a role you hold.');

        $oldPermissions = $role->permissions->pluck('name')->all();

        // Rule 3: only Admin can edit roles with users.* or roles.* permissions.
        if (! $actor->hasRole('Admin')) {
            $sensitiveOld = array_filter($oldPermissions, fn (string $p) => str_starts_with($p, 'users.') || str_starts_with($p, 'roles.'));
            $sensitiveNew = array_filter($newPermissions, fn (string $p) => str_starts_with($p, 'users.') || str_starts_with($p, 'roles.'));

            abort_if(! empty($sensitiveOld) || ! empty($sensitiveNew), 403, 'Only admins may edit roles that hold user or role-management permissions.');
        }

        // Rule 2: non-admins cannot grant permissions they don't have.
        if (! $actor->hasRole('Admin')) {
            $actorPerms = $actor->getAllPermissions()->pluck('name')->all();
            $granted = array_diff($newPermissions, $oldPermissions);

            foreach ($granted as $perm) {
                abort_unless(in_array($perm, $actorPerms, true), 403, "You cannot grant '{$perm}' because you do not hold that permission.");
            }
        }

        $role->syncPermissions($newPermissions);

        // Mark role as customised so EnsureSystemRoles leaves it alone.
        DB::table('roles')->where('id', $role->id)->update(['customised_at' => now()]);

        // Clear permission cache so the change takes effect on the next request.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AdminAuditLog::record(
            action: 'role.permissions_updated',
            subjectType: Role::class,
            subjectId: (int) $role->id,
            changes: [
                'role' => $role->name,
                'removed' => array_values(array_diff($oldPermissions, $newPermissions)),
                'added' => array_values(array_diff($newPermissions, $oldPermissions)),
            ],
        );
    }
}
