<?php

use App\Enums\InvoiceStatus;
use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Livewire\Actions\Payments\RecordPayment;
use App\Livewire\Actions\Payments\ReversePayment;
use App\Livewire\Reports\Inventory\Index as ReportInventoryIndex;
use App\Livewire\Reports\Payments\Index as ReportPaymentsIndex;
use App\Livewire\Reports\Receivables\Index as ReportReceivablesIndex;
use App\Livewire\Reports\Sales\Index as ReportSalesIndex;
use App\Livewire\Reports\Vat\Index as ReportVatIndex;
use App\Livewire\Reports\VoidedInvoices\Index as ReportVoidedInvoicesIndex;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\PaymentsByMethodService;
use App\Services\Reports\PosSessionsService;
use App\Services\Reports\VoidedInvoicesService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function reportUser(array $permissions): User
{
    $user = User::factory()->create(['name' => 'Report User '.random_int(1, 9999)]);
    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }
    $user->givePermissionTo($permissions);

    return $user;
}

function reportAdmin(): User
{
    return reportUser(['reports.view', 'reports.export']);
}

function reportViewer(): User
{
    return reportUser(['reports.view']);
}

function reportCashier(): User
{
    return reportUser(['invoices.view', 'payments.view']);
}

function reportLocation(): Location
{
    return Location::forceCreate([
        'name' => 'ReportLoc-'.random_int(1, 9999),
        'type' => 'store',
        'is_active' => true,
    ]);
}

function reportCustomer(): Customer
{
    return Customer::forceCreate([
        'code' => 'RC-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'name' => 'Report Customer '.random_int(1, 9999),
        'type' => 'business',
        'credit_limit' => '50000.00',
        'payment_terms_days' => 30,
        'is_active' => true,
        'is_walk_in' => false,
    ]);
}

/**
 * Directly insert a posted invoice row (bypasses Actions for test simplicity).
 */
function reportInvoice(
    Customer $customer,
    Location $location,
    string $total,
    string $taxTotal = '0.00',
    string $discountTotal = '0.00',
    InvoiceStatus $status = InvoiceStatus::Issued,
    ?string $issueDate = null,
    string $channel = 'invoice',
): Invoice {
    $subtotal = bcsub(bcadd($total, $discountTotal, 2), $taxTotal, 2);

    return Invoice::forceCreate([
        'number' => 'TEST-'.str_pad((string) random_int(1, 999999), 7, '0', STR_PAD_LEFT),
        'channel' => $channel,
        'status' => $status,
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'issue_date' => $issueDate ?? now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'currency' => 'TZS',
        'prices_include_tax' => false,
        'subtotal' => $subtotal,
        'discount_total' => $discountTotal,
        'tax_total' => $taxTotal,
        'total' => $total,
        'amount_paid' => '0.00',
        'created_by' => auth()->id() ?? User::factory()->create()->id,
    ]);
}

// ─── Authorization ─────────────────────────────────────────────────────────────

test('cashier cannot access any report screen', function () {
    $cashier = reportCashier();

    Livewire::actingAs($cashier)->test(ReportSalesIndex::class)->assertStatus(403);
    Livewire::actingAs($cashier)->test(ReportVatIndex::class)->assertStatus(403);
    Livewire::actingAs($cashier)->test(ReportReceivablesIndex::class)->assertStatus(403);
    Livewire::actingAs($cashier)->test(ReportPaymentsIndex::class)->assertStatus(403);
    Livewire::actingAs($cashier)->test(ReportInventoryIndex::class)->assertStatus(403);
    Livewire::actingAs($cashier)->test(ReportVoidedInvoicesIndex::class)->assertStatus(403);
});

test('viewer with reports.view can access report screens', function () {
    $viewer = reportViewer();

    Livewire::actingAs($viewer)->test(ReportSalesIndex::class)->assertOk();
    Livewire::actingAs($viewer)->test(ReportVatIndex::class)->assertOk();
    Livewire::actingAs($viewer)->test(ReportReceivablesIndex::class)->assertOk();
    Livewire::actingAs($viewer)->test(ReportPaymentsIndex::class)->assertOk();
    Livewire::actingAs($viewer)->test(ReportInventoryIndex::class)->assertOk();
    Livewire::actingAs($viewer)->test(ReportVoidedInvoicesIndex::class)->assertOk();
});

