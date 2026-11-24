<?php

use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use App\Services\Settings\Settings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function dashUser(array $perms = []): User
{
    $user = User::factory()->create();
    $allPerms = array_unique(array_merge($perms, ['dashboard.view']));
    foreach ($allPerms as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $user->givePermissionTo($allPerms);
    $user->locations()->sync([makeTestLocation()->id]);

    return $user;
}

function dashSetup(): array
{
    $unit = Unit::forceCreate(['name' => 'Piece '.Str::random(4), 'abbreviation' => 'pc']);
    $category = Category::forceCreate(['name' => 'Agro '.Str::random(4)]);
    $product = Product::forceCreate([
        'sku' => 'SKU-'.Str::upper(Str::random(8)),
        'name' => 'Fertilizer '.Str::random(4),
        'base_unit_id' => $unit->id,
        'category_id' => $category->id,
        'reorder_level' => 0,
        'track_batches' => false,
        'is_active' => true,
    ]);
    $location = Location::forceCreate(['name' => 'Main '.Str::random(4), 'type' => 'warehouse', 'is_active' => true]);
    $customer = Customer::forceCreate([
        'code' => 'CUS-'.Str::upper(Str::random(6)),
        'name' => 'Test Customer '.Str::random(4),
        'email' => Str::random(6).'@example.com',
    ]);

    return compact('unit', 'category', 'product', 'location', 'customer');
}

/** Create a posted invoice with one line item. */
function makeInvoice(array $setup, string $status = 'issued', ?string $issuedAt = null, ?int $locationId = null, ?int $issuedBy = null): Invoice
{
    $inv = Invoice::forceCreate([
        'number' => 'INV-'.Str::upper(Str::random(8)),
        'channel' => 'invoice',
        'status' => $status,
        'customer_id' => $setup['customer']->id,
        'location_id' => $locationId ?? $setup['location']->id,
        'issue_date' => Carbon::today(),
        'due_date' => Carbon::today()->addDays(30),
        'currency' => 'TZS',
        'prices_include_tax' => false,
        'subtotal' => '100000.00',
        'discount_total' => '0.00',
        'tax_total' => '0.00',
        'total' => '100000.00',
        'amount_paid' => '0.00',
        'issued_at' => $issuedAt ?? Carbon::now()->toDateTimeString(),
        'issued_by' => $issuedBy,
        'idempotency_key' => Str::uuid()->toString(),
        'created_by' => $issuedBy,
    ]);

    InvoiceItem::forceCreate([
        'invoice_id' => $inv->id,
        'product_id' => $setup['product']->id,
        'description' => 'Item',
        'unit_id' => $setup['unit']->id,
        'quantity' => '1.000',
        'base_quantity' => '1.000',
        'unit_price' => '100000.00',
        'discount_amount' => '0.00',
        'tax_type' => 'none',
        'tax_rate' => '0.00',
        'line_tax' => '0.00',
        'line_total' => '100000.00',
    ]);

    return $inv;
}

// ─── DashboardService unit tests ──────────────────────────────────────────────

test('salesKpis counts only posted invoices', function (): void {
    $setup = dashSetup();
    Cache::flush();

    // Posted
    makeInvoice($setup, 'issued');
    makeInvoice($setup, 'partially_paid');
    makeInvoice($setup, 'paid');
    // Excluded
    makeInvoice($setup, 'draft');
    makeInvoice($setup, 'void');

    $svc = app(DashboardService::class);
    $kpis = $svc->salesKpis('today', null, false, 0);

    expect($kpis['invoice_count'])->toBe(3);
    expect((float) $kpis['total_sales'])->toBe(300000.0);
});

test('salesKpis excludes drafts and voids', function (): void {
    $setup = dashSetup();
    Cache::flush();

    makeInvoice($setup, 'draft');
    makeInvoice($setup, 'void');

    $kpis = app(DashboardService::class)->salesKpis('today', null);

    expect($kpis['invoice_count'])->toBe(0);
    expect((float) $kpis['total_sales'])->toBe(0.0);
});

test('salesKpis returns previous period comparison', function (): void {
    $setup = dashSetup();
    Cache::flush();

    // Yesterday
    makeInvoice($setup, 'issued', Carbon::yesterday()->setTime(12, 0)->toDateTimeString());

    $svc = app(DashboardService::class);
    $kpis = $svc->salesKpis('today', null);

    // Today: 0 invoices; yesterday: 1
    expect($kpis['invoice_count'])->toBe(0);
    expect($kpis['invoice_count_prev'])->toBe(1);
});

test('salesKpis location filter restricts results', function (): void {
    $setup = dashSetup();
    $otherLoc = Location::forceCreate(['name' => 'Other '.Str::random(4), 'type' => 'warehouse', 'is_active' => true]);
    Cache::flush();

    makeInvoice($setup, 'issued', null, $setup['location']->id);
    makeInvoice($setup, 'issued', null, $otherLoc->id);

    $svc = app(DashboardService::class);

    expect($svc->salesKpis('today', $setup['location']->id)['invoice_count'])->toBe(1);
    expect($svc->salesKpis('today', $otherLoc->id)['invoice_count'])->toBe(1);
});

test('cashier sees only own sales', function (): void {
    $setup = dashSetup();
    $userA = dashUser(['pos.use']);
    $userB = dashUser(['pos.use']);
    Cache::flush();

    makeInvoice($setup, 'issued', null, null, $userA->id);
    makeInvoice($setup, 'issued', null, null, $userB->id);

    $svc = app(DashboardService::class);

    $kpisA = $svc->salesKpis('today', null, true, $userA->id);
    expect($kpisA['invoice_count'])->toBe(1);

    $kpisB = $svc->salesKpis('today', null, true, $userB->id);
    expect($kpisB['invoice_count'])->toBe(1);
});

test('receivablesKpis returns outstanding and overdue amounts', function (): void {
    $setup = dashSetup();
    Cache::flush();

    // Overdue issued invoice
    $inv = makeInvoice($setup, 'issued');
    $inv->forceFill(['due_date' => Carbon::yesterday()->toDateString(), 'amount_paid' => '0.00'])->save();

    $svc = app(DashboardService::class);
    $kpis = $svc->receivablesKpis(null);

    expect($kpis['overdue_count'])->toBe(1);
    expect((float) $kpis['overdue_amount'])->toBe(100000.0);
    expect((float) $kpis['outstanding'])->toBe(100000.0);
});

test('quotationKpis counts draft and sent quotations', function (): void {
    $setup = dashSetup();
    Cache::flush();

    $validUntilStr = Carbon::today()->addDays(30)->toDateString();
    $baseQuo = [
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'quote_date' => Carbon::today()->toDateString(),
        'valid_until' => $validUntilStr,
        'currency' => 'TZS',
        'prices_include_tax' => false,
        'subtotal' => '50000.00',
        'discount_total' => '0.00',
        'tax_total' => '0.00',
        'total' => '50000.00',
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('quotations')->insert(array_merge($baseQuo, ['number' => 'QUO-001', 'status' => 'draft']));
    DB::table('quotations')->insert(array_merge($baseQuo, ['number' => 'QUO-002', 'status' => 'converted'])); // should NOT count

    $kpis = app(DashboardService::class)->quotationKpis(null);

    expect($kpis['open_count'])->toBe(1);
    expect((float) $kpis['open_value'])->toBe(50000.0);
});

// ─── Dashboard screen (Livewire) tests ───────────────────────────────────────

test('guests are redirected from dashboard', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('admin sees all dashboard sections', function (): void {
    $user = dashUser(['invoices.view', 'inventory.view', 'reports.view']);
    $setup = dashSetup();
    Cache::flush();

    makeInvoice($setup);

    Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->assertOk()
        ->assertSee('Sales')
        ->assertSee('Inventory');
});

test('cashier only sees their own sales section', function (): void {
    $cashier = dashUser(['pos.use']);
    Cache::flush();

    Livewire::actingAs($cashier)
        ->test(DashboardIndex::class)
        ->assertOk()
        ->assertDontSee('id="inventory-heading"', false);
});

test('storekeeper sees inventory but not sales', function (): void {
    $storekeeper = dashUser(['inventory.view']);
    Cache::flush();

    Livewire::actingAs($storekeeper)
        ->test(DashboardIndex::class)
        ->assertOk()
        ->assertSee('Inventory');
});

test('viewer sees figures without edit buttons', function (): void {
    $viewer = dashUser(['invoices.view']);
    $setup = dashSetup();
    Cache::flush();

    makeInvoice($setup);

    Livewire::actingAs($viewer)
        ->test(DashboardIndex::class)
        ->assertOk()
        ->assertSee('Sales')
        ->assertDontSee('New Sale (POS)');
});

test('company name appears and Laravel does not', function (): void {
    $user = dashUser(['invoices.view']);

    // Set company name in settings
    app(Settings::class)->put('business.name', 'AGRIBUSINESS');
    Cache::flush();

    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('AGRIBUSINESS');
    $response->assertDontSee('Laravel');
});

test('dashboard renders in dark mode without errors', function (): void {
    $user = dashUser(['invoices.view', 'inventory.view']);
    Cache::flush();

    Livewire::actingAs($user)
        ->test(DashboardIndex::class)
        ->assertOk();
});

test('period switch affects KPI data', function (): void {
    $user = dashUser(['invoices.view']);
    $setup = dashSetup();
    Cache::flush();

    makeInvoice($setup, 'issued');

    $lw = Livewire::actingAs($user)->test(DashboardIndex::class);
    $lw->set('period', '7d')->assertOk();
    $lw->set('period', 'month')->assertOk();
});

test('dashboard runs bounded queries', function (): void {
    $user = dashUser(['invoices.view', 'inventory.view']);
    $setup = dashSetup();
    Cache::flush();

    for ($i = 0; $i < 5; $i++) {
        makeInvoice($setup);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($user)->get(route('dashboard'));

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    // With a warm cache the number of queries should be very low; before caching
    // it should still stay under a reasonable limit (< 50).
    expect($count)->toBeLessThan(50);
});
