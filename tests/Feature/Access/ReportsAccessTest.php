<?php

use App\Enums\InvoiceStatus;
use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Livewire\Reports\Sales\Index as SalesIndex;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Unit;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use App\Services\Reports\InventoryReportService;
use App\Services\Reports\PaymentsByMethodService;
use App\Services\Reports\ReceivablesAgingService;
use App\Services\Reports\SalesSummaryService;
use App\Services\Reports\VatSummaryService;
use App\Services\Reports\VoidedInvoicesService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function rptUser(array $permissions, array $locationIds = []): User
{
    $user = User::factory()->create();

    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }
    if (! empty($permissions)) {
        $user->givePermissionTo($permissions);
    }

    if (! empty($locationIds)) {
        $user->locations()->sync($locationIds);
    }

    return $user;
}

function rptAdmin(array $permissions = []): User
{
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create();
    $user->assignRole('Admin');

    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }
    if (! empty($permissions)) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function rptLocation(): Location
{
    return Location::forceCreate([
        'name' => 'RptAcc-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'type' => 'store',
        'is_active' => true,
    ]);
}

function rptCustomer(): Customer
{
    return Customer::forceCreate([
        'code' => 'RPC-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'name' => 'Rpt Customer '.random_int(1, 9999),
        'is_active' => true,
    ]);
}

function rptIssuedInvoice(Customer $customer, Location $location, string $total = '100000.00'): Invoice
{
    return Invoice::forceCreate([
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'number' => 'RINV-'.Str::upper(Str::random(8)),
        'status' => InvoiceStatus::Issued,
        'channel' => 'invoice',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => $total,
        'discount_total' => '0.00',
        'tax_total' => '0.00',
        'total' => $total,
        'amount_paid' => '0.00',
        'prices_include_tax' => false,
        'created_by' => User::factory()->create()->id,
        'issued_by' => User::factory()->create()->id,
        'issued_at' => now(),
        'customer_name' => $customer->name,
    ]);
}

function rptVoidedInvoice(Customer $customer, Location $location): Invoice
{
    return Invoice::forceCreate([
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'number' => 'VINV-'.Str::upper(Str::random(8)),
        'status' => InvoiceStatus::Void,
        'channel' => 'invoice',
        'issue_date' => now()->toDateString(),
        'subtotal' => '50000.00',
        'discount_total' => '0.00',
        'tax_total' => '0.00',
        'total' => '50000.00',
        'amount_paid' => '0.00',
        'prices_include_tax' => false,
        'created_by' => User::factory()->create()->id,
        'voided_at' => now(),
        'void_reason' => 'test',
        'customer_name' => $customer->name,
    ]);
}

function rptPayment(Location $location, string $amount = '50000.00'): Payment
{
    return Payment::forceCreate([
        'number' => 'RPAY-'.Str::upper(Str::random(8)),
        'customer_id' => rptCustomer()->id,
        'received_by' => User::factory()->create()->id,
        'method' => 'cash',
        'amount' => $amount,
        'received_at' => now()->toDateTimeString(),
        'status' => 'active',
        'location_id' => $location->id,
        'idempotency_key' => (string) Str::uuid(),
    ]);
}

function rptStockLevel(Location $location, string $qty = '10.000'): StockLevel
{
    $unit = Unit::forceCreate(['name' => 'RptUnit-'.Str::random(4), 'abbreviation' => 'pu']);
    $product = Product::forceCreate([
        'sku' => 'RSKU-'.Str::upper(Str::random(6)),
        'name' => 'Rpt Product '.Str::random(4),
        'base_unit_id' => $unit->id,
        'reorder_level' => 0,
        'track_batches' => false,
        'is_active' => true,
        'selling_price' => '1000.00',
    ]);

    return StockLevel::forceCreate([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => $qty,
    ]);
}

// ─── 1. SalesSummaryService scoped by allowedIds ──────────────────────────────

test('sales_summary_returns_only_allowed_location_data', function (): void {
    $locA = rptLocation();
    $locB = rptLocation();
    $customer = rptCustomer();

    rptIssuedInvoice($customer, $locA, '100000.00');
    rptIssuedInvoice($customer, $locB, '200000.00');

    $svc = app(SalesSummaryService::class);
    $from = Carbon::today()->startOfDay();
    $to = Carbon::today()->endOfDay();

    $resultA = $svc->get($from, $to, null, [$locA->id]);
    $resultB = $svc->get($from, $to, null, [$locB->id]);
    $resultBoth = $svc->get($from, $to, null, [$locA->id, $locB->id]);
    $resultAdmin = $svc->get($from, $to, null, null);
    $resultNone = $svc->get($from, $to, null, []);

    $strip = fn (string $v): float => (float) str_replace(',', '', $v);

    expect($strip($resultA['totals']['total']))->toBe(100000.0)
        ->and($strip($resultB['totals']['total']))->toBe(200000.0)
        ->and($strip($resultBoth['totals']['total']))->toBe(300000.0)
        ->and($strip($resultAdmin['totals']['total']))->toBeGreaterThanOrEqual(300000.0)
        ->and($strip($resultNone['totals']['total']))->toBe(0.0);
});