test('viewer without reports.export cannot trigger export actions', function () {
    $viewer = reportViewer();

    Livewire::actingAs($viewer)->test(ReportSalesIndex::class)
        ->call('exportXlsx')
        ->assertStatus(403);

    Livewire::actingAs($viewer)->test(ReportSalesIndex::class)
        ->call('exportCsv')
        ->assertStatus(403);
});

test('admin with reports.export can call export actions', function () {
    $admin = reportAdmin();

    // Returns a file download response — expect no 403
    $response = Livewire::actingAs($admin)->test(ReportSalesIndex::class)
        ->call('exportXlsx');

    $response->assertStatus(200);
});

// ─── EnsureSystemRoles ─────────────────────────────────────────────────────────

test('EnsureSystemRoles grants reports.view to admin storekeeper and viewer but not cashier', function () {
    // Create all roles/permissions
    $admin = User::factory()->create();
    $storekeeper = User::factory()->create();
    $cashier = User::factory()->create();
    $viewer = User::factory()->create();

    app(EnsureSystemRoles::class)();

    $admin->assignRole('Admin');
    $storekeeper->assignRole('Storekeeper');
    $cashier->assignRole('Cashier');
    $viewer->assignRole('Viewer');

    expect($admin->hasPermissionTo('reports.view'))->toBeTrue();
    expect($admin->hasPermissionTo('reports.export'))->toBeTrue();
    expect($storekeeper->hasPermissionTo('reports.view'))->toBeTrue();
    expect($storekeeper->hasPermissionTo('reports.export'))->toBeFalse();
    expect($cashier->hasPermissionTo('reports.view'))->toBeFalse();
    expect($cashier->hasPermissionTo('reports.export'))->toBeFalse();
    expect($viewer->hasPermissionTo('reports.view'))->toBeTrue();
    expect($viewer->hasPermissionTo('reports.export'))->toBeFalse();
});

// ─── Sales Summary ─────────────────────────────────────────────────────────────

test('sales summary shows correct totals for posted invoices', function () {
    $admin = reportAdmin();
    $location = reportLocation();
    $customer = reportCustomer();

    $today = now()->toDateString();

    // Three posted invoices
    reportInvoice($customer, $location, '1000.00', '180.00', '0.00', InvoiceStatus::Issued, $today);
    reportInvoice($customer, $location, '2000.00', '360.00', '0.00', InvoiceStatus::PartiallyPaid, $today);
    reportInvoice($customer, $location, '500.00', '0.00', '0.00', InvoiceStatus::Paid, $today);

    // Draft and void should be excluded
    reportInvoice($customer, $location, '9999.00', '0.00', '0.00', InvoiceStatus::Draft, $today);
    reportInvoice($customer, $location, '9999.00', '0.00', '0.00', InvoiceStatus::Void, $today);

    Livewire::actingAs($admin)
        ->test(ReportSalesIndex::class)
        ->set('dateFrom', $today)
        ->set('dateTo', $today)
        ->assertViewHas('totals', function (array $totals) {
            return $totals['total'] === '3,500.00'
                && $totals['tax_total'] === '540.00';
        });
});

test('sales summary excludes invoices outside the date range', function () {
    $admin = reportAdmin();
    $location = reportLocation();
    $customer = reportCustomer();

    $yesterday = now()->subDay()->toDateString();
    $today = now()->toDateString();

    reportInvoice($customer, $location, '5000.00', '0.00', '0.00', InvoiceStatus::Issued, $yesterday);
    reportInvoice($customer, $location, '1000.00', '0.00', '0.00', InvoiceStatus::Issued, $today);

    Livewire::actingAs($admin)
        ->test(ReportSalesIndex::class)
        ->set('dateFrom', $today)
        ->set('dateTo', $today)
        ->assertViewHas('totals', function (array $totals) {
            return $totals['total'] === '1,000.00';
        });
});

