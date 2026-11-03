<?php

use App\Enums\InvoiceStatus;
use App\Livewire\Actions\Invoicing\CreateInvoiceDraft;
use App\Livewire\Actions\Invoicing\DeleteInvoiceDraft;
use App\Livewire\Actions\Invoicing\IssueInvoice;
use App\Livewire\Actions\Invoicing\UpdateInvoiceDraft;
use App\Livewire\Actions\Invoicing\VoidInvoice;
use App\Livewire\Invoicing\Index as InvoicingIndex;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Services\Settings\Settings;
use App\Services\StockService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

// ─── Helpers ────────────────────────────────────────────────────────────────

function screenUser(array $permissions): User
{
    $user = User::factory()->create();
    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }
    $user->givePermissionTo($permissions);

    return $user;
}

/** @return array{unit: Unit, product: Product, location: Location, customer: Customer} */
function screenSetup(): array
{
    $unit = Unit::forceCreate(['name' => 'Piece '.Str::random(4), 'abbreviation' => 'pc']);

    $product = Product::forceCreate([
        'sku' => 'SKU-'.Str::upper(Str::random(6)),
        'name' => 'Test Product '.Str::random(4),
        'base_unit_id' => $unit->id,
        'tax_type' => 'standard',
        'reorder_level' => 0,
        'track_batches' => false,
        'is_active' => true,
    ]);

    $location = Location::forceCreate([
        'name' => 'Warehouse '.Str::random(4),
        'type' => 'warehouse',
        'is_active' => true,
    ]);

    $customer = Customer::forceCreate([
        'code' => 'CUS-'.Str::upper(Str::random(6)),
        'name' => 'Screen Test Customer '.Str::random(4),
        'type' => 'business',
        'phone' => '+255700000001',
        'credit_limit' => '0.00',
        'payment_terms_days' => 30,
        'is_active' => true,
        'is_walk_in' => false,
    ]);

    return compact('unit', 'product', 'location', 'customer');
}

function screenReceiveStock(Product $product, Location $location, string $qty): void
{
    app(StockService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'unit_id' => $product->base_unit_id,
        'quantity' => $qty,
        'type' => 'receipt',
        'reference' => 'TEST-IN',
        'person_name' => 'Test',
    ]);
}

function screenDraft(array $setup, ?int $qty = 5): Invoice
{
    return app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'issue_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $setup['product']->id,
            'quantity' => (string) $qty,
            'unit_price' => '1000.00',
        ]],
    ]);
}

// ─── List / search / filters ────────────────────────────────────────────────

test('invoice list renders for a user with invoices.view', function () {
    $setup = screenSetup();
    $user = screenUser(['invoices.view', 'invoices.create']);
    $this->actingAs($user);

    $draft = screenDraft($setup);

    Livewire::actingAs($user)->test(InvoicingIndex::class)
        ->assertSee($setup['customer']->name)
        ->assertSee('Draft');
});

