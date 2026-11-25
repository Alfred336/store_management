<?php

use App\Enums\InvoiceStatus;
use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Livewire\Actions\Customers\GetCustomerStatement;
use App\Livewire\Actions\Payments\AllocateCredit;
use App\Livewire\Actions\Payments\RecordPayment;
use App\Livewire\Actions\Payments\ReversePayment;
use App\Livewire\Payments\Index as PaymentsIndex;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\Access\LocationAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function payAccessUser(array $permissions, array $locationIds = []): User
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

function payAccessAdmin(array $permissions = []): User
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

function payAccessCustomer(): Customer
{
    return Customer::forceCreate([
        'code' => 'PAC-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'name' => 'Access Test Customer '.random_int(1, 9999),
        'type' => 'business',
        'credit_limit' => '0.00',
        'payment_terms_days' => 30,
        'is_active' => true,
        'is_walk_in' => false,
    ]);
}

function makeIssuedInvoiceAt(Customer $customer, Location $location, string $total): Invoice
{
    return Invoice::forceCreate([
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'number' => 'INV-ACC-'.str_pad((string) random_int(1, 99999), 6, '0', STR_PAD_LEFT),
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

/** Record a payment at $location by allocating it in full to an invoice at that location. */
function recordPaymentAt(User $user, Customer $customer, Location $location, string $amount): Payment
{
    // syncWithoutDetaching avoids a duplicate-key violation when the user was
    // already assigned to this location via payAccessUser().
    $user->locations()->syncWithoutDetaching([$location->id]);
    app(LocationAccess::class)->flush($user->id);
    $invoice = makeIssuedInvoiceAt($customer, $location, $amount);

    return app(RecordPayment::class)([
        'customer_id' => $customer->id,
        'method' => 'cash',
        'amount' => $amount,
        'received_at' => now()->toDateString(),
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => $amount]],
    ], (string) Str::uuid());
}

// ─── Payments list: location scoping ──────────────────────────────────────────

test('payment list shows only allowed-location payments for restricted user', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $user = payAccessUser(['payments.view', 'payments.create'], [$locA->id]);
    $this->actingAs($user);

    $customer = payAccessCustomer();
    $payA = recordPaymentAt($user, $customer, $locA, '100.00');

    // Create locB payment as an admin so the user's access is not checked
    $admin = payAccessAdmin(['payments.create', 'payments.view']);
    $this->actingAs($admin);
    $payB = recordPaymentAt($admin, $customer, $locB, '200.00');

    $this->actingAs($user);

    Livewire::actingAs($user)->test(PaymentsIndex::class)
        ->assertViewHas('payments', fn ($payments) => $payments->contains('id', $payA->id))
        ->assertViewHas('payments', fn ($payments) => ! $payments->contains('id', $payB->id));
});

test('payment list shows all locations for Admin', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $admin = payAccessAdmin(['payments.view', 'payments.create']);
    $this->actingAs($admin);

    $customer = payAccessCustomer();
    $payA = recordPaymentAt($admin, $customer, $locA, '100.00');
    $payB = recordPaymentAt($admin, $customer, $locB, '200.00');

    Livewire::actingAs($admin)->test(PaymentsIndex::class)
        ->assertViewHas('payments', fn ($payments) => $payments->contains('id', $payA->id))
        ->assertViewHas('payments', fn ($payments) => $payments->contains('id', $payB->id));
});

test('payments totals only count allowed-location payments', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $user = payAccessUser(['payments.view', 'payments.create'], [$locA->id]);
    $admin = payAccessAdmin(['payments.create']);

    $customer = payAccessCustomer();

    $this->actingAs($user);
    recordPaymentAt($user, $customer, $locA, '500.00');

    // locB payment created by admin
    $this->actingAs($admin);
    recordPaymentAt($admin, $customer, $locB, '900.00');

    $this->actingAs($user);
    Livewire::actingAs($user)->test(PaymentsIndex::class)
        ->assertViewHas('filteredTotal', '500.00')
        ->assertDontSee('900.00');
});

// ─── show() guard ─────────────────────────────────────────────────────────────

test('show() with a locB payment is refused for restricted user', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $user = payAccessUser(['payments.view', 'payments.create'], [$locA->id]);
    $admin = payAccessAdmin(['payments.create']);

    $customer = payAccessCustomer();
    $this->actingAs($admin);
    $payB = recordPaymentAt($admin, $customer, $locB, '100.00');

    $this->actingAs($user);

    // AuthorizationException thrown inside a Livewire action is returned as 403.
    Livewire::actingAs($user)->test(PaymentsIndex::class)
        ->call('show', $payB->id)
        ->assertForbidden();
});