test('sales summary location filter only includes matching location', function () {
    $admin = reportAdmin();
    $loc1 = reportLocation();
    $loc2 = reportLocation();
    $customer = reportCustomer();
    $today = now()->toDateString();

    reportInvoice($customer, $loc1, '1000.00', '0.00', '0.00', InvoiceStatus::Issued, $today);
    reportInvoice($customer, $loc2, '9999.00', '0.00', '0.00', InvoiceStatus::Issued, $today);

    Livewire::actingAs($admin)
        ->test(ReportSalesIndex::class)
        ->set('dateFrom', $today)
        ->set('dateTo', $today)
        ->set('locationFilter', (string) $loc1->id)
        ->assertViewHas('totals', function (array $totals) {
            return $totals['total'] === '1,000.00';
        });
});

// ─── VAT Summary ───────────────────────────────────────────────────────────────

test('vat summary correctly separates standard and exempt tax amounts', function () {
    $admin = reportAdmin();
    $location = reportLocation();
    $customer = reportCustomer();
    $today = now()->toDateString();

    $unit = Unit::factory()->create();
    $stdProduct = Product::factory()->create(['base_unit_id' => $unit->id, 'tax_type' => 'standard', 'selling_price' => '820.00']);
    $exmProduct = Product::factory()->create(['base_unit_id' => $unit->id, 'tax_type' => 'exempt', 'selling_price' => '500.00']);

    // Standard invoice: 180 VAT on 820 taxable → total 1000
    Invoice::forceCreate([
        'number' => 'VAT-STD-'.random_int(1, 9999),
        'channel' => 'invoice',
        'status' => InvoiceStatus::Issued,
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'issue_date' => $today,
        'due_date' => now()->addDays(30)->toDateString(),
        'currency' => 'TZS',
        'prices_include_tax' => false,
        'subtotal' => '820.00',
        'discount_total' => '0.00',
        'tax_total' => '180.00',
        'total' => '1000.00',
        'amount_paid' => '0.00',
        'created_by' => $admin->id,
    ])->items()->create([
        'product_id' => $stdProduct->id,
        'unit_id' => $unit->id,
        'description' => 'Standard item',
        'quantity' => '1.000',
        'base_quantity' => '1.000',
        'unit_price' => '820.00',
        'discount_amount' => '0.00',
        'tax_type' => 'standard',
        'tax_rate' => '18.00',
        'line_tax' => '180.00',
        'line_total' => '1000.00',
    ]);

    // Exempt invoice: 0 VAT, full amount exempt
    Invoice::forceCreate([
        'number' => 'VAT-EXM-'.random_int(1, 9999),
        'channel' => 'invoice',
        'status' => InvoiceStatus::Issued,
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'issue_date' => $today,
        'due_date' => now()->addDays(30)->toDateString(),
        'currency' => 'TZS',
        'prices_include_tax' => false,
        'subtotal' => '500.00',
        'discount_total' => '0.00',
        'tax_total' => '0.00',
        'total' => '500.00',
        'amount_paid' => '0.00',
        'created_by' => $admin->id,
    ])->items()->create([
        'product_id' => $exmProduct->id,
        'unit_id' => $unit->id,
        'description' => 'Exempt item',
        'quantity' => '1.000',
        'base_quantity' => '1.000',
        'unit_price' => '500.00',
        'discount_amount' => '0.00',
        'tax_type' => 'exempt',
        'tax_rate' => '0.00',
        'line_tax' => '0.00',
        'line_total' => '500.00',
    ]);

    Livewire::actingAs($admin)
        ->test(ReportVatIndex::class)
        ->set('dateFrom', $today)
        ->set('dateTo', $today)
        ->assertViewHas('totals', function (array $totals) {
            return $totals['vat_amount'] === '180.00'
                && $totals['exempt_amount'] === '500.00'
                && $totals['taxable_amount'] === '820.00'
                && $totals['gross_total'] === '1,500.00';
        });
});

