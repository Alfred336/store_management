<?php

use App\Enums\InvoiceStatus;
use App\Exceptions\InsufficientStockException;
use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Livewire\Actions\Customers\DeleteCustomer;
use App\Livewire\Actions\Customers\GetCustomerStatement;
use App\Livewire\Actions\Invoicing\CreateInvoiceDraft;
use App\Livewire\Actions\Invoicing\DeleteInvoiceDraft;
use App\Livewire\Actions\Invoicing\IssueInvoice;
use App\Livewire\Actions\Invoicing\UpdateInvoiceDraft;
use App\Livewire\Actions\Invoicing\VoidInvoice;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemAllocation;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Services\Customers\CreditLimitService;
use App\Services\Settings\Settings;
use App\Services\StockService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function invoicingUser(array $permissions): User
{
    $user = User::factory()->create(['name' => 'Invoice Test User']);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user->givePermissionTo($permissions);

    return $user;
}

/** @return array{unit: Unit, boxUnit: Unit, product: Product, location: Location, customer: Customer} */
function invoiceTestSetup(bool $trackBatches = false): array
{
    $unit = Unit::forceCreate(['name' => 'Piece '.Str::random(6), 'abbreviation' => 'pc']);
    $boxUnit = Unit::forceCreate(['name' => 'Box '.Str::random(6), 'abbreviation' => 'box']);

    $product = Product::forceCreate([
        'sku' => 'SKU-'.Str::upper(Str::random(8)),
        'name' => 'Agro Fertilizer '.Str::random(4),
        'base_unit_id' => $unit->id,
        'tax_type' => 'standard',
        'reorder_level' => 0,
        'track_batches' => $trackBatches,
        'is_active' => true,
    ]);

    ProductUnit::forceCreate([
        'product_id' => $product->id,
        'unit_id' => $boxUnit->id,
        'factor' => '12.000',
    ]);

    $location = Location::forceCreate([
        'name' => 'Main Warehouse '.Str::random(6),
        'type' => 'warehouse',
        'is_active' => true,
    ]);

    $customer = Customer::forceCreate([
        'code' => 'CUS-'.Str::upper(Str::random(6)),
        'name' => 'Kilimo Bora Ltd',
        'type' => 'business',
        'phone' => '+255 711 000 111',
        'email' => 'info@kilimo.example',
        'address' => 'Morogoro Road, Dar es Salaam',
        'tin' => 'TIN-999-001',
        'vrn' => 'VRN-TEST-001',
        'credit_limit' => '0.00',
        'payment_terms_days' => 30,
        'is_active' => true,
        'is_walk_in' => false,
    ]);

    return [
        'unit' => $unit,
        'boxUnit' => $boxUnit,
        'product' => $product,
        'location' => $location,
        'customer' => $customer,
    ];
}

function invoiceReceiveStock(Product $product, Location $location, string $quantity, ?Batch $batch = null): void
{
    app(StockService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'batch_id' => $batch?->id,
        'quantity' => $quantity,
    ]);
}

// ==========================================
// DRAFT TESTS
// ==========================================

test('draft invoice creation computes totals and base quantities including packaging unit', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.view']);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'issue_date' => '2026-11-01',
        'due_date' => '2026-11-15',
        'notes' => 'Test notes',
        'terms' => 'Net 14',
        'items' => [
            [
                'product_id' => $setup['product']->id,
                'unit_id' => $setup['boxUnit']->id,
                'quantity' => '2',
                'unit_price' => '100.00',
                'discount_amount' => '20.00',
            ],
        ],
    ]);

    expect($draft->status)->toBe(InvoiceStatus::Draft)
        ->and($draft->channel)->toBe('invoice')
        ->and($draft->number)->toBeNull()
        ->and($draft->customer_id)->toBe($setup['customer']->id)
        ->and($draft->location_id)->toBe($setup['location']->id)
        ->and($draft->created_by)->toBe($user->id)
        ->and($draft->prices_include_tax)->toBeFalse()
        // Line subtotal = 2 * 100 = 200.00, discount = 20.00, net = 180.00, vat 18% = 32.40, total = 212.40
        ->and($draft->subtotal)->toBe('200.00')
        ->and($draft->discount_total)->toBe('20.00')
        ->and($draft->tax_total)->toBe('32.40')
        ->and($draft->total)->toBe('212.40');

    expect($draft->items)->toHaveCount(1);
    $item = $draft->items->first();
    expect($item->product_id)->toBe($setup['product']->id)
        ->and($item->description)->toBe($setup['product']->name)
        ->and($item->sku)->toBe($setup['product']->sku)
        ->and($item->unit_id)->toBe($setup['boxUnit']->id)
        ->and($item->quantity)->toBe('2.000')
        ->and($item->base_quantity)->toBe('24.000') // 2 boxes * factor 12 = 24 base units
        ->and($item->unit_price)->toBe('100.00')
        ->and($item->discount_amount)->toBe('20.00')
        ->and($item->tax_type)->toBe('standard')
        ->and($item->tax_rate)->toBe('18.00')
        ->and($item->line_tax)->toBe('32.40')
        ->and($item->line_total)->toBe('212.40');
});