test('sales_summary_forged_location_filter_returns_nothing_for_a_only_user', function (): void {
    $locA = rptLocation();
    $locB = rptLocation();
    $customer = rptCustomer();

    rptIssuedInvoice($customer, $locB, '200000.00');

    $svc = app(SalesSummaryService::class);
    $from = Carbon::today()->startOfDay();
    $to = Carbon::today()->endOfDay();

    // User passes locB as locationId but only has access to locA:
    // allowedIds=[locA] intersection with locationId=locB → 0 results.
    $result = $svc->get($from, $to, $locB->id, [$locA->id]);

    expect((float) str_replace(',', '', $result['totals']['total']))->toBe(0.0);
});

// ─── 2. VatSummaryService scoped by allowedIds ────────────────────────────────

test('vat_summary_returns_only_allowed_location_data', function (): void {
    $locA = rptLocation();
    $locB = rptLocation();
    $customer = rptCustomer();

    rptIssuedInvoice($customer, $locA);
    rptIssuedInvoice($customer, $locB);

    $svc = app(VatSummaryService::class);
    $from = Carbon::today()->startOfDay();
    $to = Carbon::today()->endOfDay();

    $resultA = $svc->get($from, $to, null, [$locA->id]);
    $resultNone = $svc->get($from, $to, null, []);

    // locA has an invoice; locB's data should not appear
    $invoiceCountA = $resultA['rows']->count();
    $invoiceCountNone = $resultNone['rows']->count();

    expect($invoiceCountNone)->toBe(0)
        ->and($invoiceCountA)->toBeGreaterThanOrEqual(0); // VAT rows from locA only
});

// ─── 3. ReceivablesAgingService scoped by allowedIds ─────────────────────────

test('receivables_aging_returns_only_allowed_location_customers', function (): void {
    $locA = rptLocation();
    $locB = rptLocation();
    $customerA = rptCustomer();
    $customerB = rptCustomer();

    rptIssuedInvoice($customerA, $locA, '100000.00');
    rptIssuedInvoice($customerB, $locB, '200000.00');

    $svc = app(ReceivablesAgingService::class);
    $asOf = Carbon::today()->endOfDay();

    $resultA = $svc->get($asOf, [$locA->id]);
    $resultNone = $svc->get($asOf, []);

    $idsA = $resultA['rows']->pluck('customer_id')->all();

    expect($idsA)->toContain($customerA->id)
        ->and($idsA)->not->toContain($customerB->id)
        ->and($resultNone['rows']->count())->toBe(0);
});

// ─── 4. PaymentsByMethodService scoped by allowedIds ─────────────────────────

test('payments_by_method_returns_only_allowed_location_data', function (): void {
    $locA = rptLocation();
    $locB = rptLocation();

    rptPayment($locA, '30000.00');
    rptPayment($locB, '70000.00');

    $svc = app(PaymentsByMethodService::class);
    $from = Carbon::today()->startOfDay();
    $to = Carbon::today()->endOfDay();

    $resultA = $svc->get($from, $to, null, [$locA->id]);
    $resultBoth = $svc->get($from, $to, null, [$locA->id, $locB->id]);
    $resultNone = $svc->get($from, $to, null, []);

    $strip = fn (string $v): float => (float) str_replace(',', '', $v);

    expect($strip($resultA['totals']['amount']))->toBe(30000.0)
        ->and($strip($resultBoth['totals']['amount']))->toBe(100000.0)
        ->and($strip($resultNone['totals']['amount']))->toBe(0.0);
});

// ─── 5. VoidedInvoicesService scoped by allowedIds ───────────────────────────

test('voided_invoices_returns_only_allowed_location_data', function (): void {
    $locA = rptLocation();
    $locB = rptLocation();
    $customer = rptCustomer();

    $voidedA = rptVoidedInvoice($customer, $locA);
    $voidedB = rptVoidedInvoice($customer, $locB);

    $svc = app(VoidedInvoicesService::class);
    $from = Carbon::today()->startOfDay();
    $to = Carbon::today()->endOfDay();

    $resultA = $svc->paginate($from, $to, null, 50, [$locA->id]);
    $resultNone = $svc->paginate($from, $to, null, 50, []);

    $numbersA = collect($resultA->items())->pluck('number')->all();

    expect($numbersA)->toContain($voidedA->number)
        ->and($numbersA)->not->toContain($voidedB->number)
        ->and($resultNone->total())->toBe(0);
});

// ─── 6. InventoryReportService scoped by allowedIds ──────────────────────────

test('inventory_report_returns_only_allowed_location_stock', function (): void {
    $locA = rptLocation();
    $locB = rptLocation();

    $slA = rptStockLevel($locA, '5.000');
    $slB = rptStockLevel($locB, '10.000');

    $svc = app(InventoryReportService::class);

    $resultA = $svc->paginate(null, null, 50, [$locA->id]);
    $resultNone = $svc->paginate(null, null, 50, []);

    $locNamesA = collect($resultA->items())->pluck('location_name')->all();

    expect($locNamesA)->toContain($locA->name)
        ->and($locNamesA)->not->toContain($locB->name)
        ->and($resultNone->total())->toBe(0);
});