// ─── Receivables Aging ─────────────────────────────────────────────────────────

test('receivables aging buckets by days overdue from as-of date', function () {
    $admin = reportAdmin();
    $location = reportLocation();
    $customer = reportCustomer();

    $asOf = now()->toDateString();

    // Current: due in future
    Invoice::forceCreate([
        'number' => 'AGE-CUR-'.random_int(1, 9999),
        'channel' => 'invoice', 'status' => InvoiceStatus::Issued,
        'customer_id' => $customer->id, 'location_id' => $location->id,
        'issue_date' => now()->subDays(10)->toDateString(),
        'due_date' => now()->addDays(5)->toDateString(),
        'currency' => 'TZS', 'prices_include_tax' => false,
        'subtotal' => '100.00', 'discount_total' => '0.00', 'tax_total' => '0.00',
        'total' => '100.00', 'amount_paid' => '0.00',
        'created_by' => $admin->id,
    ]);

    // 31–60 days: due 45 days ago
    Invoice::forceCreate([
        'number' => 'AGE-3160-'.random_int(1, 9999),
        'channel' => 'invoice', 'status' => InvoiceStatus::Issued,
        'customer_id' => $customer->id, 'location_id' => $location->id,
        'issue_date' => now()->subDays(50)->toDateString(),
        'due_date' => now()->subDays(45)->toDateString(),
        'currency' => 'TZS', 'prices_include_tax' => false,
        'subtotal' => '200.00', 'discount_total' => '0.00', 'tax_total' => '0.00',
        'total' => '200.00', 'amount_paid' => '0.00',
        'created_by' => $admin->id,
    ]);

    // 90+ days: due 100 days ago
    Invoice::forceCreate([
        'number' => 'AGE-90-'.random_int(1, 9999),
        'channel' => 'invoice', 'status' => InvoiceStatus::Issued,
        'customer_id' => $customer->id, 'location_id' => $location->id,
        'issue_date' => now()->subDays(110)->toDateString(),
        'due_date' => now()->subDays(100)->toDateString(),
        'currency' => 'TZS', 'prices_include_tax' => false,
        'subtotal' => '400.00', 'discount_total' => '0.00', 'tax_total' => '0.00',
        'total' => '400.00', 'amount_paid' => '0.00',
        'created_by' => $admin->id,
    ]);

    Livewire::actingAs($admin)
        ->test(ReportReceivablesIndex::class)
        ->set('asOf', $asOf)
        ->assertViewHas('rows', function ($rows) use ($customer) {
            $row = $rows->firstWhere('customer_id', $customer->id);
            expect($row)->not->toBeNull();
            expect((float) $row->current_amount)->toBe(100.0);
            expect((float) $row->days_31_60)->toBe(200.0);
            expect((float) $row->days_90_plus)->toBe(400.0);

            return true;
        });
});

test('receivables aging excludes fully paid invoices', function () {
    $admin = reportAdmin();
    $location = reportLocation();
    $customer = reportCustomer();

    // Paid invoice — should not appear
    Invoice::forceCreate([
        'number' => 'AGE-PAID-'.random_int(1, 9999),
        'channel' => 'invoice', 'status' => InvoiceStatus::Paid,
        'customer_id' => $customer->id, 'location_id' => $location->id,
        'issue_date' => now()->subDays(5)->toDateString(),
        'due_date' => now()->addDays(25)->toDateString(),
        'currency' => 'TZS', 'prices_include_tax' => false,
        'subtotal' => '1000.00', 'discount_total' => '0.00', 'tax_total' => '0.00',
        'total' => '1000.00', 'amount_paid' => '1000.00',
        'created_by' => $admin->id,
    ]);

    Livewire::actingAs($admin)
        ->test(ReportReceivablesIndex::class)
        ->set('asOf', now()->toDateString())
        ->assertViewHas('rows', function ($rows) use ($customer) {
            return $rows->where('customer_id', $customer->id)->isEmpty();
        });
});