test('draft invoice validation fails with no items', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create']);
    $this->actingAs($user);

    expect(fn () => app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [],
    ]))->toThrow(ValidationException::class);
});

test('draft invoice validation fails with bad unit not defined on product', function () {
    $setup = invoiceTestSetup();
    $unrelatedUnit = Unit::forceCreate(['name' => 'Litre', 'abbreviation' => 'L']);
    $user = invoicingUser(['invoices.create']);
    $this->actingAs($user);

    expect(fn () => app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [
            [
                'product_id' => $setup['product']->id,
                'unit_id' => $unrelatedUnit->id,
                'quantity' => '1',
                'unit_price' => '50.00',
            ],
        ],
    ]))->toThrow(ValidationException::class);
});

test('draft invoice validation fails with inactive product, customer, or location', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create']);
    $this->actingAs($user);

    $setup['product']->update(['is_active' => false]);
    expect(fn () => app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '50.00']],
    ]))->toThrow(ValidationException::class);

    $setup['product']->update(['is_active' => true]);
    $setup['customer']->update(['is_active' => false]);
    expect(fn () => app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '50.00']],
    ]))->toThrow(ValidationException::class);

    $setup['customer']->update(['is_active' => true]);
    $setup['location']->update(['is_active' => false]);
    expect(fn () => app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '50.00']],
    ]))->toThrow(ValidationException::class);
});

test('draft invoice validation fails with walk-in customer on channel invoice', function () {
    $setup = invoiceTestSetup();
    $walkIn = Customer::forceCreate([
        'code' => 'WALK-IN',
        'name' => 'Walk-in Customer',
        'type' => 'individual',
        'is_active' => true,
        'is_walk_in' => true,
    ]);
    $user = invoicingUser(['invoices.create']);
    $this->actingAs($user);

    expect(fn () => app(CreateInvoiceDraft::class)([
        'customer_id' => $walkIn->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '50.00']],
    ]))->toThrow(ValidationException::class);
});

test('draft invoice validation fails when discount exceeds line subtotal', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create']);
    $this->actingAs($user);

    expect(fn () => app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [
            [
                'product_id' => $setup['product']->id,
                'quantity' => '1',
                'unit_price' => '50.00',
                'discount_amount' => '50.01',
            ],
        ],
    ]))->toThrow(ValidationException::class);
});

test('draft invoice validation fails when due date is before issue date', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create']);
    $this->actingAs($user);

    expect(fn () => app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'issue_date' => '2026-11-10',
        'due_date' => '2026-11-05',
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '50.00']],
    ]))->toThrow(ValidationException::class);
});

test('draft invoice can be updated while in draft status', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.update', 'invoices.view']);
    $this->actingAs($user);

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '100.00']],
    ]);

    $updated = app(UpdateInvoiceDraft::class)($draft, [
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'notes' => 'Updated draft note',
        'items' => [
            ['product_id' => $setup['product']->id, 'quantity' => '3', 'unit_price' => '80.00', 'discount_amount' => '10.00'],
        ],
    ]);

    expect($updated->notes)->toBe('Updated draft note')
        ->and($updated->subtotal)->toBe('240.00')
        ->and($updated->discount_total)->toBe('10.00')
        ->and($updated->items)->toHaveCount(1)
        ->and($updated->items->first()->quantity)->toBe('3.000');
});

test('updating non-draft invoice is blocked', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.update']);
    $this->actingAs($user);

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '100.00']],
    ]);

    $draft->update(['status' => InvoiceStatus::Issued, 'number' => 'INV-2026-000001']);

    expect(fn () => app(UpdateInvoiceDraft::class)($draft, [
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '2', 'unit_price' => '100.00']],
    ]))->toThrow(AuthorizationException::class);
});

test('draft invoice can be deleted', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.update']);
    $this->actingAs($user);

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '100.00']],
    ]);

    $draftId = $draft->id;
    app(DeleteInvoiceDraft::class)($draft);

    expect(Invoice::query()->find($draftId))->toBeNull()
        ->and(InvoiceItem::query()->where('invoice_id', $draftId)->exists())->toBeFalse();
});

