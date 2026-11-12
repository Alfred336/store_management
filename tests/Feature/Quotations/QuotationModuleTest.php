<?php

use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Exceptions\InsufficientStockException;
use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Livewire\Actions\Invoicing\IssueInvoice;
use App\Livewire\Actions\Quotations\AcceptQuotation;
use App\Livewire\Actions\Quotations\ConvertQuotationToInvoice;
use App\Livewire\Actions\Quotations\CreateQuotationDraft;
use App\Livewire\Actions\Quotations\DeleteQuotationDraft;
use App\Livewire\Actions\Quotations\RejectQuotation;
use App\Livewire\Actions\Quotations\ReviseQuotation;
use App\Livewire\Actions\Quotations\SendQuotation;
use App\Livewire\Actions\Quotations\UpdateQuotationDraft;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Quotation;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Services\Settings\Settings;
use App\Services\StockService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function quotUser(array $permissions): User
{
    $user = User::factory()->create(['name' => 'Quot Test User '.Str::random(4)]);

    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }

    $user->givePermissionTo($permissions);

    return $user;
}

/** @return array{unit: Unit, boxUnit: Unit, product: Product, location: Location, customer: Customer} */
function quotSetup(): array
{
    $unit = Unit::forceCreate(['name' => 'Piece '.Str::random(6), 'abbreviation' => 'pc']);
    $boxUnit = Unit::forceCreate(['name' => 'Box '.Str::random(6), 'abbreviation' => 'box']);

    $product = Product::forceCreate([
        'sku' => 'SKU-'.Str::upper(Str::random(8)),
        'name' => 'Cement '.Str::random(4),
        'base_unit_id' => $unit->id,
        'selling_price' => '5000.00',
        'tax_type' => 'standard',
        'reorder_level' => 0,
        'track_batches' => false,
        'is_active' => true,
    ]);

    ProductUnit::forceCreate([
        'product_id' => $product->id,
        'unit_id' => $boxUnit->id,
        'factor' => '50.000',
    ]);

    $location = Location::forceCreate([
        'name' => 'Main Store '.Str::random(6),
        'type' => 'warehouse',
        'is_active' => true,
    ]);

    $customer = Customer::forceCreate([
        'code' => 'CUS-'.Str::upper(Str::random(6)),
        'name' => 'Bahari Traders Ltd',
        'type' => 'business',
        'phone' => '+255 711 100 200',
        'email' => 'info@bahari.example',
        'address' => 'Kariakoo, Dar es Salaam',
        'tin' => 'TIN-888-001',
        'vrn' => null,
        'credit_limit' => '0.00',
        'payment_terms_days' => 30,
        'is_active' => true,
        'is_walk_in' => false,
    ]);

    return compact('unit', 'boxUnit', 'product', 'location', 'customer');
}

function quotReceiveStock(Product $product, Location $location, string $qty): void
{
    app(StockService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => $qty,
    ]);
}

/** Create a minimal draft quotation. The caller must already be acting as the right user. */
function quotQuickDraft(array $setup, ?string $unitPrice = null, ?string $qty = '5'): Quotation
{
    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);
    app(Settings::class)->put('quotations.validity_days', '14');

    return app(CreateQuotationDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'quote_date' => now()->toDateString(),
        'valid_until' => now()->addDays(14)->toDateString(),
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['unit']->id,
            'quantity' => $qty,
            'unit_price' => $unitPrice ?? '5000.00',
            'discount_amount' => '0.00',
        ]],
    ]);
}

/** Create a draft and send it. */
function quotQuickSent(array $setup, ?string $unitPrice = null): Quotation
{
    $draft = quotQuickDraft($setup, $unitPrice);

    return app(SendQuotation::class)($draft);
}

// ─── DRAFT: totals and packaging-unit conversion ───────────────────────────