test('aging bucket boundary: invoice due exactly on as-of date is current', function () {
    $admin = reportAdmin();
    $location = reportLocation();
    $customer = reportCustomer();
    $today = now()->toDateString();

    Invoice::forceCreate([
        'number' => 'AGE-EXACT-'.random_int(1, 9999),
        'channel' => 'invoice', 'status' => InvoiceStatus::Issued,
        'customer_id' => $customer->id, 'location_id' => $location->id,
        'issue_date' => now()->subDays(10)->toDateString(),
        'due_date' => $today,
        'currency' => 'TZS', 'prices_include_tax' => false,
        'subtotal' => '300.00', 'discount_total' => '0.00', 'tax_total' => '0.00',
        'total' => '300.00', 'amount_paid' => '0.00',
        'created_by' => $admin->id,
    ]);

    Livewire::actingAs($admin)
        ->test(ReportReceivablesIndex::class)
        ->set('asOf', $today)
        ->assertViewHas('rows', function ($rows) use ($customer) {
            $row = $rows->firstWhere('customer_id', $customer->id);
            expect($row)->not->toBeNull();
            // Due today = current bucket
            expect((float) $row->current_amount)->toBe(300.0);
            expect((float) $row->days_31_60)->toBe(0.0);
            expect((float) $row->days_90_plus)->toBe(0.0);

            return true;
        });
});

// ─── Voided Invoices ───────────────────────────────────────────────────────────

test('voided invoices report shows only voided invoices within range', function () {
    $admin = reportAdmin();
    $location = reportLocation();
    $customer = reportCustomer();
    $today = now()->toDateString();

    // Void invoice voided today
    $void = Invoice::forceCreate([
        'number' => 'VOID-TEST-'.random_int(1, 9999),
        'channel' => 'invoice', 'status' => InvoiceStatus::Void,
        'customer_id' => $customer->id, 'location_id' => $location->id,
        'issue_date' => now()->subDays(5)->toDateString(),
        'voided_at' => now(),
        'void_reason' => 'Test void',
        'currency' => 'TZS', 'prices_include_tax' => false,
        'subtotal' => '1500.00', 'discount_total' => '0.00', 'tax_total' => '0.00',
        'total' => '1500.00', 'amount_paid' => '0.00',
        'created_by' => $admin->id,
    ]);

    // Issued invoice — should not appear
    reportInvoice($customer, $location, '9999.00', '0.00', '0.00', InvoiceStatus::Issued, $today);

    Livewire::actingAs($admin)
        ->test(ReportVoidedInvoicesIndex::class)
        ->set('dateFrom', $today)
        ->set('dateTo', $today)
        ->assertViewHas('invoices', function ($invoices) use ($void) {
            return $invoices->count() === 1
                && (int) $invoices->first()->id === $void->id;
        });
});

test('voided invoices report excludes invoices voided outside date range', function () {
    $admin = reportAdmin();
    $location = reportLocation();
    $customer = reportCustomer();

    // Voided yesterday — outside today's range
    Invoice::forceCreate([
        'number' => 'VOID-YEST-'.random_int(1, 9999),
        'channel' => 'invoice', 'status' => InvoiceStatus::Void,
        'customer_id' => $customer->id, 'location_id' => $location->id,
        'issue_date' => now()->subDays(10)->toDateString(),
        'voided_at' => now()->subDay(),
        'void_reason' => 'Old void',
        'currency' => 'TZS', 'prices_include_tax' => false,
        'subtotal' => '500.00', 'discount_total' => '0.00', 'tax_total' => '0.00',
        'total' => '500.00', 'amount_paid' => '0.00',
        'created_by' => $admin->id,
    ]);

    $today = now()->toDateString();

    Livewire::actingAs($admin)
        ->test(ReportVoidedInvoicesIndex::class)
        ->set('dateFrom', $today)
        ->set('dateTo', $today)
        ->assertViewHas('invoices', function ($invoices) {
            return $invoices->count() === 0;
        });
});

// ─── Inventory Report ──────────────────────────────────────────────────────────