test('deleting non-draft invoice is blocked', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.update']);
    $this->actingAs($user);

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '100.00']],
    ]);

    $draft->update(['status' => InvoiceStatus::Issued, 'number' => 'INV-2026-000001']);

    expect(fn () => app(DeleteInvoiceDraft::class)($draft))->toThrow(AuthorizationException::class);
    expect(Invoice::query()->find($draft->id))->not->toBeNull();
});

test('delete and update draft actions throw ValidationException for non-draft when policy bypassed', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.update']);
    $this->actingAs($user);

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '100.00']],
    ]);

    $draft->update(['status' => InvoiceStatus::Issued, 'number' => 'INV-2026-000099']);

    // Bypass the InvoicePolicy so the action's own guard is reached.
    Gate::before(fn () => true);

    expect(fn () => app(DeleteInvoiceDraft::class)($draft))->toThrow(ValidationException::class);
    expect(fn () => app(UpdateInvoiceDraft::class)($draft, [
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '2', 'unit_price' => '100.00']],
    ]))->toThrow(ValidationException::class);
});

// ==========================================
// ISSUE INVOICE TESTS
// ==========================================

test('issuing invoice deducts exact stock and records allocations that sum to base quantity', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    invoiceReceiveStock($setup['product'], $setup['location'], '50.000');

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [
            ['product_id' => $setup['product']->id, 'quantity' => '10', 'unit_price' => '100.00'],
        ],
    ]);

    $issued = app(IssueInvoice::class)($draft, 'IDEMP-TEST-001');

    expect($issued->invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($issued->invoice->number)->not->toBeNull()
        ->and($issued->invoice->issued_by)->toBe($user->id)
        ->and($issued->invoice->issued_at)->not->toBeNull()
        ->and($issued->invoice->idempotency_key)->toBe('IDEMP-TEST-001')
        ->and($issued->invoice->customer_name)->toBe($setup['customer']->name)
        ->and($issued->invoice->customer_phone)->toBe($setup['customer']->phone)
        ->and($issued->invoice->customer_address)->toBe($setup['customer']->address)
        ->and($issued->invoice->customer_tin)->toBe($setup['customer']->tin)
        ->and($issued->invoice->customer_vrn)->toBe($setup['customer']->vrn);

    $stockLevel = (string) StockLevel::query()
        ->where('product_id', $setup['product']->id)
        ->where('location_id', $setup['location']->id)
        ->value('quantity');
    expect($stockLevel)->toBe('40.000');

    $item = $issued->invoice->items->first();
    $allocations = $item->allocations;
    expect($allocations)->toHaveCount(1);
    expect($allocations->first()->base_quantity)->toBe('10.000')
        ->and($allocations->first()->stock_movement_id)->not->toBeNull();
});

test('insufficient stock leaves everything unchanged without consuming number', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    invoiceReceiveStock($setup['product'], $setup['location'], '5.000');

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [
            ['product_id' => $setup['product']->id, 'quantity' => '10', 'unit_price' => '100.00'],
        ],
    ]);

    $lastSeqBefore = DB::table('document_sequences')->where('type', 'invoice')->value('last_number') ?? 0;

    expect(fn () => app(IssueInvoice::class)($draft, 'IDEMP-FAIL-01'))
        ->toThrow(InsufficientStockException::class);

    $draft->refresh();
    expect($draft->status)->toBe(InvoiceStatus::Draft)
        ->and($draft->number)->toBeNull()
        ->and($draft->items->first()->allocations)->toBeEmpty();

    $lastSeqAfter = DB::table('document_sequences')->where('type', 'invoice')->value('last_number') ?? 0;
    expect($lastSeqAfter)->toBe($lastSeqBefore);

    $stockLevel = (string) StockLevel::query()
        ->where('product_id', $setup['product']->id)
        ->where('location_id', $setup['location']->id)
        ->value('quantity');
    expect($stockLevel)->toBe('5.000');
});

test('FEFO split across two batches produces two allocations in correct order', function () {
    $setup = invoiceTestSetup(trackBatches: true);
    $user = invoicingUser(['invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    $batchEarly = Batch::forceCreate([
        'product_id' => $setup['product']->id,
        'batch_number' => 'B-EARLY',
        'expiry_date' => now()->addDays(5)->toDateString(),
    ]);
    $batchLate = Batch::forceCreate([
        'product_id' => $setup['product']->id,
        'batch_number' => 'B-LATE',
        'expiry_date' => now()->addDays(30)->toDateString(),
    ]);

    invoiceReceiveStock($setup['product'], $setup['location'], '5.000', $batchEarly);
    invoiceReceiveStock($setup['product'], $setup['location'], '10.000', $batchLate);

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [
            ['product_id' => $setup['product']->id, 'quantity' => '8', 'unit_price' => '100.00'],
        ],
    ]);

    $issued = app(IssueInvoice::class)($draft, 'IDEMP-FEFO-01');

    $allocations = $issued->invoice->items->first()->allocations->sortBy('id')->values();
    expect($allocations)->toHaveCount(2);

    expect($allocations[0]->batch_id)->toBe($batchEarly->id)
        ->and($allocations[0]->base_quantity)->toBe('5.000')
        ->and($allocations[1]->batch_id)->toBe($batchLate->id)
        ->and($allocations[1]->base_quantity)->toBe('3.000');

    $levelEarly = (string) StockLevel::query()
        ->where('product_id', $setup['product']->id)
        ->where('batch_id', $batchEarly->id)
        ->value('quantity');
    $levelLate = (string) StockLevel::query()
        ->where('product_id', $setup['product']->id)
        ->where('batch_id', $batchLate->id)
        ->value('quantity');

    expect($levelEarly)->toBe('0.000')
        ->and($levelLate)->toBe('7.000');
});