test('draft quotation computes correct totals with base unit', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view']);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);

    // 2 pieces × 1000.00 = 2000.00 subtotal; discount 200.00 → net 1800.00; 18% VAT = 324.00; total = 2124.00
    $draft = app(CreateQuotationDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['unit']->id,
            'quantity' => '2',
            'unit_price' => '1000.00',
            'discount_amount' => '200.00',
        ]],
    ]);

    expect($draft->status)->toBe(QuotationStatus::Draft)
        ->and($draft->number)->toBeNull()
        ->and($draft->revision)->toBe(1)
        ->and($draft->subtotal)->toBe('2000.00')
        ->and($draft->discount_total)->toBe('200.00')
        ->and($draft->tax_total)->toBe('324.00')
        ->and($draft->total)->toBe('2124.00')
        ->and($draft->created_by)->toBe($user->id);

    $item = $draft->items->first();
    expect($item->quantity)->toBe('2.000')
        ->and($item->base_quantity)->toBe('2.000')     // base unit, factor 1
        ->and($item->unit_price)->toBe('1000.00')
        ->and($item->discount_amount)->toBe('200.00')
        ->and($item->line_tax)->toBe('324.00')
        ->and($item->line_total)->toBe('2124.00');
});

test('draft quotation converts packaging unit to base quantity correctly', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view']);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);

    // 2 boxes × factor 50 → base_quantity = 100 pieces
    // unit_price 250000 per box; 2 × 250000 = 500000 subtotal; no discount; 18% = 90000; total = 590000
    $draft = app(CreateQuotationDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['boxUnit']->id,
            'quantity' => '2',
            'unit_price' => '250000.00',
            'discount_amount' => '0.00',
        ]],
    ]);

    $item = $draft->items->first();
    expect($item->quantity)->toBe('2.000')
        ->and($item->base_quantity)->toBe('100.000')   // 2 × 50
        ->and($item->unit_price)->toBe('250000.00')
        ->and($draft->subtotal)->toBe('500000.00')
        ->and($draft->tax_total)->toBe('90000.00')
        ->and($draft->total)->toBe('590000.00');
});

// ─── DRAFT: edited price is kept ───────────────────────────────────────────

test('explicitly provided unit_price is kept on draft create and update', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.update', 'quotations.view']);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '0.00');
    app(Settings::class)->put('tax.prices_include_tax', false);

    $draft = app(CreateQuotationDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['unit']->id,
            'quantity' => '1',
            'unit_price' => '7777.00',   // custom price
            'discount_amount' => '0.00',
        ]],
    ]);

    expect($draft->items->first()->unit_price)->toBe('7777.00');

    // Update with a different negotiated price
    $updated = app(UpdateQuotationDraft::class)($draft, [
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['unit']->id,
            'quantity' => '1',
            'unit_price' => '6500.00',
            'discount_amount' => '0.00',
        ]],
    ]);

    expect($updated->items->first()->unit_price)->toBe('6500.00');
});

// ─── DRAFT: prospect-only quotation ─────────────────────────────────────────

test('prospect-only quotation is allowed without a customer_id', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view']);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);
    app(Settings::class)->put('quotations.validity_days', '7');

    $draft = app(CreateQuotationDraft::class)([
        'prospect_name' => 'Nguyen Enterprises',
        'prospect_phone' => '+255 712 999 888',
        'prospect_email' => 'info@nguyen.example',
        'prospect_address' => 'Mwenge, Dar es Salaam',
        'location_id' => $setup['location']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['unit']->id,
            'quantity' => '10',
            'unit_price' => '5000.00',
            'discount_amount' => '0.00',
        ]],
    ]);

    expect($draft->customer_id)->toBeNull()
        ->and($draft->prospect_name)->toBe('Nguyen Enterprises')
        ->and($draft->prospect_phone)->toBe('+255 712 999 888')
        ->and($draft->prospect_email)->toBe('info@nguyen.example')
        ->and($draft->prospect_address)->toBe('Mwenge, Dar es Salaam')
        ->and($draft->status)->toBe(QuotationStatus::Draft);
});

test('draft creation fails if neither customer_id nor prospect_name is provided', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create']);
    $this->actingAs($user);

    expect(fn () => app(CreateQuotationDraft::class)([
        'location_id' => $setup['location']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'quantity' => '1',
            'unit_price' => '5000.00',
        ]],
    ]))->toThrow(ValidationException::class);
});

// ─── DRAFT: discount above limit needs approve_discount ──────────────────────

