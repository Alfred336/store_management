<?php

use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Livewire\Actions\Roles\CreateRole;
use App\Livewire\Actions\Roles\DeleteRole;
use App\Livewire\Actions\Roles\RenameRole;
use App\Livewire\Actions\Roles\ResetRolePermissions;
use App\Livewire\Actions\Roles\SaveRolePermissions;
use App\Livewire\Roles\Index as RolesIndex;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function rolesAdmin(): User
{
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('Admin');

    return $user;
}

function rolesCashier(): User
{
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('Cashier');

    return $user;
}

function rolesViewer(): User
{
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('Viewer');

    return $user;
}

// ─── Permission Catalogue ────────────────────────────────────────────────────

test('config/permissions.php has all 13 module groups', function (): void {
    $modules = array_keys(config('permissions.modules', []));

    $expected = ['Dashboard', 'Products', 'Inventory', 'Customers', 'Quotations',
        'Invoices', 'Payments', 'POS', 'Reports', 'Users', 'Roles', 'Settings', 'Audit'];

    foreach ($expected as $group) {
        expect($modules)->toContain($group);
    }
});

test('every permission name in the catalogue is unique', function (): void {
    $names = collect(config('permissions.modules', []))
        ->flatMap(fn (array $m) => array_column($m['permissions'], 'name'))
        ->all();

    expect(count($names))->toBe(count(array_unique($names)));
});

test('every permission in system_role_defaults exists in the catalogue', function (): void {
    $catalogueNames = collect(config('permissions.modules', []))
        ->flatMap(fn (array $m) => array_column($m['permissions'], 'name'))
        ->all();

    foreach (config('permissions.system_role_defaults', []) as $role => $perms) {
        if ($perms === ['*']) {
            continue;
        }
        foreach ($perms as $perm) {
            expect(in_array($perm, $catalogueNames, true))->toBeTrue("'{$perm}' in defaults for '{$role}' is not in the catalogue");
        }
    }
});

// ─── EnsureSystemRoles ───────────────────────────────────────────────────────

test('EnsureSystemRoles creates all four system roles', function (): void {
    app(EnsureSystemRoles::class)();

    expect(Role::where('name', 'Admin')->exists())->toBeTrue();
    expect(Role::where('name', 'Storekeeper')->exists())->toBeTrue();
    expect(Role::where('name', 'Cashier')->exists())->toBeTrue();
    expect(Role::where('name', 'Viewer')->exists())->toBeTrue();
});

test('Admin always holds every catalogue permission', function (): void {
    app(EnsureSystemRoles::class)();

    $admin = Role::findByName('Admin', 'web');
    $catalogueNames = collect(config('permissions.modules', []))
        ->flatMap(fn (array $m) => array_column($m['permissions'], 'name'))
        ->all();

    $adminPerms = $admin->permissions->pluck('name')->all();

    foreach ($catalogueNames as $name) {
        expect(in_array($name, $adminPerms, true))->toBeTrue("Admin is missing '{$name}'");
    }
});

test('re-running EnsureSystemRoles does NOT undo a screen change to Cashier', function (): void {
    app(EnsureSystemRoles::class)();

    $cashier = Role::findByName('Cashier', 'web');
    $cashier->revokePermissionTo('payments.create');
    DB::table('roles')->where('id', $cashier->id)->update(['customised_at' => now()]);

    // Run seeder again.
    app(EnsureSystemRoles::class)();

    $cashier->refresh();
    $cashier->load('permissions');
    expect($cashier->permissions->pluck('name'))->not->toContain('payments.create');
});

test('a new catalogue permission reaches Admin on next EnsureSystemRoles run', function (): void {
    app(EnsureSystemRoles::class)();

    // Simulate a new permission being added to the catalogue by inserting it.
    Permission::findOrCreate('test.new_permission', 'web');

    // Manually append it to the catalogue config for this test.
    config(['permissions.modules.TestModule' => [
        'permissions' => [
            ['name' => 'test.new_permission', 'label' => 'Test', 'description' => 'Test permission.'],
        ],
    ]]);

    app(EnsureSystemRoles::class)();

    $admin = Role::findByName('Admin', 'web');
    $admin->load('permissions');
    expect($admin->permissions->pluck('name'))->toContain('test.new_permission');
});