test('inventory report shows correct stock value', function () {
    $admin = reportAdmin();
    $location = reportLocation();
    $unit = Unit::factory()->create();

    $product = Product::factory()->create([
        'base_unit_id' => $unit->id,
        'selling_price' => '50.00',
        'reorder_level' => '5.000',
    ]);

    StockLevel::forceCreate([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '10.000',
    ]);

    Livewire::actingAs($admin)
        ->test(ReportInventoryIndex::class)
        ->set('locationFilter', (string) $location->id)
        ->assertViewHas('totals', function (array $totals) {
            // 10 × 50 = 500
            return $totals['total_value'] === '500.00';
        });
});

// ─── PDF Export Routes ─────────────────────────────────────────────────────────

test('report pdf route returns 403 for cashier', function () {
    $cashier = reportCashier();

    $this->actingAs($cashier)
        ->get(route('reports.pdf', ['report' => 'sales']))
        ->assertStatus(403);
});

test('report pdf route returns 404 for unknown report slug', function () {
    $admin = reportAdmin();

    $this->actingAs($admin)
        ->get(route('reports.pdf', ['report' => 'unknown-report']))
        ->assertStatus(404);
});

test('report pdf route renders a PDF for sales report', function () {
    $admin = reportAdmin();
    $today = now()->toDateString();

    $response = $this->actingAs($admin)
        ->get(route('reports.pdf', ['report' => 'sales', 'dateFrom' => $today, 'dateTo' => $today]));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->content())->toStartWith('%PDF');
});

test('report pdf route renders a PDF for inventory report', function () {
    $admin = reportAdmin();

    $response = $this->actingAs($admin)
        ->get(route('reports.pdf', ['report' => 'inventory']));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->content())->toStartWith('%PDF');
});

// ─── Payments by Method: RecordPayment / ReversePayment ────────────────────────

test('payments by method shows active payment amount and excludes reversed payment', function () {
    $tz = 'Africa/Dar_es_Salaam';
    $today = now($tz)->toDateString();

    $admin = reportAdmin();
    foreach (['payments.create', 'payments.reverse'] as $perm) {
        Permission::findOrCreate($perm, 'web');
    }
    $admin->givePermissionTo(['payments.create', 'payments.reverse']);

    $location = reportLocation();
    $customer = reportCustomer();
    $invoice = reportInvoice($customer, $location, '2500.00', '0.00', '0.00', InvoiceStatus::Issued, $today);

    $this->actingAs($admin);

    $payment = app(RecordPayment::class)([
        'customer_id' => $customer->id,
        'method' => 'mobile_money',
        'amount' => '2500.00',
        'received_at' => $today,
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '2500.00']],
    ], (string) Str::uuid());

    $from = Carbon::parse($today, $tz)->startOfDay();
    $to = Carbon::parse($today, $tz)->endOfDay();

    // Active: payment appears under the location filter
    $result = app(PaymentsByMethodService::class)->get($from, $to, $location->id);
    $mmRow = $result['rows']->firstWhere('method', 'mobile_money');
    expect($mmRow)->not->toBeNull();
    expect(number_format((float) $mmRow->amount, 2))->toBe('2,500.00');
    expect((int) $mmRow->payment_count)->toBe(1);

    // Reverse it
    app(ReversePayment::class)($payment, 'Test reversal');

    // Reversed: location-filtered total drops to zero
    $result2 = app(PaymentsByMethodService::class)->get($from, $to, $location->id);
    expect((float) $result2['totals']['amount'])->toBe(0.0);
    expect((int) $result2['totals']['payment_count'])->toBe(0);
});