test('discount above max percent requires quotations.approve_discount permission', function () {
    $setup = quotSetup();
    // User with create but NOT approve_discount
    $user = quotUser(['quotations.create', 'quotations.view']);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '0.00');
    app(Settings::class)->put('tax.prices_include_tax', false);
    app(Settings::class)->put('pos.max_discount_percent', '10');  // 10% max

    // 10 units × 1000 = 10000, discount 2000 = 20% — exceeds limit
    expect(fn () => app(CreateQuotationDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['unit']->id,
            'quantity' => '10',
            'unit_price' => '1000.00',
            'discount_amount' => '2000.00',  // 20% — over limit
        ]],
    ]))->toThrow(AuthorizationException::class);
});

test('discount above max percent succeeds with quotations.approve_discount permission', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view', 'quotations.approve_discount']);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '0.00');
    app(Settings::class)->put('tax.prices_include_tax', false);
    app(Settings::class)->put('pos.max_discount_percent', '10');

    $draft = app(CreateQuotationDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['unit']->id,
            'quantity' => '10',
            'unit_price' => '1000.00',
            'discount_amount' => '2000.00',  // 20% — approved
        ]],
    ]);

    expect($draft->discount_total)->toBe('2000.00');
});

// ─── SEND: assigns number and locks ──────────────────────────────────────────

test('send assigns a QUO-prefixed number and transitions to sent', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view', 'quotations.send']);
    $this->actingAs($user);

    app(Settings::class)->put('numbering.quotation_prefix', 'QUO');
    app(Settings::class)->put('numbering.digits', '6');
    app(Settings::class)->put('numbering.reset_yearly', false);

    $draft = quotQuickDraft($setup);

    expect($draft->status)->toBe(QuotationStatus::Draft)
        ->and($draft->number)->toBeNull();

    $sent = app(SendQuotation::class)($draft);

    expect($sent->status)->toBe(QuotationStatus::Sent)
        ->and($sent->number)->not->toBeNull()
        ->and(str_starts_with((string) $sent->number, 'QUO'))->toBeTrue()
        ->and($sent->sent_by)->toBe($user->id)
        ->and($sent->sent_at)->not->toBeNull()
        ->and($sent->customer_name)->toBe($setup['customer']->name);  // snapshot taken
});

test('send snapshots prospect details when no customer_id', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view', 'quotations.send']);
    $this->actingAs($user);

    app(Settings::class)->put('quotations.validity_days', '14');
    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);

    $draft = app(CreateQuotationDraft::class)([
        'prospect_name' => 'Walk-in Smith',
        'prospect_phone' => '+255 700 000 001',
        'location_id' => $setup['location']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['unit']->id,
            'quantity' => '1',
            'unit_price' => '5000.00',
            'discount_amount' => '0.00',
        ]],
    ]);

    $sent = app(SendQuotation::class)($draft);

    expect($sent->customer_name)->toBe('Walk-in Smith')
        ->and($sent->customer_phone)->toBe('+255 700 000 001');
});

test('a sent quotation cannot be updated', function () {
    $setup = quotSetup();
    // The policy denies update on non-draft quotations, so AuthorizationException is thrown.
    $user = quotUser(['quotations.create', 'quotations.view', 'quotations.send', 'quotations.update']);
    $this->actingAs($user);

    $sent = quotQuickSent($setup);

    expect(fn () => app(UpdateQuotationDraft::class)($sent, [
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '1.00']],
    ]))->toThrow(AuthorizationException::class);
});

test('a sent quotation cannot be deleted', function () {
    $setup = quotSetup();
    // The policy denies delete on non-draft quotations, so AuthorizationException is thrown.
    $user = quotUser(['quotations.create', 'quotations.view', 'quotations.send', 'quotations.delete']);
    $this->actingAs($user);

    $sent = quotQuickSent($setup);

    expect(fn () => app(DeleteQuotationDraft::class)($sent))->toThrow(AuthorizationException::class);
});

// ─── NO STOCK MOVEMENT before invoice is issued ──────────────────────────────