// ─── RecordPayment: location guard ────────────────────────────────────────────

test('RecordPayment is blocked for restricted user targeting locB invoice', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $user = payAccessUser(['payments.create', 'payments.view'], [$locA->id]);
    $this->actingAs($user);

    $customer = payAccessCustomer();
    $invoiceB = makeIssuedInvoiceAt($customer, $locB, '100.00');

    expect(fn () => app(RecordPayment::class)([
        'customer_id' => $customer->id,
        'method' => 'cash',
        'amount' => '100.00',
        'received_at' => now()->toDateString(),
        'allocations' => [['invoice_id' => $invoiceB->id, 'amount' => '100.00']],
    ], (string) Str::uuid()))->toThrow(AuthorizationException::class);
});

test('mixed-location allocation is refused for restricted user', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $user = payAccessUser(['payments.create', 'payments.view'], [$locA->id, $locB->id]);
    $this->actingAs($user);

    $customer = payAccessCustomer();
    $invoiceA = makeIssuedInvoiceAt($customer, $locA, '100.00');
    $invoiceB = makeIssuedInvoiceAt($customer, $locB, '200.00');

    expect(fn () => app(RecordPayment::class)([
        'customer_id' => $customer->id,
        'method' => 'cash',
        'amount' => '300.00',
        'received_at' => now()->toDateString(),
        'allocations' => [
            ['invoice_id' => $invoiceA->id, 'amount' => '100.00'],
            ['invoice_id' => $invoiceB->id, 'amount' => '200.00'],
        ],
    ], (string) Str::uuid()))->toThrow(ValidationException::class);
});

test('Admin can create a mixed-location payment', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $admin = payAccessAdmin(['payments.create', 'payments.view']);
    $this->actingAs($admin);

    $customer = payAccessCustomer();
    $invoiceA = makeIssuedInvoiceAt($customer, $locA, '100.00');
    $invoiceB = makeIssuedInvoiceAt($customer, $locB, '200.00');

    $payment = app(RecordPayment::class)([
        'customer_id' => $customer->id,
        'method' => 'cash',
        'amount' => '300.00',
        'received_at' => now()->toDateString(),
        'allocations' => [
            ['invoice_id' => $invoiceA->id, 'amount' => '100.00'],
            ['invoice_id' => $invoiceB->id, 'amount' => '200.00'],
        ],
    ], (string) Str::uuid());

    // Multi-location: location_id stays null (spans multiple locations)
    expect($payment->location_id)->toBeNull()
        ->and($payment->allocations)->toHaveCount(2);
});

// ─── AllocateCredit: location guard ───────────────────────────────────────────

test('AllocateCredit is blocked for restricted user on a locB payment', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $user = payAccessUser(['payments.create', 'payments.view'], [$locA->id]);
    $admin = payAccessAdmin(['payments.create']);

    $customer = payAccessCustomer();
    $this->actingAs($admin);
    $payB = recordPaymentAt($admin, $customer, $locB, '500.00');

    // Create a second invoice at locA for the user to try to allocate to
    $invoiceA = makeIssuedInvoiceAt($customer, $locA, '100.00');

    $this->actingAs($user);
    // payment is at locB — user can't access locB → ensure() throws
    expect(fn () => app(AllocateCredit::class)($payB, [
        ['invoice_id' => $invoiceA->id, 'amount' => '100.00'],
    ]))->toThrow(AuthorizationException::class);
});

// ─── ReversePayment: location guard ───────────────────────────────────────────

test('ReversePayment is blocked for restricted user on a locB payment', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $user = payAccessUser(['payments.view', 'payments.create', 'payments.reverse'], [$locA->id]);
    $admin = payAccessAdmin(['payments.create']);

    $customer = payAccessCustomer();
    $this->actingAs($admin);
    $payB = recordPaymentAt($admin, $customer, $locB, '100.00');

    $this->actingAs($user);
    expect(fn () => app(ReversePayment::class)($payB, 'Blocked'))
        ->toThrow(AuthorizationException::class);
});

// ─── Receipt PDF: location guard ──────────────────────────────────────────────

test('payment receipt PDF returns 403 for restricted user with a locB payment', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $user = payAccessUser(['payments.view', 'payments.create'], [$locA->id]);
    $admin = payAccessAdmin(['payments.create']);

    $customer = payAccessCustomer();
    $this->actingAs($admin);
    $payB = recordPaymentAt($admin, $customer, $locB, '100.00');

    $this->actingAs($user)
        ->get(route('payments.receipt', $payB))
        ->assertForbidden();
});

// ─── Customer statement: location-filtered view, full balance ─────────────────