test('payments by method location filter includes back-office payment via invoice location', function () {
    $tz = 'Africa/Dar_es_Salaam';
    $today = now($tz)->toDateString();

    $admin = reportAdmin();
    Permission::findOrCreate('payments.create', 'web');
    $admin->givePermissionTo(['payments.create']);

    $rufiji = reportLocation();
    $other = reportLocation();
    $customer = reportCustomer();

    $rufijInvoice = reportInvoice($customer, $rufiji, '3000.00', '0.00', '0.00', InvoiceStatus::Issued, $today);
    $otherInvoice = reportInvoice($customer, $other, '999.00', '0.00', '0.00', InvoiceStatus::Issued, $today);

    $this->actingAs($admin);

    // Back-office payment (no pos_session_id) for Rufiji invoice
    app(RecordPayment::class)([
        'customer_id' => $customer->id,
        'method' => 'bank_transfer',
        'amount' => '3000.00',
        'received_at' => $today,
        'allocations' => [['invoice_id' => $rufijInvoice->id, 'amount' => '3000.00']],
    ], (string) Str::uuid());

    // Back-office payment for Other location invoice
    app(RecordPayment::class)([
        'customer_id' => $customer->id,
        'method' => 'bank_transfer',
        'amount' => '999.00',
        'received_at' => $today,
        'allocations' => [['invoice_id' => $otherInvoice->id, 'amount' => '999.00']],
    ], (string) Str::uuid());

    $from = Carbon::parse($today, $tz)->startOfDay();
    $to = Carbon::parse($today, $tz)->endOfDay();

    // Filter by Rufiji only — should see 3000, not 999
    $result = app(PaymentsByMethodService::class)->get($from, $to, $rufiji->id);
    $btRow = $result['rows']->firstWhere('method', 'bank_transfer');
    expect($btRow)->not->toBeNull();
    expect(number_format((float) $btRow->amount, 2))->toBe('3,000.00');
    expect((int) $btRow->payment_count)->toBe(1);
});

// ─── Timezone boundary tests ───────────────────────────────────────────────────