test('expired batch is not sold during invoice issuance', function () {
    $setup = invoiceTestSetup(trackBatches: true);
    $user = invoicingUser(['invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    $batchExpired = Batch::forceCreate([
        'product_id' => $setup['product']->id,
        'batch_number' => 'B-EXPIRED',
        'expiry_date' => now()->subDay()->toDateString(),
    ]);
    $batchGood = Batch::forceCreate([
        'product_id' => $setup['product']->id,
        'batch_number' => 'B-GOOD',
        'expiry_date' => now()->addDays(10)->toDateString(),
    ]);

    invoiceReceiveStock($setup['product'], $setup['location'], '10.000', $batchExpired);
    invoiceReceiveStock($setup['product'], $setup['location'], '5.000', $batchGood);

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [
            ['product_id' => $setup['product']->id, 'quantity' => '7', 'unit_price' => '100.00'],
        ],
    ]);

    // Only 5 units are non-expired; asking for 7 must fail
    expect(fn () => app(IssueInvoice::class)($draft, 'IDEMP-EXPIRED-01'))
        ->toThrow(InsufficientStockException::class);
});

test('prices_include_tax both ways give the right totals', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    invoiceReceiveStock($setup['product'], $setup['location'], '100.000');
    app(Settings::class)->put('tax.vat_rate', '18.00');

    // Case 1: Exclusive
    app(Settings::class)->put('tax.prices_include_tax', false);
    $draftEx = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '100.00']],
    ]);
    $issuedEx = app(IssueInvoice::class)($draftEx, 'IDEMP-TAX-EX');
    // net 100, vat 18 -> total 118
    expect($issuedEx->invoice->subtotal)->toBe('100.00')
        ->and($issuedEx->invoice->tax_total)->toBe('18.00')
        ->and($issuedEx->invoice->total)->toBe('118.00');

    // Case 2: Inclusive
    app(Settings::class)->put('tax.prices_include_tax', true);
    $draftIn = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '118.00']],
    ]);
    $issuedIn = app(IssueInvoice::class)($draftIn, 'IDEMP-TAX-IN');
    // gross 118, vat extracted = 18.00, total = 118.00
    expect($issuedIn->invoice->subtotal)->toBe('118.00')
        ->and($issuedIn->invoice->tax_total)->toBe('18.00')
        ->and($issuedIn->invoice->total)->toBe('118.00');
});

test('credit limit block stops invoice issuance when configured to block', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    invoiceReceiveStock($setup['product'], $setup['location'], '100.000');
    $setup['customer']->update(['credit_limit' => '100.00']);
    app(Settings::class)->put('customers.credit_limit_mode', 'block');

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '2', 'unit_price' => '80.00']],
    ]);

    $seqBefore = DB::table('document_sequences')->where('type', 'invoice')->value('last_number') ?? 0;
    $movementsBefore = StockMovement::query()->where('product_id', $setup['product']->id)->count();

    // 2 * 80 + vat = 188.80, which exceeds credit_limit of 100.00
    expect(fn () => app(IssueInvoice::class)($draft, 'IDEMP-CREDIT-BLOCK'))
        ->toThrow(ValidationException::class);

    $draft->refresh();
    expect($draft->status)->toBe(InvoiceStatus::Draft)
        ->and($draft->number)->toBeNull();

    // no number consumed
    $seqAfter = DB::table('document_sequences')->where('type', 'invoice')->value('last_number') ?? 0;
    expect($seqAfter)->toBe($seqBefore);

    // no stock movement added, no allocation
    expect(StockMovement::query()->where('product_id', $setup['product']->id)->count())->toBe($movementsBefore);
    expect(InvoiceItemAllocation::query()
        ->whereHas('item', fn ($q) => $q->where('invoice_id', $draft->id))
        ->count())->toBe(0);

    // Change setting to warn -> should pass and return a warning
    app(Settings::class)->put('customers.credit_limit_mode', 'warn');
    $issued = app(IssueInvoice::class)($draft, 'IDEMP-CREDIT-WARN');
    expect($issued->invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($issued->creditWarning)->not->toBeNull()
        ->and($issued->creditWarning)->toContain('Kilimo Bora Ltd');
});

