<?php

use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Livewire\Actions\Invoicing\CreateInvoiceDraft;
use App\Livewire\Actions\Invoicing\DeleteInvoiceDraft;
use App\Livewire\Actions\Invoicing\IssueInvoice;
use App\Livewire\Actions\Invoicing\UpdateInvoiceDraft;
use App\Livewire\Actions\Invoicing\VoidInvoice;
use App\Livewire\Actions\Quotations\AcceptQuotation;
use App\Livewire\Actions\Quotations\ConvertQuotationToInvoice;
use App\Livewire\Actions\Quotations\CreateQuotationDraft;
use App\Livewire\Actions\Quotations\DeleteQuotationDraft;
use App\Livewire\Actions\Quotations\RejectQuotation;
use App\Livewire\Actions\Quotations\ReviseQuotation;
use App\Livewire\Actions\Quotations\SendQuotation;
use App\Livewire\Actions\Quotations\UpdateQuotationDraft;
use App\Livewire\Invoicing\Index as InvoicingIndex;
use App\Livewire\Quotations\Index as QuotationsIndex;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function invUser(array $permissions, array $locationIds = []): User
{
    $user = User::factory()->create();

    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }

    $user->givePermissionTo($permissions);

    if (! empty($locationIds)) {
        $user->locations()->sync($locationIds);
    }

    return $user;
}

function invAdmin(array $permissions = []): User
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

    // Intentionally no location assignments — Admin role bypasses location restriction.
    return $user;
}

function makeInvoiceProduct(): Product
{
    $unit = Unit::forceCreate(['name' => 'Piece '.uniqid(), 'abbreviation' => 'pc']);

    return Product::forceCreate([
        'sku' => 'SKU-'.strtoupper(substr(md5(uniqid()), 0, 8)),
        'name' => 'Test Product '.uniqid(),
        'base_unit_id' => $unit->id,
        'selling_price' => '10000.00',
        'tax_type' => 'standard',
        'reorder_level' => 0,
        'track_batches' => false,
        'is_active' => true,
    ]);
}

function makeInvoiceCustomer(): Customer
{
    return Customer::forceCreate([
        'code' => 'CUS-'.strtoupper(substr(md5(uniqid()), 0, 6)),
        'name' => 'Test Customer '.uniqid(),
        'type' => 'individual',
        'credit_limit' => '0.00',
        'payment_terms_days' => 30,
        'is_active' => true,
        'is_walk_in' => false,
    ]);
}

function makeDraftInvoice(Location $location): Invoice
{
    return Invoice::forceCreate([
        'channel' => 'invoice',
        'status' => InvoiceStatus::Draft,
        'customer_id' => makeInvoiceCustomer()->id,
        'location_id' => $location->id,
        'issue_date' => now()->toDateString(),
        'currency' => 'TZS',
        'prices_include_tax' => false,
        'subtotal' => '10000.00',
        'discount_total' => '0.00',
        'tax_total' => '1800.00',
        'total' => '11800.00',
        'amount_paid' => '0.00',
    ]);
}

function makeDraftQuotation(Location $location): Quotation
{
    return Quotation::forceCreate([
        'status' => QuotationStatus::Draft,
        'customer_id' => makeInvoiceCustomer()->id,
        'location_id' => $location->id,
        'quote_date' => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
        'currency' => 'TZS',
        'prices_include_tax' => false,
        'subtotal' => '10000.00',
        'discount_total' => '0.00',
        'tax_total' => '1800.00',
        'total' => '11800.00',
        'revision' => 1,
    ]);
}

function makeSentQuotation(Location $location): Quotation
{
    return Quotation::forceCreate([
        'status' => QuotationStatus::Sent,
        'customer_id' => makeInvoiceCustomer()->id,
        'location_id' => $location->id,
        'quote_date' => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
        'currency' => 'TZS',
        'prices_include_tax' => false,
        'subtotal' => '10000.00',
        'discount_total' => '0.00',
        'tax_total' => '1800.00',
        'total' => '11800.00',
        'revision' => 1,
    ]);
}

// ─── Invoice list: location scoping ───────────────────────────────────────────

test('invoice list shows only allowed-location invoices for restricted user', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $invA = makeDraftInvoice($locA);
    $invB = makeDraftInvoice($locB);

    $user = invUser(['invoices.view'], [$locA->id]);

    Livewire::actingAs($user)->test(InvoicingIndex::class)
        ->assertViewHas('invoices', fn ($invoices) => $invoices->contains('id', $invA->id))
        ->assertViewHas('invoices', fn ($invoices) => ! $invoices->contains('id', $invB->id));
});

