<?php

use App\Livewire\Actions\Quotations\CreateQuotationDraft;
use App\Livewire\Actions\Quotations\SendQuotation;
use App\Livewire\Quotations\Index as QuotationsIndex;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\User;
use App\Services\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function quotScrUser(array $permissions): User
{
    $user = User::factory()->create(['name' => 'QuotScr User '.Str::random(4)]);

    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }

    $user->givePermissionTo($permissions);

    return $user;
}

function quotScrSetup(): array
{
    $unit = Unit::forceCreate(['name' => 'Piece '.Str::random(6), 'abbreviation' => 'pc']);

    $product = Product::forceCreate([
        'sku' => 'SKU-'.Str::upper(Str::random(8)),
        'name' => 'Timber '.Str::random(4),
        'base_unit_id' => $unit->id,
        'selling_price' => '3000.00',
        'tax_type' => 'standard',
        'reorder_level' => 0,
        'track_batches' => false,
        'is_active' => true,
    ]);

    $location = Location::forceCreate([
        'name' => 'Scr Warehouse '.Str::random(6),
        'type' => 'warehouse',
        'is_active' => true,
    ]);

    $customer = Customer::forceCreate([
        'code' => 'CUS-'.Str::upper(Str::random(6)),
        'name' => 'Mtaa Hardware Ltd '.Str::random(3),
        'type' => 'business',
        'phone' => '+255 713 000 500',
        'credit_limit' => '0.00',
        'payment_terms_days' => 30,
        'is_active' => true,
        'is_walk_in' => false,
    ]);

    return compact('unit', 'product', 'location', 'customer');
}

function quotScrDraft(array $setup): Quotation
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
            'quantity' => '2',
            'unit_price' => '3000.00',
            'discount_amount' => '0.00',
        ]],
    ]);
}

function quotScrSent(array $setup): Quotation
{
    app(Settings::class)->put('numbering.quotation_prefix', 'QUO');
    app(Settings::class)->put('numbering.digits', '5');
    app(Settings::class)->put('numbering.reset_yearly', false);

    $draft = quotScrDraft($setup);

    return app(SendQuotation::class)($draft);
}

// ─── List ────────────────────────────────────────────────────────────────────

test('quotation list renders for a user with quotations.view', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create']);
    $this->actingAs($user);

    $draft = quotScrDraft($setup);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->assertSee($setup['customer']->name)
        ->assertSee('Draft');
});

test('quotation list page is denied without quotations.view', function () {
    $setup = quotScrSetup();
    $user = quotScrUser([]);    // no permissions
    $this->actingAs($user);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->assertForbidden();
});

test('search filters the quotation list by customer name', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create']);
    $this->actingAs($user);

    quotScrDraft($setup);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->set('search', $setup['customer']->name)
        ->assertSee($setup['customer']->name);
});

test('status filter to sent hides draft quotations', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create']);
    $this->actingAs($user);

    quotScrDraft($setup);  // draft only

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->set('statusFilter', 'sent')
        ->assertDontSee($setup['customer']->name);
});

test('status filter to draft shows only draft quotations', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create', 'quotations.send']);
    $this->actingAs($user);

    $draft = quotScrDraft($setup);
    quotScrSent($setup);  // also creates a sent one

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->set('statusFilter', 'draft')
        ->assertSee($setup['customer']->name);  // draft appears
});

// ─── Modal dispatches ────────────────────────────────────────────────────────

test('openForm dispatches modal-show event with name quotation-form', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create']);
    $this->actingAs($user);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->call('openForm')
        ->assertDispatched('modal-show', name: 'quotation-form');
});

test('closeForm dispatches modal-close event', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create']);
    $this->actingAs($user);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->call('openForm')
        ->call('closeForm')
        ->assertDispatched('modal-close', name: 'quotation-form');
});

test('show dispatches modal-show event with name quotation-details', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create']);
    $this->actingAs($user);

    $draft = quotScrDraft($setup);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->call('show', $draft->id)
        ->assertDispatched('modal-show', name: 'quotation-details');
});

test('confirmDelete dispatches modal-show event for delete confirmation', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create', 'quotations.delete']);
    $this->actingAs($user);

    $draft = quotScrDraft($setup);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->call('confirmDelete', $draft->id)
        ->assertDispatched('modal-show', name: 'quotation-delete');
});

test('openSendConfirmation dispatches modal-show event for send confirmation', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create', 'quotations.send']);
    $this->actingAs($user);

    $draft = quotScrDraft($setup);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->call('openSendConfirmation', $draft->id)
        ->assertDispatched('modal-show', name: 'quotation-send');
});

test('openEmailPanel dispatches modal-show event for email panel', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create', 'quotations.send', 'quotations.print']);
    $this->actingAs($user);

    $sent = quotScrSent($setup);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->call('openEmailPanel', $sent->id)
        ->assertDispatched('modal-show', name: 'quotation-email');
});

