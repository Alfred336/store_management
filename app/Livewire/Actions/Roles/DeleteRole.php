<?php

namespace App\Livewire\Actions\Roles;

use App\Models\AdminAuditLog;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Delete a custom role. System roles and roles with assigned users cannot be deleted. */
class DeleteRole
{
    public function __invoke(Role $role): void
    {
        $actor = auth()->user();
        abort_unless($actor !== null, 403);
        abort_unless($actor->can('roles.delete'), 403);

        $systemRoles = ['Admin', 'Storekeeper', 'Cashier', 'Viewer'];
        abort_if(in_array($role->name, $systemRoles, true), 403, 'System roles cannot be deleted.');

        $userCount = $role->users()->count();
        abort_if($userCount > 0, 422, "Cannot delete role '{$role->name}' because {$userCount} user(s) still hold it.");

        $roleId = $role->id;
        $roleName = $role->name;

        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AdminAuditLog::record(
            action: 'role.deleted',
            subjectType: Role::class,
            subjectId: (int) $roleId,
            changes: ['name' => $roleName],
        );
    }
}