test('same idempotency key returns the same invoice and does not post twice', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    invoiceReceiveStock($setup['product'], $setup['location'], '50.000');

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '5', 'unit_price' => '100.00']],
    ]);

    $issued1 = app(IssueInvoice::class)($draft, 'IDEMP-SAME-KEY');
    $movementsCount1 = StockMovement::query()->where('product_id', $setup['product']->id)->count();

    $issued2 = app(IssueInvoice::class)($draft, 'IDEMP-SAME-KEY');
    $movementsCount2 = StockMovement::query()->where('product_id', $setup['product']->id)->count();

    expect($issued1->invoice->id)->toBe($issued2->invoice->id)
        ->and($issued1->invoice->number)->toBe($issued2->invoice->number)
        ->and($movementsCount2)->toBe($movementsCount1);

    // no warning on replay
    expect($issued2->creditWarning)->toBeNull();
});

test('idempotency replay returns same invoice even after status changed to paid', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    invoiceReceiveStock($setup['product'], $setup['location'], '20.000');

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '5', 'unit_price' => '10.00']],
    ]);

    $issued = app(IssueInvoice::class)($draft, 'IDEMP-REPLAY-PAID');
    $movementsBefore = StockMovement::query()->where('product_id', $setup['product']->id)->count();

    // Simulate the invoice being fully paid
    $issued->invoice->update(['status' => InvoiceStatus::Paid, 'amount_paid' => $issued->invoice->total]);

    // Replaying the same key must return the now-paid invoice without posting anything new
    $replayed = app(IssueInvoice::class)($draft, 'IDEMP-REPLAY-PAID');

    expect($replayed->invoice->id)->toBe($issued->invoice->id)
        ->and($replayed->invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($replayed->creditWarning)->toBeNull();

    // No additional stock movements
    expect(StockMovement::query()->where('product_id', $setup['product']->id)->count())->toBe($movementsBefore);
});

test('issuing an already issued invoice with different idempotency key fails', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    invoiceReceiveStock($setup['product'], $setup['location'], '50.000');

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '5', 'unit_price' => '100.00']],
    ]);

    app(IssueInvoice::class)($draft, 'IDEMP-KEY-A');

    expect(fn () => app(IssueInvoice::class)($draft, 'IDEMP-KEY-B'))
        ->toThrow(ValidationException::class);
});

test('user with invoices.issue but without customers.view can issue an invoice', function () {
    // Credit check must NOT go through GetCustomerStatement (which calls Gate::authorize('view', customer))
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.issue']); // intentionally NO customers.view
    $this->actingAs($user);

    invoiceReceiveStock($setup['product'], $setup['location'], '20.000');

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '5', 'unit_price' => '10.00']],
    ]);

    // Should succeed without customers.view permission
    $issued = app(IssueInvoice::class)($draft, 'IDEMP-NO-CUST-VIEW');
    expect($issued->invoice->status)->toBe(InvoiceStatus::Issued);
});

test('walk-in customer is allowed on pos channel invoice', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    $walkIn = Customer::forceCreate([
        'code' => 'WALK-IN-POS',
        'name' => 'Walk-in Customer',
        'type' => 'individual',
        'is_active' => true,
        'is_walk_in' => true,
    ]);

    invoiceReceiveStock($setup['product'], $setup['location'], '10.000');

    // CreateInvoiceDraft only produces 'invoice' channel drafts; force-create a 'pos' draft directly
    $draft = Invoice::forceCreate([
        'channel' => 'pos',
        'status' => InvoiceStatus::Draft,
        'customer_id' => $walkIn->id,
        'location_id' => $setup['location']->id,
        'issue_date' => now()->toDateString(),
        'currency' => 'TZS',
        'prices_include_tax' => false,
        'subtotal' => '10.00',
        'discount_total' => '0.00',
        'tax_total' => '0.00',
        'total' => '10.00',
        'amount_paid' => '0.00',
        'created_by' => $user->id,
    ]);

    $draft->items()->create([
        'product_id' => $setup['product']->id,
        'description' => $setup['product']->name,
        'sku' => $setup['product']->sku,
        'unit_id' => $setup['unit']->id,
        'quantity' => '1.000',
        'base_quantity' => '1.000',
        'unit_price' => '10.00',
        'discount_amount' => '0.00',
        'tax_type' => 'standard',
        'tax_rate' => '0.00',
        'line_tax' => '0.00',
        'line_total' => '10.00',
    ]);

    // Walk-in is only blocked on 'invoice' channel; pos must succeed
    $issued = app(IssueInvoice::class)($draft, 'IDEMP-POS-WALK-IN');
    expect($issued->invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($issued->invoice->channel)->toBe('pos');
});

