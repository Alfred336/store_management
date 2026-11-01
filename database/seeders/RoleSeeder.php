<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Create inventory permissions and synchronize the standard inventory roles. */
class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $resources = ['units', 'categories', 'locations', 'products'];
        $allPermissions = [];

        foreach ($resources as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $ability) {
                $permission = "{$resource}.{$ability}";
                Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
                $allPermissions[] = $permission;
            }
        }

        $viewAndEditPermissions = collect($resources)
            ->flatMap(fn (string $resource): array => [
                "{$resource}.view",
                "{$resource}.create",
                "{$resource}.update",
            ])
            ->all();
        $viewPermissions = collect($resources)
            ->map(fn (string $resource): string => "{$resource}.view")
            ->all();

        Role::findOrCreate('Admin', 'web')->syncPermissions($allPermissions);
        Role::findOrCreate('Storekeeper', 'web')->syncPermissions($viewAndEditPermissions);
        Role::findOrCreate('Viewer', 'web')->syncPermissions($viewPermissions);
    }
}