test('no stock movement at any stage before an invoice is issued', function () {
    $setup = quotSetup();
    quotReceiveStock($setup['product'], $setup['location'], '100.000');

    $user = quotUser([
        'quotations.create', 'quotations.view', 'quotations.send',
        'quotations.update', 'quotations.convert',
    ]);
    $this->actingAs($user);

    $movementsBefore = StockMovement::query()->count();
    $levelBefore = (string) StockLevel::query()
        ->where('product_id', $setup['product']->id)
        ->where('location_id', $setup['location']->id)
        ->value('quantity');

    $draft = quotQuickDraft($setup);
    $sent = app(SendQuotation::class)($draft);
    $invoice = app(ConvertQuotationToInvoice::class)($sent);  // draft invoice created

    // Still no stock movement — IssueInvoice hasn't been called yet.
    $movementsAfterConvert = StockMovement::query()->count();
    $levelAfterConvert = (string) StockLevel::query()
        ->where('product_id', $setup['product']->id)
        ->where('location_id', $setup['location']->id)
        ->value('quantity');

    // $movementsBefore already includes the receive; draft/send/convert add zero movements.
    expect($movementsAfterConvert)->toBe($movementsBefore)
        ->and($levelAfterConvert)->toBe($levelBefore);
});

// ─── EXPIRY: computed, never stored ──────────────────────────────────────────

test('quotation with valid_until yesterday is treated as expired', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view', 'quotations.send']);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);

    $draft = app(CreateQuotationDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'quote_date' => now()->subDays(10)->toDateString(),
        'valid_until' => now()->subDay()->toDateString(),   // yesterday
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['unit']->id,
            'quantity' => '1',
            'unit_price' => '5000.00',
            'discount_amount' => '0.00',
        ]],
    ]);

    $sent = app(SendQuotation::class)($draft);

    // Status is still 'sent' in the DB (expiry is computed).
    expect($sent->status)->toBe(QuotationStatus::Sent)
        ->and($sent->isExpired())->toBeTrue()
        ->and($sent->displayStatus())->toBe('expired')
        ->and($sent->displayStatusLabel())->toBe('Expired');
});

test('accept is refused for an expired quotation', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view', 'quotations.send', 'quotations.update']);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);

    $draft = app(CreateQuotationDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'quote_date' => now()->subDays(10)->toDateString(),
        'valid_until' => now()->subDay()->toDateString(),
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['unit']->id,
            'quantity' => '1',
            'unit_price' => '5000.00',
            'discount_amount' => '0.00',
        ]],
    ]);

    $sent = app(SendQuotation::class)($draft);

    // Policy denies accept on expired quotations before the action even runs.
    expect(fn () => app(AcceptQuotation::class)($sent))->toThrow(AuthorizationException::class);
});

test('convert is refused for an expired quotation', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view', 'quotations.send', 'quotations.convert']);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);

    $draft = app(CreateQuotationDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'quote_date' => now()->subDays(10)->toDateString(),
        'valid_until' => now()->subDay()->toDateString(),
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['unit']->id,
            'quantity' => '1',
            'unit_price' => '5000.00',
            'discount_amount' => '0.00',
        ]],
    ]);

    $sent = app(SendQuotation::class)($draft);

    // Policy denies convert on expired quotations before the action even runs.
    expect(fn () => app(ConvertQuotationToInvoice::class)($sent))->toThrow(AuthorizationException::class);
});

// ─── ACCEPT / REJECT ─────────────────────────────────────────────────────────

test('accept transitions a sent quotation to accepted', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view', 'quotations.send', 'quotations.update']);
    $this->actingAs($user);

    $sent = quotQuickSent($setup);
    $accepted = app(AcceptQuotation::class)($sent);

    expect($accepted->status)->toBe(QuotationStatus::Accepted)
        ->and($accepted->accepted_at)->not->toBeNull();
});

test('reject transitions a sent quotation to rejected and stores reason', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view', 'quotations.send', 'quotations.update']);
    $this->actingAs($user);

    $sent = quotQuickSent($setup);
    $rejected = app(RejectQuotation::class)($sent, 'Too expensive');

    expect($rejected->status)->toBe(QuotationStatus::Rejected)
        ->and($rejected->rejected_at)->not->toBeNull()
        ->and($rejected->reject_reason)->toBe('Too expensive');
});

// ─── REVISE ──────────────────────────────────────────────────────────────────