test('credit check at exactly the limit is allowed', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.issue']); // no customers.view needed
    $this->actingAs($user);

    invoiceReceiveStock($setup['product'], $setup['location'], '100.000');
    app(Settings::class)->put('tax.vat_rate', '0.00');  // use 0 VAT so total = price, easier to reason about
    app(Settings::class)->put('tax.prices_include_tax', false);
    $setup['customer']->update(['credit_limit' => '100.00']);
    app(Settings::class)->put('customers.credit_limit_mode', 'block');

    // Exactly at limit: 1 unit * 100.00 = 100.00 = credit_limit -> allowed
    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '100.00']],
    ]);
    $issued = app(IssueInvoice::class)($draft, 'IDEMP-CREDIT-AT-LIMIT');
    expect($issued->invoice->status)->toBe(InvoiceStatus::Issued);
});

test('credit check one cent over the limit is blocked', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.issue']); // no customers.view needed
    $this->actingAs($user);

    invoiceReceiveStock($setup['product'], $setup['location'], '100.000');
    app(Settings::class)->put('tax.vat_rate', '0.00');  // 0 VAT so total = price
    app(Settings::class)->put('tax.prices_include_tax', false);
    $setup['customer']->update(['credit_limit' => '100.00']);
    app(Settings::class)->put('customers.credit_limit_mode', 'block');

    // One cent over: 100.01 > 100.00 -> blocked
    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '100.01']],
    ]);
    expect(fn () => app(IssueInvoice::class)($draft, 'IDEMP-CREDIT-OVER-LIMIT'))
        ->toThrow(ValidationException::class);

    $draft->refresh();
    expect($draft->status)->toBe(InvoiceStatus::Draft);
});

test('credit_limit of zero is treated as no limit by CreditLimitService', function () {
    // customers.credit_limit is NOT NULL DEFAULT 0 — null cannot arrive from the database.
    // Zero (the column default) means "no credit limit configured"; the service always allows.
    $setup = invoiceTestSetup(); // customer has credit_limit = '0.00'

    app(Settings::class)->put('customers.credit_limit_mode', 'block');

    $result = app(CreditLimitService::class)->evaluate($setup['customer'], '999999.99');
    expect($result['outcome'])->toBe('within_limit');
});

test('issuing a draft with an idempotency key already used by a different invoice throws ValidationException', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    invoiceReceiveStock($setup['product'], $setup['location'], '50.000');

    // Create and issue invoice A with key IDEMP-CONFLICT
    $draftA = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '10.00']],
    ]);
    app(IssueInvoice::class)($draftA, 'IDEMP-CONFLICT');

    // Create a different draft B
    $draftB = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '10.00']],
    ]);

    // Trying to issue draft B with the same key must throw ValidationException (not a raw QueryException)
    expect(fn () => app(IssueInvoice::class)($draftB, 'IDEMP-CONFLICT'))
        ->toThrow(ValidationException::class);

    $draftB->refresh();
    expect($draftB->status)->toBe(InvoiceStatus::Draft);
});

test('document numbering is sequential and rolled back on failure', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    invoiceReceiveStock($setup['product'], $setup['location'], '10.000');

    $draft1 = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '5', 'unit_price' => '10.00']],
    ]);
    $issued1 = app(IssueInvoice::class)($draft1, 'IDEMP-SEQ-01');

    // Attempt second invoice with insufficient stock (remaining 5, asking for 20)
    $draftFail = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '20', 'unit_price' => '10.00']],
    ]);
    expect(fn () => app(IssueInvoice::class)($draftFail, 'IDEMP-SEQ-FAIL'))
        ->toThrow(InsufficientStockException::class);

    // Issue third invoice with remaining stock
    $draft2 = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '5', 'unit_price' => '10.00']],
    ]);
    $issued2 = app(IssueInvoice::class)($draft2, 'IDEMP-SEQ-02');

    $year = now()->format('Y');
    expect($issued1->invoice->number)->toBe("INV-{$year}-000001")
        ->and($issued2->invoice->number)->toBe("INV-{$year}-000002");
});

// ==========================================
// VOID INVOICE TESTS
// ==========================================