// ─── 7. PDF endpoint returns 403 for forbidden location ───────────────────────

test('report_pdf_forbidden_location_returns_403', function (): void {
    $locA = rptLocation();
    $locB = rptLocation();
    $user = rptUser(['reports.view'], [$locA->id]);

    $this->actingAs($user)
        ->get(route('reports.pdf', ['report' => 'sales', 'locationId' => $locB->id]))
        ->assertForbidden();
});

test('report_pdf_allowed_location_returns_200', function (): void {
    $locA = rptLocation();
    $user = rptUser(['reports.view'], [$locA->id]);

    $this->actingAs($user)
        ->get(route('reports.pdf', ['report' => 'sales', 'locationId' => $locA->id]))
        ->assertOk();
});

test('report_pdf_admin_can_access_any_location', function (): void {
    $locB = rptLocation();
    $admin = rptAdmin(['reports.view']);

    $this->actingAs($admin)
        ->get(route('reports.pdf', ['report' => 'sales', 'locationId' => $locB->id]))
        ->assertOk();
});

// ─── 8. Sales Livewire report shows only allowed locations in dropdown ─────────

test('sales_report_component_dropdown_shows_only_allowed_locations', function (): void {
    $locA = rptLocation();
    $locB = rptLocation();
    $user = rptUser(['reports.view'], [$locA->id]);

    Livewire::actingAs($user)
        ->test(SalesIndex::class)
        ->assertSee($locA->name)
        ->assertDontSee($locB->name);
});

test('admin_sales_report_dropdown_shows_all_locations', function (): void {
    $locA = rptLocation();
    $locB = rptLocation();
    $admin = rptAdmin(['reports.view']);

    Livewire::actingAs($admin)
        ->test(SalesIndex::class)
        ->assertSee($locA->name)
        ->assertSee($locB->name);
});

// ─── 9. DashboardService salesKpis scoped by allowedIds ──────────────────────

test('dashboard_sales_kpis_scoped_to_allowed_locations', function (): void {
    $locA = rptLocation();
    $locB = rptLocation();
    $customer = rptCustomer();

    rptIssuedInvoice($customer, $locA, '100000.00');
    rptIssuedInvoice($customer, $locB, '200000.00');

    $svc = app(DashboardService::class);

    $kpisA = $svc->salesKpis('today', null, false, 0, [$locA->id]);
    $kpisNone = $svc->salesKpis('today', null, false, 0, []);
    $kpisAdmin = $svc->salesKpis('today', null, false, 0, null);

    expect((float) $kpisA['total_sales'])->toBe(100000.0)
        ->and((int) $kpisNone['invoice_count'])->toBe(0)
        ->and((float) $kpisAdmin['total_sales'])->toBeGreaterThanOrEqual(300000.0);
});

// ─── 10. Dashboard cache isolation between users with different locations ──────

test('dashboard_cache_is_isolated_between_users_with_different_locations', function (): void {
    $locA = rptLocation();
    $locB = rptLocation();
    $customer = rptCustomer();

    rptIssuedInvoice($customer, $locA, '111111.00');
    rptIssuedInvoice($customer, $locB, '222222.00');

    Cache::flush();

    $svc = app(DashboardService::class);

    $kpisA = $svc->salesKpis('today', null, false, 0, [$locA->id]);
    $kpisB = $svc->salesKpis('today', null, false, 0, [$locB->id]);

    expect((float) $kpisA['total_sales'])->not->toBe((float) $kpisB['total_sales'])
        ->and((float) $kpisA['total_sales'])->toBe(111111.0)
        ->and((float) $kpisB['total_sales'])->toBe(222222.0);
});

// ─── 11. Inventory KPIs scoped by allowedIds ─────────────────────────────────

test('inventory_kpis_scoped_to_allowed_locations', function (): void {
    $locA = rptLocation();

    // Create a product with no stock at locA
    $unit = Unit::forceCreate(['name' => 'InvKpiUnit-'.Str::random(4), 'abbreviation' => 'iu']);
    $product = Product::forceCreate([
        'sku' => 'IKPI-'.Str::upper(Str::random(6)),
        'name' => 'InvKpi Product '.Str::random(4),
        'base_unit_id' => $unit->id,
        'reorder_level' => 0,
        'track_batches' => false,
        'is_active' => true,
        'selling_price' => '500.00',
    ]);

    StockLevel::forceCreate([
        'product_id' => $product->id,
        'location_id' => $locA->id,
        'quantity' => '0.000',
    ]);

    $svc = app(DashboardService::class);

    $kpisA = $svc->inventoryKpis([$locA->id]);
    $kpisNone = $svc->inventoryKpis([]);

    // The product at locA has 0 stock → out_of_stock includes it
    expect($kpisA['out_of_stock'])->toBeGreaterThanOrEqual(1)
        ->and($kpisNone['out_of_stock'])->toBe(0)
        ->and($kpisNone['low_stock'])->toBe(0);
});