test('revise creates revision 2 and keeps the original revision 1 intact', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view', 'quotations.send']);
    $this->actingAs($user);

    $sent = quotQuickSent($setup);
    $originalId = $sent->id;

    $revision2 = app(ReviseQuotation::class)($sent);

    // Original is unchanged.
    $original = Quotation::query()->findOrFail($originalId);
    expect($original->status)->toBe(QuotationStatus::Sent)
        ->and($original->revision)->toBe(1);

    // New draft is revision 2 and links back.
    expect($revision2->status)->toBe(QuotationStatus::Draft)
        ->and($revision2->revision)->toBe(2)
        ->and($revision2->revised_from_id)->toBe($originalId)
        ->and($revision2->number)->toBeNull()
        ->and($revision2->items)->toHaveCount($sent->items->count());
});

test('revise can be applied to a rejected quotation', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view', 'quotations.send', 'quotations.update']);
    $this->actingAs($user);

    $sent = quotQuickSent($setup);
    $rejected = app(RejectQuotation::class)($sent, 'Price too high');
    $revision = app(ReviseQuotation::class)($rejected);

    expect($revision->status)->toBe(QuotationStatus::Draft)
        ->and($revision->revision)->toBe(2);
});

test('revise can be applied to an expired quotation', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view', 'quotations.send']);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);

    $draft = app(CreateQuotationDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'quote_date' => now()->subDays(10)->toDateString(),
        'valid_until' => now()->subDay()->toDateString(),
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['unit']->id,
            'quantity' => '1',
            'unit_price' => '5000.00',
            'discount_amount' => '0.00',
        ]],
    ]);

    $sent = app(SendQuotation::class)($draft);
    $revision = app(ReviseQuotation::class)($sent);

    expect($revision->status)->toBe(QuotationStatus::Draft)
        ->and($revision->revision)->toBe(2);
});

// ─── CONVERT ─────────────────────────────────────────────────────────────────

test('convert creates one invoice draft with the quoted prices and links both records', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view', 'quotations.send', 'quotations.convert']);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);

    // Use a custom price to verify it carries over verbatim.
    $sent = quotQuickSent($setup, '8888.00');
    $invoice = app(ConvertQuotationToInvoice::class)($sent);

    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->channel)->toBe('invoice')
        ->and($invoice->quotation_id)->toBe($sent->id)
        ->and($invoice->customer_id)->toBe($setup['customer']->id);

    // Invoice line carries the quoted price verbatim.
    $invItem = $invoice->items->first();
    expect($invItem->unit_price)->toBe('8888.00');

    // Quotation is now Converted and links to the invoice.
    $quot = $sent->fresh();
    expect($quot->status)->toBe(QuotationStatus::Converted)
        ->and($quot->converted_invoice_id)->toBe($invoice->id);
});

test('a second convert on the same quotation is refused', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view', 'quotations.send', 'quotations.convert']);
    $this->actingAs($user);

    $sent = quotQuickSent($setup);
    app(ConvertQuotationToInvoice::class)($sent);

    // Reload — now Converted.
    $converted = $sent->fresh();

    // Policy denies convert when converted_invoice_id is already set.
    expect(fn () => app(ConvertQuotationToInvoice::class)($converted))
        ->toThrow(AuthorizationException::class);
});

test('convert requires a registered customer for a prospect quotation', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view', 'quotations.send', 'quotations.convert']);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);

    // Create a prospect-only draft then send it.
    $draft = app(CreateQuotationDraft::class)([
        'prospect_name' => 'Prospect Corp',
        'location_id' => $setup['location']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['unit']->id,
            'quantity' => '1',
            'unit_price' => '5000.00',
            'discount_amount' => '0.00',
        ]],
    ]);

    $sent = app(SendQuotation::class)($draft);

    // No customer_id passed — must fail.
    expect(fn () => app(ConvertQuotationToInvoice::class)($sent, null))
        ->toThrow(ValidationException::class);

    // With a registered customer it should succeed.
    $invoice = app(ConvertQuotationToInvoice::class)($sent, $setup['customer']->id);
    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->customer_id)->toBe($setup['customer']->id);
});

// ─── CONVERT + ISSUE: stock and credit check ─────────────────────────────────