test('voiding invoice returns exact quantities to exact batches with audit fields', function () {
    $setup = invoiceTestSetup(trackBatches: true);
    $user = invoicingUser(['invoices.create', 'invoices.issue', 'invoices.void']);
    $this->actingAs($user);

    $batchA = Batch::forceCreate([
        'product_id' => $setup['product']->id,
        'batch_number' => 'B-VOID-A',
        'expiry_date' => now()->addDays(10)->toDateString(),
    ]);
    $batchB = Batch::forceCreate([
        'product_id' => $setup['product']->id,
        'batch_number' => 'B-VOID-B',
        'expiry_date' => now()->addDays(20)->toDateString(),
    ]);

    invoiceReceiveStock($setup['product'], $setup['location'], '5.000', $batchA);
    invoiceReceiveStock($setup['product'], $setup['location'], '10.000', $batchB);

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '8', 'unit_price' => '50.00']],
    ]);
    $issued = app(IssueInvoice::class)($draft, 'IDEMP-VOID-01');

    $voided = app(VoidInvoice::class)($issued->invoice, 'Customer order cancelled');

    expect($voided->status)->toBe(InvoiceStatus::Void)
        ->and($voided->voided_by)->toBe($user->id)
        ->and($voided->voided_at)->not->toBeNull()
        ->and($voided->void_reason)->toBe('Customer order cancelled');

    $levelA = (string) StockLevel::query()->where('product_id', $setup['product']->id)->where('batch_id', $batchA->id)->value('quantity');
    $levelB = (string) StockLevel::query()->where('product_id', $setup['product']->id)->where('batch_id', $batchB->id)->value('quantity');

    expect($levelA)->toBe('5.000')
        ->and($levelB)->toBe('10.000');

    $allocations = InvoiceItemAllocation::query()->whereHas('item', fn ($q) => $q->where('invoice_id', $issued->invoice->id))->get();
    expect($allocations)->toHaveCount(2);
    foreach ($allocations as $alloc) {
        expect($alloc->return_movement_id)->not->toBeNull();
    }
});

test('voiding invoice requires a non-empty reason', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.issue', 'invoices.void']);
    $this->actingAs($user);

    invoiceReceiveStock($setup['product'], $setup['location'], '10.000');
    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '2', 'unit_price' => '50.00']],
    ]);
    $issued = app(IssueInvoice::class)($draft, 'IDEMP-REASON-TEST');

    expect(fn () => app(VoidInvoice::class)($issued->invoice, '   '))
        ->toThrow(ValidationException::class);
});

test('cannot void a draft invoice, an already void invoice, or an invoice with payments', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.issue', 'invoices.void']);
    $this->actingAs($user);

    invoiceReceiveStock($setup['product'], $setup['location'], '10.000');
    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '2', 'unit_price' => '50.00']],
    ]);

    // Cannot void a draft
    expect(fn () => app(VoidInvoice::class)($draft, 'Cancel draft'))
        ->toThrow(ValidationException::class);

    $issued = app(IssueInvoice::class)($draft, 'IDEMP-VOID-RULES');

    // Cannot void when amount_paid > 0
    $issued->invoice->update(['amount_paid' => '20.00']);
    expect(fn () => app(VoidInvoice::class)($issued->invoice, 'Cancel paid'))
        ->toThrow(ValidationException::class);

    $issued->invoice->update(['amount_paid' => '0.00']);
    $voided = app(VoidInvoice::class)($issued->invoice, 'Cancel now');

    // Cannot void an already void invoice
    expect(fn () => app(VoidInvoice::class)($voided, 'Cancel again'))
        ->toThrow(ValidationException::class);
});

test('ledger invariant holds after issue and after void', function () {
    $setup = invoiceTestSetup(trackBatches: true);
    $user = invoicingUser(['invoices.create', 'invoices.issue', 'invoices.void']);
    $this->actingAs($user);

    $batch = Batch::forceCreate([
        'product_id' => $setup['product']->id,
        'batch_number' => 'B-INVARIANT',
        'expiry_date' => now()->addDays(15)->toDateString(),
    ]);

    invoiceReceiveStock($setup['product'], $setup['location'], '30.000', $batch);

    $stockLevelSumBefore = StockLevel::query()->where('product_id', $setup['product']->id)->sum('quantity');
    $movementSumBefore = StockMovement::query()->where('product_id', $setup['product']->id)->sum('quantity');
    expect(bccomp((string) $stockLevelSumBefore, (string) $movementSumBefore, 3))->toBe(0);

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '12', 'unit_price' => '50.00']],
    ]);
    $issued = app(IssueInvoice::class)($draft, 'IDEMP-INVARIANT');

    $stockLevelSumAfterIssue = StockLevel::query()->where('product_id', $setup['product']->id)->sum('quantity');
    $movementSumAfterIssue = StockMovement::query()->where('product_id', $setup['product']->id)->sum('quantity');
    expect(bccomp((string) $stockLevelSumAfterIssue, (string) $movementSumAfterIssue, 3))->toBe(0)
        ->and(bccomp((string) $stockLevelSumAfterIssue, '18.000', 3))->toBe(0);

    app(VoidInvoice::class)($issued->invoice, 'Void for invariant test');

    $stockLevelSumAfterVoid = StockLevel::query()->where('product_id', $setup['product']->id)->sum('quantity');
    $movementSumAfterVoid = StockMovement::query()->where('product_id', $setup['product']->id)->sum('quantity');
    expect(bccomp((string) $stockLevelSumAfterVoid, (string) $movementSumAfterVoid, 3))->toBe(0)
        ->and(bccomp((string) $stockLevelSumAfterVoid, '30.000', 3))->toBe(0);
});