test('a new catalogue permission reaches an uncustomised role additively', function (): void {
    app(EnsureSystemRoles::class)();

    // Add permission to Viewer defaults in config.
    $viewerDefaults = config('permissions.system_role_defaults.Viewer');
    $viewerDefaults[] = 'test.new_permission';
    config(['permissions.system_role_defaults.Viewer' => $viewerDefaults]);
    config(['permissions.modules.TestModule' => [
        'permissions' => [
            ['name' => 'test.new_permission', 'label' => 'Test', 'description' => 'Test permission.'],
        ],
    ]]);

    // Ensure Viewer is uncustomised.
    $viewer = Role::findByName('Viewer', 'web');
    DB::table('roles')->where('id', $viewer->id)->update(['customised_at' => null]);

    Permission::findOrCreate('test.new_permission', 'web');
    app(EnsureSystemRoles::class)();

    $viewer->load('permissions');
    expect($viewer->permissions->pluck('name'))->toContain('test.new_permission');
});

test('a new catalogue permission does NOT reach a customised role', function (): void {
    app(EnsureSystemRoles::class)();

    $cashier = Role::findByName('Cashier', 'web');
    DB::table('roles')->where('id', $cashier->id)->update(['customised_at' => now()]);

    $cashierDefaults = config('permissions.system_role_defaults.Cashier');
    $cashierDefaults[] = 'test.new_permission';
    config(['permissions.system_role_defaults.Cashier' => $cashierDefaults]);
    config(['permissions.modules.TestModule' => [
        'permissions' => [
            ['name' => 'test.new_permission', 'label' => 'Test', 'description' => 'Test permission.'],
        ],
    ]]);

    Permission::findOrCreate('test.new_permission', 'web');
    app(EnsureSystemRoles::class)();

    $cashier->load('permissions');
    expect($cashier->permissions->pluck('name'))->not->toContain('test.new_permission');
});

// ─── System role restrictions ────────────────────────────────────────────────

test('system roles cannot be renamed', function (): void {
    $admin = rolesAdmin();
    $this->actingAs($admin);

    $cashier = Role::findByName('Cashier', 'web');

    $this->expectException(HttpException::class);
    app(RenameRole::class)($cashier, 'NewName');
});

test('system roles cannot be deleted', function (): void {
    $admin = rolesAdmin();
    $this->actingAs($admin);

    $cashier = Role::findByName('Cashier', 'web');

    $this->expectException(HttpException::class);
    app(DeleteRole::class)($cashier);
});

test('Admin role cannot be edited via SaveRolePermissions', function (): void {
    $admin = rolesAdmin();
    $this->actingAs($admin);

    $adminRole = Role::findByName('Admin', 'web');

    $this->expectException(HttpException::class);
    app(SaveRolePermissions::class)($adminRole, ['dashboard.view']);
});

// ─── SaveRolePermissions + Reset ────────────────────────────────────────────

test('removing payments.create from Cashier takes effect and customised_at is set', function (): void {
    $admin = rolesAdmin();
    $this->actingAs($admin);

    $cashier = Role::findByName('Cashier', 'web');
    $newPerms = $cashier->permissions->pluck('name')->filter(fn ($p) => $p !== 'payments.create')->values()->all();

    app(SaveRolePermissions::class)($cashier, $newPerms);

    $cashier->load('permissions');
    expect($cashier->permissions->pluck('name'))->not->toContain('payments.create');

    $customisedAt = DB::table('roles')->where('id', $cashier->id)->value('customised_at');
    expect($customisedAt)->not->toBeNull();
});

test('reset to default restores permissions and clears customised_at', function (): void {
    $admin = rolesAdmin();
    $this->actingAs($admin);

    $cashier = Role::findByName('Cashier', 'web');
    // Remove a permission.
    $newPerms = $cashier->permissions->pluck('name')->filter(fn ($p) => $p !== 'payments.create')->values()->all();
    app(SaveRolePermissions::class)($cashier, $newPerms);

    // Reset.
    app(ResetRolePermissions::class)($cashier);

    $cashier->load('permissions');
    expect($cashier->permissions->pluck('name'))->toContain('payments.create');

    $customisedAt = DB::table('roles')->where('id', $cashier->id)->value('customised_at');
    expect($customisedAt)->toBeNull();
});

