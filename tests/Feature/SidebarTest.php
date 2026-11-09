<?php

use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function sidebarAdmin(): User
{
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('Admin');

    return $user;
}

function sidebarCashier(): User
{
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('Cashier');

    return $user;
}

function sidebarViewer(): User
{
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('Viewer');

    return $user;
}

// ─── Admin: all groups render ─────────────────────────────────────────────────

test('admin sees all sidebar group headings on dashboard', function (): void {
    $this->actingAs(sidebarAdmin())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Sales')
        ->assertSee('Inventory')
        ->assertSee('Reports')
        ->assertSee('Administration');
});

test('admin sidebar shows all sales links', function (): void {
    $this->actingAs(sidebarAdmin())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Point of Sale')
        ->assertSee('Invoices')
        ->assertSee('Payments')
        ->assertSee('Customers');
});

// ─── Admin: active-group open state ──────────────────────────────────────────

test('inventory group renders open when visiting an inventory route', function (): void {
    $this->actingAs(sidebarAdmin())
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertSee('open  data-flux-sidebar-group', false);
});

test('no group has open state when visiting the dashboard', function (): void {
    $this->actingAs(sidebarAdmin())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('open  data-flux-sidebar-group', false);
});

test('sales group renders open when visiting an invoices route', function (): void {
    $this->actingAs(sidebarAdmin())
        ->get(route('invoices.index'))
        ->assertOk()
        ->assertSee('open  data-flux-sidebar-group', false);
});

test('administration group renders open when visiting the users route', function (): void {
    $this->actingAs(sidebarAdmin())
        ->get(route('users.index'))
        ->assertOk()
        ->assertSee('open  data-flux-sidebar-group', false);
});

// ─── Cashier: sees Sales, no Administration or Reports ───────────────────────

test('cashier sees sales group and all its links', function (): void {
    $this->actingAs(sidebarCashier())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Sales')
        ->assertSee('Point of Sale')
        ->assertSee('Invoices')
        ->assertSee('Payments')
        ->assertSee('Customers');
});

test('cashier sees inventory group', function (): void {
    $this->actingAs(sidebarCashier())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Inventory')
        ->assertSee('Stock on Hand')
        ->assertSee('Stock Ledger')
        ->assertSee('Products');
});

test('cashier does not see reports or administration groups', function (): void {
    $this->actingAs(sidebarCashier())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Reports')
        ->assertDontSee('Administration');
});

// ─── Viewer: no empty groups ──────────────────────────────────────────────────

test('viewer sees sales inventory and reports groups', function (): void {
    $this->actingAs(sidebarViewer())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Sales')
        ->assertSee('Inventory')
        ->assertSee('Reports');
});

test('viewer does not see administration group', function (): void {
    $this->actingAs(sidebarViewer())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Administration');
});

test('viewer does not see pos or customers links', function (): void {
    $this->actingAs(sidebarViewer())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Point of Sale')
        ->assertDontSee('Customers');
});

test('viewer sees invoices and payments links in sales group', function (): void {
    $this->actingAs(sidebarViewer())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Invoices')
        ->assertSee('Payments');
});
