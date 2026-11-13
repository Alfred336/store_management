<?php

namespace App\Livewire\Actions\Authorization;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Create or synchronise system roles and permissions from config/permissions.php.
 *
 * Rules:
 *  - Admin always has ALL catalogue permissions (synced on every run).
 *  - Other system roles (Storekeeper, Cashier, Viewer):
 *      · On first creation: assign defaults.
 *      · Exists + customised_at IS NULL: additively grant any missing defaults (never remove).
 *      · Exists + customised_at IS NOT NULL: skip entirely (screen-managed).
 *  - Custom roles are never touched.
 *  - Schema::hasColumn guard makes this safe inside old migrations that run before
 *    the 2026_11_13 migration that adds customised_at.
 */
class EnsureSystemRoles
{
    public function __invoke(): void
    {
        $catalogue = config('permissions.modules', []);
        $defaults = config('permissions.system_role_defaults', []);

        // ── 1. Collect every permission name from the catalogue ────────────
        $allNames = [];
        foreach ($catalogue as $module) {
            foreach ($module['permissions'] as $perm) {
                $allNames[] = $perm['name'];
            }
        }

        // ── 2. Ensure every permission exists in the DB ────────────────────
        foreach ($allNames as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // ── 3. Guard: does the customised_at column exist yet? ─────────────
        $hasCustomisedAt = Schema::hasColumn('roles', 'customised_at');

        // ── 4. Sync system roles ───────────────────────────────────────────
        $systemRoles = ['Admin', 'Storekeeper', 'Cashier', 'Viewer'];

        foreach ($systemRoles as $roleName) {
            $defaultSet = $defaults[$roleName] ?? [];
            $resolvedDefaults = $defaultSet === ['*'] ? $allNames : $defaultSet;

            $exists = Role::where('name', $roleName)->where('guard_name', 'web')->exists();

            if (! $exists) {
                // First creation: create role and assign defaults.
                $role = Role::create(['name' => $roleName, 'guard_name' => 'web']);
                $role->syncPermissions($resolvedDefaults);

                continue;
            }

            $role = Role::findByName($roleName, 'web');

            if ($roleName === 'Admin') {
                // Admin always holds every catalogue permission.
                $role->syncPermissions($resolvedDefaults);

                continue;
            }

            // For non-Admin system roles, respect customised_at.
            if (! $hasCustomisedAt) {
                // Column not yet created (called from an old migration): sync defaults.
                $role->syncPermissions($resolvedDefaults);

                continue;
            }

            $customisedAt = DB::table('roles')
                ->where('id', $role->id)
                ->value('customised_at');

            if ($customisedAt !== null) {
                // Role has been edited on screen — hands off.
                continue;
            }

            // Uncustomised role: additively grant any missing defaults.
            $current = $role->permissions->pluck('name')->all();
            $missing = array_diff($resolvedDefaults, $current);

            if (! empty($missing)) {
                $role->givePermissionTo($missing);
            }
        }

        // ── 5. Clear Spatie permission cache ──────────────────────────────
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