test('search by number finds an issued invoice', function () {
    $setup = screenSetup();
    $user = screenUser(['invoices.view', 'invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    screenReceiveStock($setup['product'], $setup['location'], '50.000');
    $draft = screenDraft($setup);
    $result = app(IssueInvoice::class)($draft, Str::uuid()->toString());
    $number = $result->invoice->number;

    Livewire::actingAs($user)->test(InvoicingIndex::class)
        ->set('search', $number)
        ->assertSee($number);
});

test('search by customer name filters the list', function () {
    $setup = screenSetup();
    $user = screenUser(['invoices.view', 'invoices.create']);
    $this->actingAs($user);

    screenDraft($setup);

    Livewire::actingAs($user)->test(InvoicingIndex::class)
        ->set('search', $setup['customer']->name)
        ->assertSee($setup['customer']->name);
});

test('status filter shows only matching invoices', function () {
    $setup = screenSetup();
    $user = screenUser(['invoices.view', 'invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    screenReceiveStock($setup['product'], $setup['location'], '50.000');
    $draft = screenDraft($setup);
    app(IssueInvoice::class)($draft, Str::uuid()->toString());

    // Filter to drafts: issued invoice should NOT appear
    Livewire::actingAs($user)->test(InvoicingIndex::class)
        ->set('statusFilter', 'draft')
        ->assertDontSee($draft->fresh()->number ?? 'INV-');
});

test('overdue filter shows only past-due invoices with a balance', function () {
    $setup = screenSetup();
    $user = screenUser(['invoices.view', 'invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    screenReceiveStock($setup['product'], $setup['location'], '50.000');
    $draft = screenDraft($setup);
    // Set past due date on the draft before issuing
    $draft->update(['due_date' => now()->subDay()->toDateString()]);
    app(IssueInvoice::class)($draft, Str::uuid()->toString());
    $number = $draft->fresh()->number;

    Livewire::actingAs($user)->test(InvoicingIndex::class)
        ->set('overdueOnly', true)
        ->assertSee($number);
});

// ─── Create and edit draft ──────────────────────────────────────────────────

test('create a draft invoice from the screen', function () {
    $setup = screenSetup();
    $user = screenUser(['invoices.view', 'invoices.create']);
    $this->actingAs($user);

    Livewire::actingAs($user)->test(InvoicingIndex::class)
        ->call('openForm')
        ->assertSet('showForm', true)
        ->call('selectCustomer', $setup['customer']->id)
        ->set('locationId', $setup['location']->id)
        ->set('issueDate', now()->toDateString())
        ->call('addLineItem', $setup['product']->id)
        ->set('items.0.quantity', '3.000')
        ->set('items.0.unit_price', '500.00')
        ->call('store')
        ->assertHasNoErrors()
        ->assertSet('showForm', false);

    $this->assertDatabaseHas('invoices', [
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'status' => 'draft',
        'channel' => 'invoice',
    ]);
});

test('edit a draft invoice from the screen', function () {
    $setup = screenSetup();
    $user = screenUser(['invoices.view', 'invoices.create', 'invoices.update']);
    $this->actingAs($user);

    $draft = screenDraft($setup);

    Livewire::actingAs($user)->test(InvoicingIndex::class)
        ->call('openForm', $draft->id)
        ->assertSet('invoiceId', $draft->id)
        ->assertSet('showForm', true)
        ->set('notes', 'Updated note from screen test')
        ->call('update')
        ->assertHasNoErrors()
        ->assertSet('showForm', false);

    expect($draft->fresh()->notes)->toBe('Updated note from screen test');
});

// ─── Walk-in customer ────────────────────────────────────────────────────────

test('walk-in customer is not returned by the customer search', function () {
    $walkIn = ensureWalkInCustomerForTests();
    $user = screenUser(['invoices.view', 'invoices.create']);

    Livewire::actingAs($user)->test(InvoicingIndex::class)
        ->call('openForm')
        ->set('customerSearch', $walkIn->name)
        ->assertDontSee($walkIn->name);
});

test('walk-in customer is rejected when posted directly to store', function () {
    $setup = screenSetup();
    $walkIn = ensureWalkInCustomerForTests();
    $user = screenUser(['invoices.view', 'invoices.create']);
    $this->actingAs($user);

    Livewire::actingAs($user)->test(InvoicingIndex::class)
        ->call('openForm')
        ->set('selectedCustomerId', $walkIn->id)
        ->set('selectedCustomerName', $walkIn->name)
        ->set('locationId', $setup['location']->id)
        ->set('issueDate', now()->toDateString())
        ->call('addLineItem', $setup['product']->id)
        ->set('items.0.quantity', '1.000')
        ->set('items.0.unit_price', '100.00')
        ->call('store')
        ->assertHasErrors(['customer_id']);
});

// ─── Issuing ────────────────────────────────────────────────────────────────

test('issuing from the screen deducts stock', function () {
    $setup = screenSetup();
    $user = screenUser(['invoices.view', 'invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    screenReceiveStock($setup['product'], $setup['location'], '50.000');
    $draft = screenDraft($setup, 5);
    $movementsBefore = StockMovement::query()->count();

    Livewire::actingAs($user)->test(InvoicingIndex::class)
        ->call('openIssueConfirmation', $draft->id)
        ->assertSet('showIssueConfirmation', true)
        ->call('issueInvoice')
        ->assertSet('issueError', null)
        ->assertSet('showIssueConfirmation', false);

    $draft->refresh();
    expect($draft->status)->toBe(InvoiceStatus::Issued);
    expect(StockMovement::query()->count())->toBeGreaterThan($movementsBefore);
});

test('insufficient stock shows error message and invoice stays draft', function () {
    $setup = screenSetup();
    $user = screenUser(['invoices.view', 'invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    // No stock received — issue will fail
    $draft = screenDraft($setup, 1);

    $component = Livewire::actingAs($user)->test(InvoicingIndex::class)
        ->call('openIssueConfirmation', $draft->id)
        ->call('issueInvoice');

    $component->assertSet('showIssueConfirmation', true); // modal stays open
    $issueError = $component->get('issueError');
    expect($issueError)->not->toBeNull();

    expect($draft->fresh()->status)->toBe(InvoiceStatus::Draft);
    expect(StockMovement::query()->where('type', 'sale')->count())->toBe(0);
});

test('double submit with same idempotency key issues only once', function () {
    $setup = screenSetup();
    $user = screenUser(['invoices.view', 'invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    screenReceiveStock($setup['product'], $setup['location'], '50.000');
    $draft = screenDraft($setup, 1);

    $component = Livewire::actingAs($user)->test(InvoicingIndex::class)
        ->call('openIssueConfirmation', $draft->id);

    $key = $component->get('idempotencyKey');
    $component->call('issueInvoice')->assertSet('issueError', null);

    $movementsAfterFirst = StockMovement::query()->count();
    $draft->refresh();
    expect($draft->status)->toBe(InvoiceStatus::Issued);

    // Replay the same idempotency key via the action directly (simulating a retry)
    $result = app(IssueInvoice::class)($draft, $key);
    expect($result->invoice->id)->toBe($draft->id);
    expect(StockMovement::query()->count())->toBe($movementsAfterFirst); // no new movements
});

test('credit warning is shown after issuing over limit', function () {
    $setup = screenSetup();
    $user = screenUser(['invoices.view', 'invoices.create', 'invoices.issue']);
    $this->actingAs($user);

    // Set credit limit that will be exceeded by the invoice total (qty=5 * 1000 = 5000)
    $setup['customer']->update(['credit_limit' => '1000.00']);
    app(Settings::class)->put('customers.credit_limit_mode', 'warn');

    screenReceiveStock($setup['product'], $setup['location'], '50.000');
    $draft = screenDraft($setup, 5);

    $component = Livewire::actingAs($user)->test(InvoicingIndex::class)
        ->call('openIssueConfirmation', $draft->id)
        ->call('issueInvoice');

    // Modal should stay open with a credit warning
    $component->assertSet('showIssueConfirmation', true);
    $warning = $component->get('creditWarning');
    expect($warning)->not->toBeNull();

    // Invoice was issued despite warning
    expect($draft->fresh()->status)->toBe(InvoiceStatus::Issued);
});

// ─── Void ──────────────────────────────────────────────────────────────────

test('void with a reason works from the screen', function () {
    $setup = screenSetup();
    $user = screenUser(['invoices.view', 'invoices.create', 'invoices.issue', 'invoices.void']);
    $this->actingAs($user);

    screenReceiveStock($setup['product'], $setup['location'], '50.000');
    $draft = screenDraft($setup);
    app(IssueInvoice::class)($draft, Str::uuid()->toString());
    $draft->refresh();

    Livewire::actingAs($user)->test(InvoicingIndex::class)
        ->call('openVoidConfirmation', $draft->id)
        ->assertSet('showVoidConfirmation', true)
        ->set('voidReason', 'Customer cancelled order')
        ->call('voidInvoice')
        ->assertSet('voidError', null)
        ->assertSet('showVoidConfirmation', false);

    expect($draft->fresh()->status)->toBe(InvoiceStatus::Void);
});

test('void without a reason fails and modal stays open', function () {
    $setup = screenSetup();
    $user = screenUser(['invoices.view', 'invoices.create', 'invoices.issue', 'invoices.void']);
    $this->actingAs($user);

    screenReceiveStock($setup['product'], $setup['location'], '50.000');
    $draft = screenDraft($setup);
    app(IssueInvoice::class)($draft, Str::uuid()->toString());
    $draft->refresh();

    $component = Livewire::actingAs($user)->test(InvoicingIndex::class)
        ->call('openVoidConfirmation', $draft->id)
        ->set('voidReason', '')
        ->call('voidInvoice');

    $component->assertSet('showVoidConfirmation', true);
    $voidError = $component->get('voidError');
    expect($voidError)->not->toBeNull();

    expect($draft->fresh()->status)->toBe(InvoiceStatus::Issued);
});

// ─── Delete draft ──────────────────────────────────────────────────────────

test('draft can be deleted from the screen', function () {
    $setup = screenSetup();
    $user = screenUser(['invoices.view', 'invoices.create', 'invoices.update']);
    $this->actingAs($user);

    $draft = screenDraft($setup);
    $draftId = $draft->id;

    Livewire::actingAs($user)->test(InvoicingIndex::class)
        ->call('confirmDelete', $draftId)
        ->assertSet('showDeleteConfirmation', true)
        ->call('deleteDraft')
        ->assertSet('showDeleteConfirmation', false);

    expect(Invoice::query()->find($draftId))->toBeNull();
});

// ─── Authorization ─────────────────────────────────────────────────────────

test('viewer cannot create, issue, void or delete via direct calls', function () {
    $setup = screenSetup();
    $admin = screenUser(['invoices.view', 'invoices.create', 'invoices.update', 'invoices.issue', 'invoices.void']);
    $viewer = screenUser(['invoices.view']);

    // Setup as admin, then switch to viewer for assertions
    $this->actingAs($admin);
    screenReceiveStock($setup['product'], $setup['location'], '50.000');
    $issuedInvoice = app(IssueInvoice::class)(screenDraft($setup), Str::uuid()->toString())->invoice;
    $anotherDraft = screenDraft($setup, 2);

    $this->actingAs($viewer);

    // Viewer can reach the list
    Livewire::actingAs($viewer)->test(InvoicingIndex::class)->assertOk();

    // Cannot create
    expect(fn () => app(CreateInvoiceDraft::class)([
        'customer_id' => $setup['customer']->id,
        'location_id' => $setup['location']->id,
        'issue_date' => now()->toDateString(),
        'items' => [['product_id' => $setup['product']->id, 'quantity' => '1', 'unit_price' => '1000.00']],
    ]))->toThrow(AuthorizationException::class);

    // Cannot issue
    expect(fn () => app(IssueInvoice::class)($anotherDraft, Str::uuid()->toString()))
        ->toThrow(AuthorizationException::class);

    // Cannot void
    expect(fn () => app(VoidInvoice::class)($issuedInvoice, 'test reason'))
        ->toThrow(AuthorizationException::class);

    // Cannot delete
    expect(fn () => app(DeleteInvoiceDraft::class)($anotherDraft))
        ->toThrow(AuthorizationException::class);
});

test('cashier can issue but not void', function () {
    $setup = screenSetup();
    $cashier = screenUser(['invoices.view', 'invoices.create', 'invoices.update', 'invoices.issue']);
    $this->actingAs($cashier);

    screenReceiveStock($setup['product'], $setup['location'], '50.000');
    $draft = screenDraft($setup);

    // Cashier can issue
    Livewire::actingAs($cashier)->test(InvoicingIndex::class)
        ->call('openIssueConfirmation', $draft->id)
        ->call('issueInvoice')
        ->assertSet('issueError', null);

    $draft->refresh();
    expect($draft->status)->toBe(InvoiceStatus::Issued);

    // Cashier cannot void (test via action directly — Livewire does not re-throw AuthorizationException from ->call())
    expect(fn () => app(VoidInvoice::class)($draft->fresh(), 'reason'))
        ->toThrow(AuthorizationException::class);
});

test('only drafts can be edited or deleted via the screen', function () {
    $setup = screenSetup();
    $user = screenUser(['invoices.view', 'invoices.create', 'invoices.update', 'invoices.issue']);
    $this->actingAs($user);

    screenReceiveStock($setup['product'], $setup['location'], '50.000');
    $draft = screenDraft($setup);
    app(IssueInvoice::class)($draft, Str::uuid()->toString());
    $draft->refresh();

    // Policy denies update/delete on non-draft invoices (test via actions directly)
    expect(fn () => app(UpdateInvoiceDraft::class)($draft->fresh(), []))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(DeleteInvoiceDraft::class)($draft->fresh()))
        ->toThrow(AuthorizationException::class);
});