test('customer statement shows only locA invoices and payments for restricted user', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $user = payAccessUser(['payments.view', 'payments.create', 'customers.view'], [$locA->id]);
    $admin = payAccessAdmin(['payments.create']);

    $customer = payAccessCustomer();
    $customer->update(['credit_limit' => '0.00']);

    // locA invoice and payment
    $this->actingAs($user);
    $invoiceA = makeIssuedInvoiceAt($customer, $locA, '300.00');
    $payA = recordPaymentAt($user, $customer, $locA, '300.00');

    // locB invoice and payment (created by admin)
    $this->actingAs($admin);
    $invoiceB = makeIssuedInvoiceAt($customer, $locB, '500.00');
    $payB = recordPaymentAt($admin, $customer, $locB, '500.00');

    $this->actingAs($user);
    $statement = app(GetCustomerStatement::class)($customer, null, $user);

    $invoiceIds = collect($statement['invoices'])->pluck('id')->all();
    $paymentIds = collect($statement['payments'])->pluck('id')->all();

    expect($invoiceIds)->toContain($invoiceA->id)
        ->not->toContain($invoiceB->id);

    expect($paymentIds)->toContain($payA->id)
        ->not->toContain($payB->id);
});

test('credit-limit check uses the full balance across all locations', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $user = payAccessUser(['payments.view', 'payments.create', 'customers.view'], [$locA->id]);
    $admin = payAccessAdmin(['payments.create']);

    $customer = payAccessCustomer();
    $customer->update(['credit_limit' => '0.00']);

    // locB has an unpaid invoice of 400 — user cannot see locB
    $this->actingAs($admin);
    makeIssuedInvoiceAt($customer, $locB, '400.00');

    $this->actingAs($user);
    $statement = app(GetCustomerStatement::class)($customer, null, $user);

    // The balance shown uses the FULL outstanding including locB's 400
    expect($statement['balance'])->toBe('400.00');

    // But the invoices list is restricted to locA only (empty for this user)
    expect($statement['invoices'])->toHaveCount(0);
});

// ─── Backfill: migration sets location_id correctly ───────────────────────────

test('backfill sets payment location_id from the first allocation invoice', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $admin = payAccessAdmin(['payments.create']);
    $this->actingAs($admin);
    $customer = payAccessCustomer();

    // Create a payment with null location (simulating a pre-migration row)
    $payment = Payment::forceCreate([
        'number' => 'TEST-BKFILL-001',
        'customer_id' => $customer->id,
        'location_id' => null,
        'method' => 'cash',
        'amount' => '200.00',
        'received_at' => now(),
        'received_by' => $admin->id,
        'status' => 'active',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $invoiceA = makeIssuedInvoiceAt($customer, $locA, '100.00');
    $invoiceB = makeIssuedInvoiceAt($customer, $locB, '100.00');

    // Allocations: invoiceA first (lower id), invoiceB second
    PaymentAllocation::forceCreate(['payment_id' => $payment->id, 'invoice_id' => $invoiceA->id, 'amount' => '100.00']);
    PaymentAllocation::forceCreate(['payment_id' => $payment->id, 'invoice_id' => $invoiceB->id, 'amount' => '100.00']);

    // Run the same backfill SQL the migration uses
    DB::statement('
        UPDATE payments
        SET location_id = (
            SELECT i.location_id
            FROM payment_allocations pa
            JOIN invoices i ON i.id = pa.invoice_id
            WHERE pa.payment_id = payments.id
            ORDER BY pa.id ASC
            LIMIT 1
        )
        WHERE id = ?
    ', [$payment->id]);

    $payment->refresh();
    // Should be locA because that allocation has the lower id
    expect($payment->location_id)->toBe($locA->id);
});

test('backfill leaves null for payments with no allocations', function (): void {
    $admin = payAccessAdmin(['payments.create']);
    $this->actingAs($admin);
    $customer = payAccessCustomer();

    $payment = Payment::forceCreate([
        'number' => 'TEST-BKFILL-002',
        'customer_id' => $customer->id,
        'location_id' => null,
        'method' => 'cash',
        'amount' => '50.00',
        'received_at' => now(),
        'received_by' => $admin->id,
        'status' => 'active',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    DB::statement('
        UPDATE payments
        SET location_id = (
            SELECT i.location_id
            FROM payment_allocations pa
            JOIN invoices i ON i.id = pa.invoice_id
            WHERE pa.payment_id = payments.id
            ORDER BY pa.id ASC
            LIMIT 1
        )
        WHERE EXISTS (
            SELECT 1 FROM payment_allocations pa2 WHERE pa2.payment_id = payments.id
        )
        AND id = ?
    ', [$payment->id]);

    $payment->refresh();
    expect($payment->location_id)->toBeNull();
});
