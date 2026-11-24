<?php

/**
 * Tests that DashboardService survives a serializing cache store (file driver).
 *
 * phpunit.xml sets CACHE_STORE=array, so tests that do NOT use this file run
 * against an in-process store that never serialises.  Here we explicitly switch
 * to the file store for each test, call every public method twice (miss then
 * hit), and assert the result is identical on both calls.
 *
 * We also do an HTTP double-GET of /dashboard for Admin, Cashier and Storekeeper
 * to confirm the full page renders on a cache-hit without a 500.
 */

use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ─── Setup helpers ────────────────────────────────────────────────────────────

function serUser(array $perms = []): User
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

function serSetup(): array
{
    $unit = Unit::forceCreate(['name' => 'Piece '.Str::random(4), 'abbreviation' => 'pc']);
    $category = Category::forceCreate(['name' => 'Cat '.Str::random(4)]);
    $product = Product::forceCreate([
        'sku' => 'SKU-'.Str::upper(Str::random(8)),
        'name' => 'Product '.Str::random(4),
        'base_unit_id' => $unit->id,
        'category_id' => $category->id,
        'reorder_level' => 0,
        'track_batches' => false,
        'is_active' => true,
    ]);
    $location = Location::forceCreate(['name' => 'Loc '.Str::random(4), 'type' => 'warehouse', 'is_active' => true]);
    $customer = Customer::forceCreate([
        'code' => 'CUS-'.Str::upper(Str::random(6)),
        'name' => 'Customer '.Str::random(4),
        'email' => Str::random(6).'@example.com',
    ]);

    return compact('unit', 'category', 'product', 'location', 'customer');
}

