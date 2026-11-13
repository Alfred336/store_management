<?php

namespace App\Livewire\Actions\Roles;

use App\Models\AdminAuditLog;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Create a new custom role with an optional description. */
class CreateRole
{
    public function __invoke(string $name, string $description = ''): Role
    {
        $actor = auth()->user();
        abort_unless($actor !== null, 403);
        abort_unless($actor->can('roles.create'), 403);

        /** @var Role $role */
        $role = Role::create(['name' => $name, 'guard_name' => 'web']);

        if ($description !== '') {
            DB::table('roles')->where('id', $role->id)->update(['description' => $description]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AdminAuditLog::record(
            action: 'role.created',
            subjectType: Role::class,
            subjectId: (int) $role->id,
            changes: ['name' => $name, 'description' => $description],
        );

        return $role;
    }
}