// ─── Custom role CRUD ────────────────────────────────────────────────────────

test('admin can create a custom role', function (): void {
    $admin = rolesAdmin();
    $this->actingAs($admin);

    $role = app(CreateRole::class)('Supervisor', 'Manages daily ops');

    expect($role->name)->toBe('Supervisor');
    $desc = DB::table('roles')->where('id', $role->id)->value('description');
    expect($desc)->toBe('Manages daily ops');
});

test('custom role can be renamed', function (): void {
    $admin = rolesAdmin();
    $this->actingAs($admin);

    $role = app(CreateRole::class)('Supervisor', '');
    app(RenameRole::class)($role, 'Senior Supervisor');

    $updated = DB::table('roles')->where('id', $role->id)->value('name');
    expect($updated)->toBe('Senior Supervisor');
});

test('custom role can be deleted when no users hold it', function (): void {
    $admin = rolesAdmin();
    $this->actingAs($admin);

    $role = app(CreateRole::class)('Temp', '');
    $roleId = $role->id;

    app(DeleteRole::class)($role);

    expect(Role::where('id', $roleId)->exists())->toBeFalse();
});

test('custom role cannot be deleted when users hold it', function (): void {
    $admin = rolesAdmin();
    $this->actingAs($admin);

    $role = app(CreateRole::class)('Supervisor', '');
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->expectException(HttpException::class);
    app(DeleteRole::class)($role);
});

// ─── Privilege escalation ────────────────────────────────────────────────────

test('cashier cannot change permissions of a role they hold', function (): void {
    $cashier = rolesCashier();
    $this->actingAs($cashier);

    $cashierRole = Role::findByName('Cashier', 'web');

    $this->expectException(HttpException::class);
    app(SaveRolePermissions::class)($cashierRole, ['dashboard.view']);
});

test('non-admin cannot grant a permission they do not hold', function (): void {
    app(EnsureSystemRoles::class)();

    // Create a custom role with no sensitive permissions.
    $customRole = Role::create(['name' => 'LimitedEditor', 'guard_name' => 'web']);

    $storekeeper = User::factory()->create(['is_active' => true]);
    $storekeeper->assignRole('Storekeeper');
    $this->actingAs($storekeeper);

    // Storekeeper does not have users.view; try to grant it.
    $this->expectException(HttpException::class);
    app(SaveRolePermissions::class)($customRole, ['users.view']);
});

test('non-admin cannot edit a role that holds users.* permissions', function (): void {
    app(EnsureSystemRoles::class)();

    // Admin role holds users.* — only an Admin can edit it (blocked anyway by admin-role guard).
    // Create a custom role with users.view granted.
    $customRole = Role::create(['name' => 'UserManager', 'guard_name' => 'web']);
    $usersViewPerm = Permission::findByName('users.view', 'web');
    $customRole->givePermissionTo($usersViewPerm);

    $storekeeper = User::factory()->create(['is_active' => true]);
    $storekeeper->assignRole('Storekeeper');
    $this->actingAs($storekeeper);

    $this->expectException(HttpException::class);
    app(SaveRolePermissions::class)($customRole, ['dashboard.view']);
});

// ─── Audit log ───────────────────────────────────────────────────────────────

test('SaveRolePermissions writes an audit row', function (): void {
    $admin = rolesAdmin();
    $this->actingAs($admin);

    $cashier = Role::findByName('Cashier', 'web');
    $newPerms = $cashier->permissions->pluck('name')->filter(fn ($p) => $p !== 'payments.create')->values()->all();

    app(SaveRolePermissions::class)($cashier, $newPerms);

    $log = AdminAuditLog::where('action', 'role.permissions_updated')
        ->where('subject_id', $cashier->id)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->changes['removed'])->toContain('payments.create');
});

