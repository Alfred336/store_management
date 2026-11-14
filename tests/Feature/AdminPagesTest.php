<?php

use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pagesAdminUser(): User
{
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('Admin');

    return $user;
}

$routes = [
    'dashboard',
    'units.index',
    'categories.index',
    'locations.index',
    'products.index',
    'invoices.index',
    'customers.index',
    'settings.index',
    'payments.index',
    'inventory.index',
    'inventory.ledger',
    'pos.index',
    'quotations.index',
    'users.index',
    'roles.index',
    'reports.sales',
    'reports.vat',
    'reports.receivables',
    'reports.payments',
    'reports.pos_sessions',
    'reports.inventory',
    'reports.voided_invoices',
];

foreach ($routes as $routeName) {
    test("admin can access {$routeName} and gets 200", function () use ($routeName): void {
        $this->actingAs(pagesAdminUser())
            ->get(route($routeName))
            ->assertOk();
    });
}