test('invoice list shows all locations for Admin', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $invA = makeDraftInvoice($locA);
    $invB = makeDraftInvoice($locB);

    $admin = invAdmin(['invoices.view']);

    Livewire::actingAs($admin)->test(InvoicingIndex::class)
        ->assertViewHas('invoices', fn ($invoices) => $invoices->contains('id', $invA->id))
        ->assertViewHas('invoices', fn ($invoices) => $invoices->contains('id', $invB->id));
});

// ─── Quotation list: location scoping ─────────────────────────────────────────

test('quotation list shows only allowed-location quotations for restricted user', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $quotA = makeDraftQuotation($locA);
    $quotB = makeDraftQuotation($locB);

    $user = invUser(['quotations.view'], [$locA->id]);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->assertViewHas('quotations', fn ($quotations) => $quotations->contains('id', $quotA->id))
        ->assertViewHas('quotations', fn ($quotations) => ! $quotations->contains('id', $quotB->id));
});

test('quotation list shows all locations for Admin', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $quotA = makeDraftQuotation($locA);
    $quotB = makeDraftQuotation($locB);

    $admin = invAdmin(['quotations.view']);

    Livewire::actingAs($admin)->test(QuotationsIndex::class)
        ->assertViewHas('quotations', fn ($quotations) => $quotations->contains('id', $quotA->id))
        ->assertViewHas('quotations', fn ($quotations) => $quotations->contains('id', $quotB->id));
});

// ─── Invoice Actions: location guard ──────────────────────────────────────────

test('CreateInvoiceDraft is blocked for restricted user targeting locB', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $user = invUser(['invoices.create'], [$locA->id]);
    $this->actingAs($user);

    expect(fn () => app(CreateInvoiceDraft::class)([
        'customer_id' => makeInvoiceCustomer()->id,
        'location_id' => $locB->id,
        'items' => [],
    ]))->toThrow(AuthorizationException::class);
});

test('UpdateInvoiceDraft is blocked for restricted user targeting locB invoice', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $invoice = makeDraftInvoice($locB);
    $user = invUser(['invoices.update'], [$locA->id]);
    $this->actingAs($user);

    expect(fn () => app(UpdateInvoiceDraft::class)($invoice, [
        'location_id' => $locB->id,
        'items' => [],
    ]))->toThrow(AuthorizationException::class);
});

test('DeleteInvoiceDraft is blocked for restricted user targeting locB invoice', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $invoice = makeDraftInvoice($locB);
    $user = invUser(['invoices.update'], [$locA->id]);
    $this->actingAs($user);

    expect(fn () => app(DeleteInvoiceDraft::class)($invoice))
        ->toThrow(AuthorizationException::class);
});

test('IssueInvoice is blocked for restricted user targeting locB invoice', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $invoice = makeDraftInvoice($locB);
    $user = invUser(['invoices.issue'], [$locA->id]);
    $this->actingAs($user);

    expect(fn () => app(IssueInvoice::class)($invoice, 'IDEMP-BLOCK-'.uniqid()))
        ->toThrow(AuthorizationException::class);
});

test('VoidInvoice is blocked for restricted user targeting locB invoice', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $invoice = makeDraftInvoice($locB);
    $user = invUser(['invoices.void'], [$locA->id]);
    $this->actingAs($user);

    expect(fn () => app(VoidInvoice::class)($invoice, 'Blocked'))
        ->toThrow(AuthorizationException::class);
});

// ─── Quotation Actions: location guard ────────────────────────────────────────

test('CreateQuotationDraft is blocked for restricted user targeting locB', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $user = invUser(['quotations.create'], [$locA->id]);
    $this->actingAs($user);

    expect(fn () => app(CreateQuotationDraft::class)([
        'customer_id' => makeInvoiceCustomer()->id,
        'location_id' => $locB->id,
        'items' => [],
    ]))->toThrow(AuthorizationException::class);
});

test('UpdateQuotationDraft is blocked for restricted user targeting locB quotation', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $quotation = makeDraftQuotation($locB);
    $user = invUser(['quotations.update'], [$locA->id]);
    $this->actingAs($user);

    expect(fn () => app(UpdateQuotationDraft::class)($quotation, [
        'location_id' => $locB->id,
        'items' => [],
    ]))->toThrow(AuthorizationException::class);
});

test('DeleteQuotationDraft is blocked for restricted user targeting locB quotation', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $quotation = makeDraftQuotation($locB);
    $user = invUser(['quotations.delete'], [$locA->id]);
    $this->actingAs($user);

    expect(fn () => app(DeleteQuotationDraft::class)($quotation))
        ->toThrow(AuthorizationException::class);
});

test('SendQuotation is blocked for restricted user targeting locB quotation', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $quotation = makeDraftQuotation($locB);
    $user = invUser(['quotations.send'], [$locA->id]);
    $this->actingAs($user);

    expect(fn () => app(SendQuotation::class)($quotation))
        ->toThrow(AuthorizationException::class);
});

