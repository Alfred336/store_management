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
            'payments.view', 'payments.create', 'payments.reverse',
            'pos.use', 'pos.open_session', 'pos.close_session', 'pos.close_any_session', 'pos.discount', 'pos.credit_sale',
            'inventory.view', 'inventory.receive', 'inventory.adjust', 'inventory.transfer',
            'reports.view', 'reports.export',
            'users.view', 'users.create', 'users.update', 'users.deactivate', 'users.reset_password', 'users.delete',
            'audit.view',
            // Quotations
            'quotations.view', 'quotations.create', 'quotations.update', 'quotations.send',
            'quotations.convert', 'quotations.delete', 'quotations.print', 'quotations.approve_discount',
        ]);
        $cashierPermissions = [
            'dashboard.view', 'products.view', 'categories.view', 'units.view', 'locations.view',
            'customers.view', 'customers.create', 'customers.update',
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.issue', 'invoices.print',
            'payments.view', 'payments.create',
            'pos.use', 'pos.open_session', 'pos.close_session', 'pos.discount', 'pos.credit_sale',
            'inventory.view',
            'quotations.view', 'quotations.create', 'quotations.update', 'quotations.send', 'quotations.convert', 'quotations.print',
        ];

        foreach ($allPermissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'Admin' => $allPermissions,
            'Storekeeper' => array_merge($managePermissions, ['dashboard.view', 'invoices.view', 'invoices.print', 'inventory.view', 'inventory.receive', 'inventory.adjust', 'inventory.transfer', 'reports.view', 'quotations.view']),
            'Cashier' => $cashierPermissions,
            'Viewer' => array_merge($viewPermissions, ['dashboard.view', 'invoices.view', 'payments.view', 'inventory.view', 'reports.view', 'quotations.view']),
        ];

        foreach ($roles as $name => $permissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
        }
    }
}