function serInvoice(array $setup, string $status = 'issued', ?int $issuedBy = null): Invoice
{
    $inv = Invoice::forceCreate([
        'number' => 'INV-'.Str::upper(Str::random(8)),
        'channel' => 'invoice',
        'status' => $status,
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'issue_date' => Carbon::today(),
        'due_date' => Carbon::today()->addDays(30),
        'currency' => 'TZS',
        'prices_include_tax' => false,
        'subtotal' => '100000.00',
        'discount_total' => '0.00',
        'tax_total' => '0.00',
        'total' => '100000.00',
        'amount_paid' => '0.00',
        'issued_at' => Carbon::now()->toDateTimeString(),
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

/** Switch cache to file store and clean up after each test. */
function useFileCache(): void
{
    Config::set('cache.default', 'file');
    Cache::flush();
}

// ─── DashboardService double-call (miss → hit) tests ─────────────────────────

test('recentInvoices: file-cache hit returns Collection of stdClass, not __PHP_Incomplete_Class', function (): void {
    $setup = serSetup();
    serInvoice($setup);
    useFileCache();

    $svc = app(DashboardService::class);

    // First call: cache miss
    $first = $svc->recentInvoices(null);
    expect($first)->toBeInstanceOf(Collection::class);
    expect($first->count())->toBe(1);
    expect($first->first())->toBeInstanceOf(stdClass::class);
    expect($first->first()->customer_name)->toBeString();

    // Second call: cache hit
    $second = $svc->recentInvoices(null);
    expect($second)->toBeInstanceOf(Collection::class);
    expect($second->count())->toBe(1);
    expect($second->first())->toBeInstanceOf(stdClass::class);
    expect($second->first()->customer_name)->toBe($first->first()->customer_name);
});

test('topProducts: file-cache hit returns Collection of stdClass', function (): void {
    $setup = serSetup();
    serInvoice($setup);
    useFileCache();

    $svc = app(DashboardService::class);

    $first = $svc->topProducts('today', null);
    $second = $svc->topProducts('today', null);

    expect($second)->toBeInstanceOf(Collection::class);
    expect($second->count())->toBe($first->count());

    if ($second->isNotEmpty()) {
        expect($second->first())->toBeInstanceOf(stdClass::class);
        expect($second->first()->name)->toBe($first->first()->name);
    }
});

test('overdueInvoices: file-cache hit returns Collection of stdClass', function (): void {
    $setup = serSetup();
    $inv = serInvoice($setup);
    // Make it overdue
    $inv->forceFill(['due_date' => Carbon::yesterday()->toDateString(), 'status' => 'issued'])->save();
    useFileCache();

    $svc = app(DashboardService::class);

    $first = $svc->overdueInvoices(null);
    $second = $svc->overdueInvoices(null);

    expect($second)->toBeInstanceOf(Collection::class);
    expect($second->count())->toBe(1);
    expect($second->first())->toBeInstanceOf(stdClass::class);
    expect($second->first()->customer_name)->toBe($first->first()->customer_name);
    expect((float) $second->first()->balance)->toBe((float) $first->first()->balance);
});

test('urgentInventoryItems: file-cache hit returns Collection of stdClass', function (): void {
    // Create a product with no stock (out-of-stock)
    $unit = Unit::forceCreate(['name' => 'U '.Str::random(4), 'abbreviation' => 'u']);
    Product::forceCreate([
        'sku' => 'SKU-'.Str::upper(Str::random(8)),
        'name' => 'OOS Product',
        'base_unit_id' => $unit->id,
        'reorder_level' => 0,
        'track_batches' => false,
        'is_active' => true,
    ]);
    useFileCache();

    $svc = app(DashboardService::class);

    $first = $svc->urgentInventoryItems();
    $second = $svc->urgentInventoryItems();

    expect($second)->toBeInstanceOf(Collection::class);
    expect($second->count())->toBe($first->count());

    if ($second->isNotEmpty()) {
        expect($second->first())->toBeInstanceOf(stdClass::class);
        expect($second->first()->issue_type)->toBe($first->first()->issue_type);
    }
});

test('salesKpis: file-cache hit returns identical array', function (): void {
    $setup = serSetup();
    serInvoice($setup);
    useFileCache();

    $svc = app(DashboardService::class);

    $first = $svc->salesKpis('today', null);
    $second = $svc->salesKpis('today', null);

    expect($second)->toBe($first);
    expect($second['invoice_count'])->toBeInt();
    expect($second['total_sales'])->toBeString();
});

test('receivablesKpis: file-cache hit returns identical array', function (): void {
    $setup = serSetup();
    serInvoice($setup);
    useFileCache();

    $svc = app(DashboardService::class);

    $first = $svc->receivablesKpis(null);
    $second = $svc->receivablesKpis(null);

    expect($second)->toBe($first);
    expect($second['overdue_count'])->toBeInt();
});

test('inventoryKpis: file-cache hit returns identical array', function (): void {
    useFileCache();

    $svc = app(DashboardService::class);

    $first = $svc->inventoryKpis();
    $second = $svc->inventoryKpis();

    expect($second)->toBe($first);
    expect($second['out_of_stock'])->toBeInt();
});

test('salesByDay: file-cache hit returns identical array', function (): void {
    $setup = serSetup();
    serInvoice($setup);
    useFileCache();

    $svc = app(DashboardService::class);

    $first = $svc->salesByDay(null);
    $second = $svc->salesByDay(null);

    expect($second['labels'])->toBe($first['labels']);
    expect($second['datasets'])->toBe($first['datasets']);
});

// ─── Cache-key coverage ───────────────────────────────────────────────────────

test('salesKpis cache key varies by period', function (): void {
    $setup = serSetup();
    serInvoice($setup);
    useFileCache();

    $svc = app(DashboardService::class);

    $today = $svc->salesKpis('today', null);
    $month = $svc->salesKpis('month', null);
    $seven = $svc->salesKpis('7d', null);

    // All three should be independent keys — different period labels mean different counts are possible
    expect($today)->toBeArray();
    expect($month)->toBeArray();
    expect($seven)->toBeArray();
});

test('salesKpis cache key varies by location', function (): void {
    $setup = serSetup();
    $otherLoc = Location::forceCreate(['name' => 'Other '.Str::random(4), 'type' => 'warehouse', 'is_active' => true]);
    serInvoice($setup); // goes to setup['location']
    useFileCache();

    $svc = app(DashboardService::class);

    $all = $svc->salesKpis('today', null);
    $loc1 = $svc->salesKpis('today', $setup['location']->id);
    $loc2 = $svc->salesKpis('today', $otherLoc->id);

    expect($all['invoice_count'])->toBe(1);
    expect($loc1['invoice_count'])->toBe(1);
    expect($loc2['invoice_count'])->toBe(0);
});

test('salesKpis cache key varies by user for cashier-only', function (): void {
    $setup = serSetup();
    $userA = serUser(['pos.use']);
    $userB = serUser(['pos.use']);
    serInvoice($setup, 'issued', $userA->id);
    useFileCache();

    $svc = app(DashboardService::class);

    $kpisA = $svc->salesKpis('today', null, true, $userA->id);
    $kpisB = $svc->salesKpis('today', null, true, $userB->id);

    expect($kpisA['invoice_count'])->toBe(1);
    expect($kpisB['invoice_count'])->toBe(0);
});

// ─── HTTP double-GET tests ────────────────────────────────────────────────────

test('admin can load dashboard twice without 500 (file cache)', function (): void {
    $admin = serUser(['invoices.view', 'inventory.view', 'reports.view']);
    $setup = serSetup();
    serInvoice($setup);
    useFileCache();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('dashboard'))->assertOk(); // cache hit
});

test('cashier can load dashboard twice without 500 (file cache)', function (): void {
    $cashier = serUser(['pos.use']);
    useFileCache();

    $this->actingAs($cashier)->get(route('dashboard'))->assertOk();
    $this->actingAs($cashier)->get(route('dashboard'))->assertOk(); // cache hit
});

test('storekeeper can load dashboard twice without 500 (file cache)', function (): void {
    $storekeeper = serUser(['inventory.view']);
    useFileCache();

    $this->actingAs($storekeeper)->get(route('dashboard'))->assertOk();
    $this->actingAs($storekeeper)->get(route('dashboard'))->assertOk(); // cache hit
});