test('CreateRole writes an audit row', function (): void {
    $admin = rolesAdmin();
    $this->actingAs($admin);

    $role = app(CreateRole::class)('Supervisor', '');

    $log = AdminAuditLog::where('action', 'role.created')->where('subject_id', $role->id)->first();
    expect($log)->not->toBeNull();
});

test('DeleteRole writes an audit row', function (): void {
    $admin = rolesAdmin();
    $this->actingAs($admin);

    $role = app(CreateRole::class)('TempRole', '');
    $roleId = $role->id;

    app(DeleteRole::class)($role);

    $log = AdminAuditLog::where('action', 'role.deleted')->where('subject_id', $roleId)->first();
    expect($log)->not->toBeNull();
});

// ─── Access control (non-admin blocked) ──────────────────────────────────────

test('viewer cannot access the roles screen', function (): void {
    $viewer = rolesViewer();
    $this->actingAs($viewer);

    $this->get(route('roles.index'))->assertForbidden();
});

test('cashier cannot create a role', function (): void {
    $cashier = rolesCashier();
    $this->actingAs($cashier);

    $this->expectException(HttpException::class);
    app(CreateRole::class)('Hacked', '');
});

// ─── Livewire component rendering ────────────────────────────────────────────

test('roles screen renders all module groups', function (): void {
    $admin = rolesAdmin();

    $component = Livewire::actingAs($admin)->test(RolesIndex::class);

    $expectedModules = ['Dashboard', 'Products', 'Inventory', 'Customers', 'Quotations',
        'Invoices', 'Payments', 'POS', 'Reports', 'Users', 'Roles', 'Settings', 'Audit'];

    foreach ($expectedModules as $module) {
        $component->assertSee($module);
    }
});

test('roles screen shows all four system roles', function (): void {
    $admin = rolesAdmin();

    $component = Livewire::actingAs($admin)->test(RolesIndex::class);

    $component->assertSee('Admin')
        ->assertSee('Storekeeper')
        ->assertSee('Cashier')
        ->assertSee('Viewer');
});

test('openCreate dispatches modal-show', function (): void {
    $admin = rolesAdmin();

    Livewire::actingAs($admin)
        ->test(RolesIndex::class)
        ->call('openCreate')
        ->assertDispatched('modal-show', name: 'create-role');
});

test('openRename dispatches modal-show for custom role', function (): void {
    $admin = rolesAdmin();
    $this->actingAs($admin);
    $role = app(CreateRole::class)('Supervisor', '');

    Livewire::actingAs($admin)
        ->test(RolesIndex::class)
        ->call('openRename', $role->id)
        ->assertDispatched('modal-show', name: 'rename-role');
});

test('confirmDelete dispatches modal-show for custom role', function (): void {
    $admin = rolesAdmin();
    $this->actingAs($admin);
    $role = app(CreateRole::class)('Temp', '');

    Livewire::actingAs($admin)
        ->test(RolesIndex::class)
        ->call('confirmDelete', $role->id)
        ->assertDispatched('modal-show', name: 'delete-role');
});

test('confirmOrSave dispatches modal-show when removing permissions from role with users', function (): void {
    $admin = rolesAdmin();
    $this->actingAs($admin);

    $cashier = Role::findByName('Cashier', 'web');
    $cashierUser = User::factory()->create();
    $cashierUser->assignRole('Cashier');

    // Select cashier in the component and remove payments.create.
    $perms = $cashier->permissions->pluck('name')->filter(fn ($p) => $p !== 'payments.create')->values()->all();

    Livewire::actingAs($admin)
        ->test(RolesIndex::class)
        ->call('selectRole', $cashier->id)
        ->set('pendingPermissions', $perms)
        ->call('confirmOrSave')
        ->assertDispatched('modal-show', name: 'save-confirm');
});

test('storeRole on the component creates the role and dispatches modal-close', function (): void {
    $admin = rolesAdmin();

    Livewire::actingAs($admin)
        ->test(RolesIndex::class)
        ->set('newRoleName', 'SpecialRole')
        ->set('newRoleDescription', 'A special role')
        ->call('storeRole')
        ->assertDispatched('modal-close', name: 'create-role');

    expect(Role::where('name', 'SpecialRole')->exists())->toBeTrue();
});
