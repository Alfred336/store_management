<?php

namespace App\Livewire\Actions\Authorization;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Create baseline roles and keep current inventory permissions synchronized. */
class EnsureSystemRoles
{
    public function __invoke(): void
    {
        $inventoryResources = ['units', 'categories', 'locations', 'products'];
        $allPermissions = collect($inventoryResources)
            ->flatMap(fn (string $resource): array => [
                "{$resource}.view",
                "{$resource}.create",
                "{$resource}.update",
                "{$resource}.delete",
            ])
            ->all();
        $managePermissions = collect($inventoryResources)
            ->flatMap(fn (string $resource): array => [
                "{$resource}.view",
                "{$resource}.create",
                "{$resource}.update",
            ])
            ->all();
        $viewPermissions = collect($inventoryResources)
            ->map(fn (string $resource): string => "{$resource}.view")
            ->all();
        $allPermissions = array_merge($allPermissions, [
            'dashboard.view', 'settings.view', 'settings.update',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.issue', 'invoices.void', 'invoices.print',
            'payments.view', 'payments.create', 'payments.void', 'pos.access',
        ]);
        $cashierPermissions = [
            'dashboard.view', 'products.view', 'categories.view', 'units.view', 'locations.view',
            'customers.view', 'customers.create', 'customers.update',
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.issue', 'invoices.print',
            'payments.view', 'payments.create', 'pos.access',
        ];
        $accountantPermissions = ['dashboard.view', 'settings.view', 'customers.view', 'invoices.view', 'invoices.print', 'payments.view', 'payments.create'];

        foreach ($allPermissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'Owner/Admin' => $allPermissions,
            'Admin' => $allPermissions,
            'Storekeeper' => array_merge($managePermissions, ['dashboard.view', 'invoices.view', 'invoices.print']),
            'Viewer' => array_merge($viewPermissions, ['dashboard.view', 'invoices.view']),
            'Cashier' => $cashierPermissions,
            'Accountant' => $accountantPermissions,
        ];

        foreach ($roles as $name => $permissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
        }
    }
}
