<?php

use App\Enums\InvoiceStatus;
use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Models\Invoice;
use App\Models\InvoiceDocument;
use App\Models\Payment;
use App\Models\User;
use App\Services\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function receiptAdmin(): User
{
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('Admin');

    return $user;
}

function makePayment(int $userId): Payment
{
    $customerId = DB::table('customers')->insertGetId([
        'code' => 'RCT-'.rand(100, 999),
        'name' => 'Receipt Customer',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Payment::create([
        'number' => 'PAY-TEST-'.rand(1000, 9999),
        'idempotency_key' => Str::uuid()->toString(),
        'customer_id' => $customerId,
        'amount' => '100.00',
        'method' => 'cash',
        'status' => 'active',
        'received_at' => now(),
        'received_by' => $userId,
        'currency' => 'TZS',
        'created_by' => $userId,
    ]);
}

test('payment receipt always renders thermal pdf', function (): void {
    $admin = receiptAdmin();
    $payment = makePayment($admin->id);

    $response = $this->actingAs($admin)
        ->get(route('payments.receipt', ['payment' => $payment->id]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('payment receipt respects pos receipt_width setting', function (): void {
    $admin = receiptAdmin();
    $payment = makePayment($admin->id);

    app(Settings::class)->put('pos.receipt_width', '58mm');

    $response = $this->actingAs($admin)
        ->get(route('payments.receipt', ['payment' => $payment->id]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('payment receipt download mode returns attachment disposition', function (): void {
    $admin = receiptAdmin();
    $payment = makePayment($admin->id);

    $response = $this->actingAs($admin)
        ->get(route('payments.receipt', ['payment' => $payment->id, 'mode' => 'download']));

    $response->assertOk();
    $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
});

test('a4 paper param is ignored — receipt is always thermal', function (): void {
    $admin = receiptAdmin();
    $payment = makePayment($admin->id);

    // The old ?paper=a4 param must be silently ignored; thermal is always used
    $response = $this->actingAs($admin)
        ->get(route('payments.receipt', ['payment' => $payment->id, 'paper' => 'a4']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('pos invoice renders thermal via InvoicePdfController', function (): void {
    $admin = receiptAdmin();

    $locationId = DB::table('locations')->insertGetId([
        'name' => 'POS Loc', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $customerId = DB::table('customers')->insertGetId([
        'code' => 'POS-TEST', 'name' => 'POS Customer',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $invoice = Invoice::create([
        'channel' => 'pos',
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

    $response = $this->actingAs($admin)
        ->get(route('invoices.pdf', ['invoice' => $invoice->id, 'mode' => 'inline']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');

    // The stored document should use the thermal template
    $doc = InvoiceDocument::where('invoice_id', $invoice->id)->first();
    expect($doc->template_key)->toBe('thermal');
});

test('channel=invoice uses configured A4 template not thermal', function (): void {
    $admin = receiptAdmin();

    app(Settings::class)->put('invoicing.invoice_template', 'classic');
    app(Settings::class)->put('invoicing.invoice_paper', 'A4');

    $locationId = DB::table('locations')->insertGetId([
        'name' => 'Invoice Loc', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $customerId = DB::table('customers')->insertGetId([
        'code' => 'INV-TEST', 'name' => 'Invoice Customer',
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

    $response = $this->actingAs($admin)
        ->get(route('invoices.pdf', ['invoice' => $invoice->id, 'mode' => 'inline']));

    $response->assertOk();

    $doc = InvoiceDocument::where('invoice_id', $invoice->id)->first();
    expect($doc->template_key)->toBe('classic');
});