test('AcceptQuotation is blocked for restricted user targeting locB quotation', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $quotation = makeSentQuotation($locB);
    $user = invUser(['quotations.update'], [$locA->id]);
    $this->actingAs($user);

    expect(fn () => app(AcceptQuotation::class)($quotation))
        ->toThrow(AuthorizationException::class);
});

test('RejectQuotation is blocked for restricted user targeting locB quotation', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $quotation = makeSentQuotation($locB);
    $user = invUser(['quotations.update'], [$locA->id]);
    $this->actingAs($user);

    expect(fn () => app(RejectQuotation::class)($quotation, 'Blocked'))
        ->toThrow(AuthorizationException::class);
});

test('ReviseQuotation is blocked for restricted user targeting locB quotation', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $quotation = makeSentQuotation($locB);
    $user = invUser(['quotations.create'], [$locA->id]);
    $this->actingAs($user);

    expect(fn () => app(ReviseQuotation::class)($quotation))
        ->toThrow(AuthorizationException::class);
});

test('ConvertQuotationToInvoice is blocked for restricted user targeting locB quotation', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $quotation = makeSentQuotation($locB);
    $user = invUser(['quotations.convert'], [$locA->id]);
    $this->actingAs($user);

    expect(fn () => app(ConvertQuotationToInvoice::class)($quotation))
        ->toThrow(AuthorizationException::class);
});

// ─── PDF routes: 403 for restricted user ──────────────────────────────────────

test('invoice PDF route returns 403 for restricted user with locB invoice', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $invoice = makeDraftInvoice($locB);
    $user = invUser(['invoices.print'], [$locA->id]);

    $this->actingAs($user)
        ->get(route('invoices.pdf', $invoice))
        ->assertStatus(403);
});

test('quotation PDF route returns 403 for restricted user with locB quotation', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $quotation = makeDraftQuotation($locB);
    $user = invUser(['quotations.print'], [$locA->id]);

    $this->actingAs($user)
        ->get(route('quotations.pdf', $quotation))
        ->assertStatus(403);
});

// ─── Admin: unrestricted access ───────────────────────────────────────────────

test('Admin with no location assignments sees invoices at all locations', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $invA = makeDraftInvoice($locA);
    $invB = makeDraftInvoice($locB);

    $admin = invAdmin(['invoices.view']);

    Livewire::actingAs($admin)->test(InvoicingIndex::class)
        ->assertViewHas('invoices', fn ($invoices) => $invoices->contains('id', $invA->id))
        ->assertViewHas('invoices', fn ($invoices) => $invoices->contains('id', $invB->id));
});

test('Admin with no location assignments sees quotations at all locations', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $quotA = makeDraftQuotation($locA);
    $quotB = makeDraftQuotation($locB);

    $admin = invAdmin(['quotations.view']);

    Livewire::actingAs($admin)->test(QuotationsIndex::class)
        ->assertViewHas('quotations', fn ($quotations) => $quotations->contains('id', $quotA->id))
        ->assertViewHas('quotations', fn ($quotations) => $quotations->contains('id', $quotB->id));
});

test('Admin can invoke CreateInvoiceDraft at any location', function (): void {
    $locB = Location::factory()->create(['is_active' => true]);
    $product = makeInvoiceProduct();
    $customer = makeInvoiceCustomer();

    $admin = invAdmin(['invoices.create']);
    $this->actingAs($admin);

    $invoice = app(CreateInvoiceDraft::class)([
        'customer_id' => $customer->id,
        'location_id' => $locB->id,
        'items' => [
            [
                'product_id' => $product->id,
                'unit_id' => $product->base_unit_id,
                'quantity' => '1',
                'unit_price' => '5000.00',
                'discount_amount' => '0.00',
            ],
        ],
    ]);

    expect($invoice->location_id)->toBe($locB->id)
        ->and($invoice->status)->toBe(InvoiceStatus::Draft);
});

test('Admin can invoke CreateQuotationDraft at any location', function (): void {
    $locB = Location::factory()->create(['is_active' => true]);
    $product = makeInvoiceProduct();
    $customer = makeInvoiceCustomer();

    $admin = invAdmin(['quotations.create']);
    $this->actingAs($admin);

    $quotation = app(CreateQuotationDraft::class)([
        'customer_id' => $customer->id,
        'location_id' => $locB->id,
        'items' => [
            [
                'product_id' => $product->id,
                'unit_id' => $product->base_unit_id,
                'quantity' => '2',
                'unit_price' => '3000.00',
                'discount_amount' => '0.00',
            ],
        ],
    ]);

    expect($quotation->location_id)->toBe($locB->id)
        ->and($quotation->status)->toBe(QuotationStatus::Draft);
});
