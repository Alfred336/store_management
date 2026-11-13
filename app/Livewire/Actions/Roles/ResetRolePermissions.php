<?php

namespace App\Livewire\Actions\Roles;

use App\Models\AdminAuditLog;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reset a system role's permissions back to the catalogue defaults and clear
 * the customised_at flag so EnsureSystemRoles will manage it again.
 * Custom roles cannot be reset.
 */
class ResetRolePermissions
{
    public function __invoke(Role $role): void
    {
        $actor = auth()->user();
        abort_unless($actor !== null, 403);
        abort_unless($actor->can('roles.update'), 403);

        $systemRoles = ['Admin', 'Storekeeper', 'Cashier', 'Viewer'];
        abort_unless(in_array($role->name, $systemRoles, true), 422, 'Only system roles can be reset to defaults.');
        abort_if($role->name === 'Admin', 403, 'Admin role cannot be reset.');

        $defaults = config('permissions.system_role_defaults.'.$role->name, []);

        if ($defaults === ['*']) {
            $defaults = [];
            foreach (config('permissions.modules', []) as $module) {
                foreach ($module['permissions'] as $perm) {
                    $defaults[] = $perm['name'];
                }
            }
        }

        $oldPermissions = $role->permissions->pluck('name')->all();

        $role->syncPermissions($defaults);

        // Clear customised_at so EnsureSystemRoles will manage this role again.
        DB::table('roles')->where('id', $role->id)->update(['customised_at' => null]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AdminAuditLog::record(
            action: 'role.reset_to_defaults',
            subjectType: Role::class,
            subjectId: (int) $role->id,
            changes: [
                'role' => $role->name,
                'removed' => array_values(array_diff($oldPermissions, $defaults)),
                'added' => array_values(array_diff($defaults, $oldPermissions)),
            ],
        );
    }
}