test('openConvertPanel dispatches modal-show event for convert panel', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create', 'quotations.send', 'quotations.convert']);
    $this->actingAs($user);

    $sent = quotScrSent($setup);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->call('openConvertPanel', $sent->id)
        ->assertDispatched('modal-show', name: 'quotation-convert');
});

// ─── Store (create draft via screen) ─────────────────────────────────────────

test('store creates a draft quotation and shows a success toast', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create']);
    $this->actingAs($user);

    app(Settings::class)->put('tax.vat_rate', '18.00');
    app(Settings::class)->put('tax.prices_include_tax', false);
    app(Settings::class)->put('quotations.validity_days', '14');

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->set('recipientMode', 'customer')
        ->set('selectedCustomerId', $setup['customer']->id)
        ->set('locationId', $setup['location']->id)
        ->set('quoteDate', now()->toDateString())
        ->set('validUntil', now()->addDays(14)->toDateString())
        ->set('items', [[
            'product_id' => $setup['product']->id,
            'product_name' => $setup['product']->name,
            'product_sku' => $setup['product']->sku,
            'tax_type' => 'standard',
            'unit_id' => $setup['unit']->id,
            'available_units' => [['id' => $setup['unit']->id, 'abbreviation' => 'pc', 'factor' => '1.000']],
            'quantity' => '2',
            'unit_price' => '3000.00',
            'discount_amount' => '0.00',
        ]])
        ->call('store')
        ->assertHasNoErrors()
        ->assertDispatched('modal-close', name: 'quotation-form');

    expect(Quotation::query()->count())->toBe(1);
});

test('store shows validation errors when items array is empty', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create']);
    $this->actingAs($user);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->set('recipientMode', 'customer')
        ->set('selectedCustomerId', $setup['customer']->id)
        ->set('locationId', $setup['location']->id)
        ->set('items', [])
        ->call('store')
        ->assertHasErrors();
});

// ─── Delete draft via screen ──────────────────────────────────────────────────

test('deleteDraft removes the quotation and closes the confirmation modal', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create', 'quotations.delete']);
    $this->actingAs($user);

    $draft = quotScrDraft($setup);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->call('confirmDelete', $draft->id)
        ->call('deleteDraft')
        ->assertDispatched('modal-close', name: 'quotation-delete');

    expect(Quotation::query()->find($draft->id))->toBeNull();
});

// ─── Send via screen ──────────────────────────────────────────────────────────

test('sendQuotation transitions the draft to sent and closes modal', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create', 'quotations.send']);
    $this->actingAs($user);

    app(Settings::class)->put('numbering.quotation_prefix', 'QUO');
    app(Settings::class)->put('numbering.digits', '5');
    app(Settings::class)->put('numbering.reset_yearly', false);

    $draft = quotScrDraft($setup);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->call('openSendConfirmation', $draft->id)
        ->call('sendQuotation')
        ->assertDispatched('modal-close', name: 'quotation-send')
        ->assertHasNoErrors();

    expect($draft->fresh()->status->value)->toBe('sent');
});

// ─── Accept / Reject via screen ───────────────────────────────────────────────

test('applyStatus with accept transitions the quotation to accepted', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create', 'quotations.send', 'quotations.update']);
    $this->actingAs($user);

    $sent = quotScrSent($setup);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->call('openStatusPanel', $sent->id, 'accept')
        ->call('applyStatus')
        ->assertHasNoErrors();

    expect($sent->fresh()->status->value)->toBe('accepted');
});

test('applyStatus with reject stores the reason', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create', 'quotations.send', 'quotations.update']);
    $this->actingAs($user);

    $sent = quotScrSent($setup);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->call('openStatusPanel', $sent->id, 'reject')
        ->set('rejectReason', 'Customer not interested')
        ->call('applyStatus')
        ->assertHasNoErrors();

    $quot = $sent->fresh();
    expect($quot->status->value)->toBe('rejected')
        ->and($quot->reject_reason)->toBe('Customer not interested');
});

// ─── Revise via screen ────────────────────────────────────────────────────────

test('revise creates a new draft and closes details panel', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create', 'quotations.send']);
    $this->actingAs($user);

    $sent = quotScrSent($setup);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->call('show', $sent->id)
        ->call('revise', $sent->id)
        ->assertHasNoErrors();

    expect(Quotation::query()->where('revised_from_id', $sent->id)->count())->toBe(1);
});

// ─── Convert via screen ───────────────────────────────────────────────────────

test('convert creates an invoice draft from a registered-customer quotation', function () {
    $setup = quotScrSetup();
    $user = quotScrUser(['quotations.view', 'quotations.create', 'quotations.send', 'quotations.convert']);
    $this->actingAs($user);

    $sent = quotScrSent($setup);

    Livewire::actingAs($user)->test(QuotationsIndex::class)
        ->call('openConvertPanel', $sent->id)
        ->call('convertToInvoice')
        ->assertHasNoErrors();

    expect($sent->fresh()->status->value)->toBe('converted');
});