test('payments at 00:30 and 23:30 EAT both appear for that local day; 02:00 next day does not', function () {
    $tz = 'Africa/Dar_es_Salaam';
    $localDate = '2026-11-06';

    $location = reportLocation();
    $customer = reportCustomer();

    // All timestamps in UTC (EAT = UTC+3)
    $earlyUtc = Carbon::parse("{$localDate} 00:30:00", $tz)->utc();   // 2026-11-05 21:30 UTC
    $lateUtc = Carbon::parse("{$localDate} 23:30:00", $tz)->utc();    // 2026-11-06 20:30 UTC
    $nextUtc = Carbon::parse('2026-11-07 02:00:00', $tz)->utc();      // 2026-11-06 23:00 UTC

    $user = User::factory()->create();

    $makePayment = function (string $amount, Carbon $receivedAt) use ($customer, $location, $user): Payment {
        $inv = reportInvoice($customer, $location, $amount, '0.00', '0.00', InvoiceStatus::Issued, '2026-11-06');
        $pay = Payment::forceCreate([
            'number' => 'TZ-PAY-'.random_int(1, 99999),
            'customer_id' => $customer->id,
            'method' => 'cash',
            'amount' => $amount,
            'received_at' => $receivedAt->toDateTimeString(),
            'received_by' => $user->id,
            'status' => 'active',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        PaymentAllocation::forceCreate(['payment_id' => $pay->id, 'invoice_id' => $inv->id, 'amount' => $amount]);

        return $pay;
    };

    $makePayment('100.00', $earlyUtc);  // 00:30 EAT — should appear
    $makePayment('200.00', $lateUtc);   // 23:30 EAT — should appear
    $makePayment('300.00', $nextUtc);   // next day 02:00 EAT — must NOT appear

    $from = Carbon::parse($localDate, $tz)->startOfDay();
    $to = Carbon::parse($localDate, $tz)->endOfDay();

    $result = app(PaymentsByMethodService::class)->get($from, $to, $location->id);
    $cashRow = $result['rows']->firstWhere('method', 'cash');

    expect($cashRow)->not->toBeNull();
    expect((float) $cashRow->amount)->toBe(300.0);  // 100 + 200, not 300 from next day
    expect((int) $cashRow->payment_count)->toBe(2);
});

test('pos sessions opened at 00:30 and 23:30 EAT appear for that local day; 02:00 next day does not', function () {
    $tz = 'Africa/Dar_es_Salaam';
    $localDate = '2026-11-06';

    $location = reportLocation();

    $earlyUtc = Carbon::parse("{$localDate} 00:30:00", $tz)->utc();
    $lateUtc = Carbon::parse("{$localDate} 23:30:00", $tz)->utc();
    $nextUtc = Carbon::parse('2026-11-07 02:00:00', $tz)->utc();

    // Three different users to avoid the one-open-session-per-user partial unique index
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    $u3 = User::factory()->create();

    $loc = reportLocation();

    $id1 = DB::table('pos_sessions')->insertGetId([
        'user_id' => $u1->id, 'location_id' => $loc->id,
        'opened_at' => $earlyUtc->toDateTimeString(), 'opening_cash' => '0.00',
        'status' => 'closed', 'closed_at' => $earlyUtc->copy()->addHours(8)->toDateTimeString(),
        'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
    ]);
    $id2 = DB::table('pos_sessions')->insertGetId([
        'user_id' => $u2->id, 'location_id' => $loc->id,
        'opened_at' => $lateUtc->toDateTimeString(), 'opening_cash' => '0.00',
        'status' => 'closed', 'closed_at' => $lateUtc->copy()->addHours(2)->toDateTimeString(),
        'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
    ]);
    $id3 = DB::table('pos_sessions')->insertGetId([
        'user_id' => $u3->id, 'location_id' => $loc->id,
        'opened_at' => $nextUtc->toDateTimeString(), 'opening_cash' => '0.00',
        'status' => 'open',
        'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
    ]);

    $from = Carbon::parse($localDate, $tz)->startOfDay();
    $to = Carbon::parse($localDate, $tz)->endOfDay();

    $result = app(PosSessionsService::class)->paginate($from, $to, $loc->id, 50);
    $ids = $result->pluck('id')->map(fn ($v) => (int) $v)->all();

    expect($ids)->toContain($id1);
    expect($ids)->toContain($id2);
    expect($ids)->not->toContain($id3);
});

test('voided invoices at 00:30 and 23:30 EAT appear for that local day; 02:00 next day does not', function () {
    $tz = 'Africa/Dar_es_Salaam';
    $localDate = '2026-11-06';

    $location = reportLocation();
    $customer = reportCustomer();
    $creator = User::factory()->create();

    $earlyUtc = Carbon::parse("{$localDate} 00:30:00", $tz)->utc();
    $lateUtc = Carbon::parse("{$localDate} 23:30:00", $tz)->utc();
    $nextUtc = Carbon::parse('2026-11-07 02:00:00', $tz)->utc();

    $makeVoid = function (Carbon $voidedAt) use ($customer, $location, $creator): Invoice {
        return Invoice::forceCreate([
            'number' => 'TZ-VOID-'.random_int(1, 99999),
            'channel' => 'invoice',
            'status' => InvoiceStatus::Void,
            'customer_id' => $customer->id,
            'location_id' => $location->id,
            'issue_date' => '2026-11-01',
            'voided_at' => $voidedAt->toDateTimeString(),
            'void_reason' => 'TZ boundary test',
            'currency' => 'TZS',
            'prices_include_tax' => false,
            'subtotal' => '500.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'total' => '500.00',
            'amount_paid' => '0.00',
            'created_by' => $creator->id,
        ]);
    };

    $v1 = $makeVoid($earlyUtc);  // 00:30 EAT — should appear
    $v2 = $makeVoid($lateUtc);   // 23:30 EAT — should appear
    $v3 = $makeVoid($nextUtc);   // next day 02:00 EAT — must NOT appear

    $from = Carbon::parse($localDate, $tz)->startOfDay();
    $to = Carbon::parse($localDate, $tz)->endOfDay();

    $result = app(VoidedInvoicesService::class)->paginate($from, $to, $location->id, 50);
    $ids = $result->pluck('id')->map(fn ($v) => (int) $v)->all();

    expect($ids)->toContain($v1->id);
    expect($ids)->toContain($v2->id);
    expect($ids)->not->toContain($v3->id);
});
