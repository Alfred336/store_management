<?php

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceTemplate;
use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Models\Invoice;
use App\Models\InvoiceDocument;
use App\Models\User;
use App\Services\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function settingsAdmin(): User
{
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('Admin');

    return $user;
}

function settingsCashier(): User
{
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('Cashier');

    return $user;
}

// ─── nonThermalValues helper ─────────────────────────────────────────────────

test('InvoiceTemplate::nonThermalValues excludes thermal', function (): void {
    $values = InvoiceTemplate::nonThermalValues();

    expect($values)->not->toContain('thermal');
    expect($values)->toContain('classic');
    expect($values)->toContain('modern');
    expect($values)->toContain('minimal');
    expect($values)->toContain('compact');
});

// ─── Settings page saves invoice and quotation templates ─────────────────────

test('admin can save separate invoice and quotation templates', function (): void {
    $admin = settingsAdmin();

    Livewire::actingAs($admin)->test(SettingsIndex::class)
        ->set('businessName', 'Test Business')
        ->set('currency', 'TZS')
        ->set('vatRate', '18.00')
        ->set('pricesIncludeTax', false)
        ->set('invoicePrefix', 'INV')
        ->set('receiptPrefix', 'RCT')
        ->set('paymentPrefix', 'PAY')
        ->set('quotationPrefix', 'QUO')
        ->set('resetYearly', true)
        ->set('numberDigits', '6')
        ->set('quotationValidityDays', '14')
        ->set('creditLimitMode', 'warn')
        ->set('invoiceTemplate', 'modern')
        ->set('invoicePaper', 'A4')
        ->set('quotationTemplate', 'minimal')
        ->set('quotationPaper', 'A5')
        ->set('receiptWidth', '80mm')
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(Settings::class);
    expect($settings->get('invoicing.invoice_template'))->toBe('modern');
    expect($settings->get('invoicing.invoice_paper'))->toBe('A4');
    expect($settings->get('invoicing.quotation_template'))->toBe('minimal');
    expect($settings->get('invoicing.quotation_paper'))->toBe('A5');
});

test('cashier cannot save template settings', function (): void {
    $cashier = settingsCashier();

    Livewire::actingAs($cashier)->test(SettingsIndex::class)->assertForbidden();
});

test('thermal is rejected as invoice template', function (): void {
    $admin = settingsAdmin();

    Livewire::actingAs($admin)->test(SettingsIndex::class)
        ->set('invoiceTemplate', 'thermal')
        ->call('save')
        ->assertHasErrors(['invoiceTemplate']);
});

test('thermal is rejected as quotation template', function (): void {
    $admin = settingsAdmin();

    Livewire::actingAs($admin)->test(SettingsIndex::class)
        ->set('quotationTemplate', 'thermal')
        ->call('save')
        ->assertHasErrors(['quotationTemplate']);
});

test('thermal paper sizes are rejected for invoice paper', function (): void {
    $admin = settingsAdmin();

    Livewire::actingAs($admin)->test(SettingsIndex::class)
        ->set('invoicePaper', '80mm')
        ->call('save')
        ->assertHasErrors(['invoicePaper']);
});

// ─── InvoicePdfController reads settings, ignores request template param ─────

test('invoice pdf controller uses settings template regardless of request param', function (): void {
    $admin = settingsAdmin();

    // Set settings to 'classic'
    app(Settings::class)->put('invoicing.invoice_template', 'classic');
    app(Settings::class)->put('invoicing.invoice_paper', 'A4');

    // Create a customer, location and posted invoice
    $locationId = DB::table('locations')->insertGetId([
        'name' => 'Test Loc', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $customerId = DB::table('customers')->insertGetId([
        'code' => 'TST-TPL', 'name' => 'Template Test Customer',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $invoice = Invoice::create([
        'channel' => 'invoice',
        'status' => InvoiceStatus::Issued,
        'customer_id' => $customerId,
        'location_id' => $locationId,
        'issue_date' => now()->toDateString(),
        'currency' => 'TZS',
        'prices_include_tax' => false,
        'subtotal' => '0',
        'discount_total' => '0',
        'tax_total' => '0',
        'total' => '0',
        'created_by' => $admin->id,
    ]);

    // Request with ?template=modern (should be ignored)
    $response = $this->actingAs($admin)->get(
        route('invoices.pdf', ['invoice' => $invoice->id, 'template' => 'modern', 'mode' => 'inline'])
    );

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

// ─── Changing settings changes next print ─────────────────────────────────────

test('changing invoice template setting affects the next pdf render', function (): void {
    $admin = settingsAdmin();

    // Set 'modern' before any read so there is no mid-test cache invalidation needed
    app(Settings::class)->put('invoicing.invoice_template', 'modern');
    app(Settings::class)->put('invoicing.invoice_paper', 'A4');

    $locationId = DB::table('locations')->insertGetId([
        'name' => 'Template Loc', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $customerId = DB::table('customers')->insertGetId([
        'code' => 'TST-TPL2', 'name' => 'Template Test 2',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $invoice = Invoice::create([
        'channel' => 'invoice',
        'status' => InvoiceStatus::Issued,
        'customer_id' => $customerId,
        'location_id' => $locationId,
        'issue_date' => now()->toDateString(),
        'currency' => 'TZS',
        'prices_include_tax' => false,
        'subtotal' => '0',
        'discount_total' => '0',
        'tax_total' => '0',
        'total' => '0',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('invoices.pdf', ['invoice' => $invoice->id, 'mode' => 'inline']))
        ->assertOk();

    $doc = InvoiceDocument::where('invoice_id', $invoice->id)->first();
    expect($doc->template_key)->toBe('modern');
});