test('issuing the converted invoice deducts stock and runs the credit check', function () {
    app(EnsureSystemRoles::class)();

    $setup = quotSetup();
    quotReceiveStock($setup['product'], $setup['location'], '100.000');

    $user = quotUser([
        'quotations.create', 'quotations.view', 'quotations.send', 'quotations.convert',
        'invoices.create', 'invoices.issue',
    ]);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);
    app(Settings::class)->put('customers.credit_limit_mode', 'warn');

    $levelBefore = (string) StockLevel::query()
        ->where('product_id', $setup['product']->id)
        ->where('location_id', $setup['location']->id)
        ->value('quantity');

    $sent = quotQuickSent($setup);               // 5 units
    $invoice = app(ConvertQuotationToInvoice::class)($sent);

    // No stock deducted yet.
    $levelAfterConvert = (string) StockLevel::query()
        ->where('product_id', $setup['product']->id)
        ->where('location_id', $setup['location']->id)
        ->value('quantity');

    expect($levelAfterConvert)->toBe($levelBefore);

    // Issue the invoice — now stock is deducted.
    $result = app(IssueInvoice::class)($invoice, 'IDEMP-QUOT-ISSUE-'.Str::random(8));

    $levelAfterIssue = (string) StockLevel::query()
        ->where('product_id', $setup['product']->id)
        ->where('location_id', $setup['location']->id)
        ->value('quantity');

    expect($result->invoice->status)->toBe(InvoiceStatus::Issued)
        ->and(bccomp($levelAfterIssue, bcsub($levelBefore, '5.000', 3), 3))->toBe(0);
});

test('issuing the converted invoice fails when insufficient stock', function () {
    app(EnsureSystemRoles::class)();

    $setup = quotSetup();
    // No stock received — trying to issue 5 units should fail.

    $user = quotUser([
        'quotations.create', 'quotations.view', 'quotations.send', 'quotations.convert',
        'invoices.create', 'invoices.issue',
    ]);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);

    $sent = quotQuickSent($setup);
    $invoice = app(ConvertQuotationToInvoice::class)($sent);

    expect(fn () => app(IssueInvoice::class)($invoice, 'IDEMP-QUOT-NOSTOCK-'.Str::random(8)))
        ->toThrow(InsufficientStockException::class);
});

// ─── DELETE DRAFT ─────────────────────────────────────────────────────────────

test('draft quotation can be deleted and its items are removed', function () {
    $setup = quotSetup();
    $user = quotUser(['quotations.create', 'quotations.view', 'quotations.delete']);
    $this->actingAs($user);

    $draft = quotQuickDraft($setup);
    $id = $draft->id;

    app(DeleteQuotationDraft::class)($draft);

    expect(Quotation::query()->find($id))->toBeNull();
});

// ─── PERMISSIONS: Viewer and Storekeeper cannot create/send/convert ───────────

test('Viewer cannot create a quotation draft', function () {
    app(EnsureSystemRoles::class)();

    $setup = quotSetup();
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    $this->actingAs($viewer);

    expect(fn () => app(CreateQuotationDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'unit_id' => $setup['unit']->id,
            'quantity' => '1',
            'unit_price' => '5000.00',
            'discount_amount' => '0.00',
        ]],
    ]))->toThrow(AuthorizationException::class);
});

test('Storekeeper cannot send a quotation', function () {
    app(EnsureSystemRoles::class)();

    $setup = quotSetup();
    $adminUser = quotUser(['quotations.create', 'quotations.view', 'quotations.send']);
    $this->actingAs($adminUser);

    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);
    app(Settings::class)->put('quotations.validity_days', '14');

    $draft = quotQuickDraft($setup);

    // Switch to storekeeper.
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole('Storekeeper');
    $this->actingAs($storekeeper);

    expect(fn () => app(SendQuotation::class)($draft))
        ->toThrow(AuthorizationException::class);
});

test('Viewer cannot convert a quotation', function () {
    app(EnsureSystemRoles::class)();

    $setup = quotSetup();
    $adminUser = quotUser(['quotations.create', 'quotations.view', 'quotations.send']);
    $this->actingAs($adminUser);

    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);
    app(Settings::class)->put('quotations.validity_days', '14');

    $sent = quotQuickSent($setup);

    // Switch to viewer.
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer);

    expect(fn () => app(ConvertQuotationToInvoice::class)($sent))
        ->toThrow(AuthorizationException::class);
});