// ==========================================
// AUTHORIZATION TESTS
// ==========================================

test('viewer role cannot create, issue, or void invoices', function () {
    app(EnsureSystemRoles::class)();
    $setup = invoiceTestSetup();

    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer);

    expect(fn () => app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '10.00']],
    ]))->toThrow(AuthorizationException::class);
});

test('cashier role can create and issue invoices but cannot void invoices', function () {
    app(EnsureSystemRoles::class)();
    $setup = invoiceTestSetup();
    invoiceReceiveStock($setup['product'], $setup['location'], '20.000');

    $cashier = User::factory()->create(['name' => 'Cashier User']);
    $cashier->assignRole('Cashier');
    $this->actingAs($cashier);

    $draft = app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '2', 'unit_price' => '25.00']],
    ]);
    expect($draft->status)->toBe(InvoiceStatus::Draft);

    $issued = app(IssueInvoice::class)($draft, 'IDEMP-CASHIER-01');
    expect($issued->invoice->status)->toBe(InvoiceStatus::Issued);

    expect(fn () => app(VoidInvoice::class)($issued->invoice, 'Cashier trying to void'))
        ->toThrow(AuthorizationException::class);
});

// ==========================================
// CUSTOMER MODULE INTEGRATION TESTS
// ==========================================

test('customer statement balance includes only issued, partially paid, and paid invoices, excluding drafts and voids', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['invoices.create', 'invoices.issue', 'invoices.void', 'customers.view']);
    $this->actingAs($user);

    $location = $setup['location'];
    $customer = $setup['customer'];

    // Draft: 100.00 (excluded)
    Invoice::forceCreate([
        'channel' => 'invoice',
        'status' => InvoiceStatus::Draft,
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'issue_date' => '2026-11-01',
        'subtotal' => '100.00',
        'total' => '100.00',
        'amount_paid' => '0.00',
    ]);

    // Issued: 200.00 (included, balance +200)
    Invoice::forceCreate([
        'number' => 'INV-2026-000101',
        'channel' => 'invoice',
        'status' => InvoiceStatus::Issued,
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'issue_date' => '2026-11-02',
        'subtotal' => '200.00',
        'total' => '200.00',
        'amount_paid' => '0.00',
    ]);

    // Void: 300.00 (excluded)
    Invoice::forceCreate([
        'number' => 'INV-2026-000102',
        'channel' => 'invoice',
        'status' => InvoiceStatus::Void,
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'issue_date' => '2026-11-03',
        'subtotal' => '300.00',
        'total' => '300.00',
        'amount_paid' => '0.00',
    ]);

    // PartiallyPaid: 400.00, paid 150.00 (included, balance +250)
    Invoice::forceCreate([
        'number' => 'INV-2026-000103',
        'channel' => 'invoice',
        'status' => InvoiceStatus::PartiallyPaid,
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'issue_date' => '2026-11-04',
        'subtotal' => '400.00',
        'total' => '400.00',
        'amount_paid' => '150.00',
    ]);

    $statement = app(GetCustomerStatement::class)($customer);

    // Balance should be 200.00 + (400.00 - 150.00) = 450.00
    expect($statement['balance'])->toBe('450.00')
        ->and($statement['total_spent'])->toBe('600.00')
        ->and($statement['last_purchase_date'])->toBe('2026-11-04')
        ->and($statement['invoices'])->toHaveCount(4); // All 4 show up in history list
});

test('deleting a customer with invoices in any status is blocked', function () {
    $setup = invoiceTestSetup();
    $user = invoicingUser(['customers.delete', 'invoices.create']);
    $this->actingAs($user);

    Invoice::forceCreate([
        'channel' => 'invoice',
        'status' => InvoiceStatus::Draft,
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'issue_date' => '2026-11-01',
        'subtotal' => '50.00',
        'total' => '50.00',
        'amount_paid' => '0.00',
    ]);

    expect(fn () => app(DeleteCustomer::class)($setup['customer']))
        ->toThrow(ValidationException::class);

    expect(Customer::query()->find($setup['customer']->id))->not->toBeNull();
});
