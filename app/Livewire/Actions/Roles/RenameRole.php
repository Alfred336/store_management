<?php

namespace App\Livewire\Actions\Roles;

use App\Models\AdminAuditLog;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Rename a custom role. System roles (Admin, Storekeeper, Cashier, Viewer) cannot be renamed. */
class RenameRole
{
    public function __invoke(Role $role, string $newName): void
    {
        $actor = auth()->user();
        abort_unless($actor !== null, 403);
        abort_unless($actor->can('roles.update'), 403);

        $systemRoles = ['Admin', 'Storekeeper', 'Cashier', 'Viewer'];
        abort_if(in_array($role->name, $systemRoles, true), 403, 'System roles cannot be renamed.');

        $oldName = $role->name;

        DB::table('roles')->where('id', $role->id)->update(['name' => $newName]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AdminAuditLog::record(
            action: 'role.renamed',
            subjectType: Role::class,
            subjectId: (int) $role->id,
            changes: ['old_name' => $oldName, 'new_name' => $newName],
        );
    }
}
