<?php

use App\Enums\InvoiceStatus;
use App\Livewire\Actions\Invoicing\VoidInvoice;
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
use App\Services\Customers\CreditLimitService;
use App\Services\Customers\CustomerBalance;
use App\Services\Settings\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function paymentUser(array $permissions): User
{
    $user = User::factory()->create(['name' => 'Payment Test User']);

    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }

    $user->givePermissionTo($permissions);

    return $user;
}

function makeCustomer(): Customer
{
    return Customer::forceCreate([
        'code' => 'PAY-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'name' => 'Test Customer '.random_int(1, 9999),
        'type' => 'business',
        'phone' => '+255 700 000 001',
        'credit_limit' => '0.00',
        'payment_terms_days' => 30,
        'is_active' => true,
        'is_walk_in' => false,
    ]);
}

/**
 * Create a issued invoice row ready for payment allocation.
 */
function makePaymentInvoice(Customer $customer, string $total, string $amountPaid = '0.00'): Invoice
{
    $status = match (true) {
        bccomp($amountPaid, '0.00', 2) === 0 => InvoiceStatus::Issued,
        bccomp($amountPaid, $total, 2) >= 0 => InvoiceStatus::Paid,
        default => InvoiceStatus::PartiallyPaid,
    };

    $location = Location::forceCreate(['name' => 'WH-PAY-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT), 'type' => 'warehouse', 'is_active' => true]);

    return Invoice::forceCreate([
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'number' => 'INV-TEST-'.str_pad((string) random_int(1, 99999), 6, '0', STR_PAD_LEFT),
        'status' => $status,
        'channel' => 'invoice',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => $total,
        'discount_total' => '0.00',
        'tax_total' => '0.00',
        'total' => $total,
        'amount_paid' => $amountPaid,
        'prices_include_tax' => false,
        'created_by' => User::factory()->create()->id,
        'issued_by' => User::factory()->create()->id,
        'issued_at' => now(),
        'customer_name' => $customer->name,
    ]);
}

function paymentData(Customer $customer, string $amount, array $allocations = [], ?string $key = null): array
{
    return [
        'customer_id' => $customer->id,
        'method' => 'cash',
        'amount' => $amount,
        'received_at' => now()->toDateString(),
        'allocations' => $allocations,
        'idempotency_key' => $key ?? (string) Str::uuid(),
    ];
}

// ─── RecordPayment ─────────────────────────────────────────────────────────────

test('full payment marks invoice paid', function (): void {
    $user = paymentUser(['payments.create', 'payments.view']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $invoice = makePaymentInvoice($customer, '500.00');

    $key = (string) Str::uuid();
    $payment = app(RecordPayment::class)(
        paymentData($customer, '500.00', [
            ['invoice_id' => $invoice->id, 'amount' => '500.00'],
        ]),
        $key,
    );

    expect($payment->status)->toBe('active')
        ->and($payment->amount)->toBe('500.00')
        ->and($payment->allocations)->toHaveCount(1);

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->amount_paid)->toBe('500.00');
});

test('partial payment marks invoice partially_paid', function (): void {
    $user = paymentUser(['payments.create', 'payments.view']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $invoice = makePaymentInvoice($customer, '500.00');

    app(RecordPayment::class)(
        paymentData($customer, '200.00', [
            ['invoice_id' => $invoice->id, 'amount' => '200.00'],
        ]),
        (string) Str::uuid(),
    );

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::PartiallyPaid)
        ->and($invoice->amount_paid)->toBe('200.00');
});

test('one payment across multiple invoices allocates correctly', function (): void {
    $user = paymentUser(['payments.create', 'payments.view']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $inv1 = makePaymentInvoice($customer, '300.00');
    $inv2 = makePaymentInvoice($customer, '200.00');

    $payment = app(RecordPayment::class)(
        paymentData($customer, '500.00', [
            ['invoice_id' => $inv1->id, 'amount' => '300.00'],
            ['invoice_id' => $inv2->id, 'amount' => '200.00'],
        ]),
        (string) Str::uuid(),
    );

    expect($payment->allocations)->toHaveCount(2);

    $inv1->refresh();
    $inv2->refresh();
    expect($inv1->status)->toBe(InvoiceStatus::Paid)
        ->and($inv2->status)->toBe(InvoiceStatus::Paid);
});

test('overpayment leaves unallocated credit', function (): void {
    $user = paymentUser(['payments.create', 'payments.view']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $invoice = makePaymentInvoice($customer, '300.00');

    $payment = app(RecordPayment::class)(
        paymentData($customer, '500.00', [
            ['invoice_id' => $invoice->id, 'amount' => '300.00'],
        ]),
        (string) Str::uuid(),
    );

    expect($payment->unallocatedCredit())->toBe('200.00');
});

test('AllocateCredit applies unallocated credit to open invoice', function (): void {
    $user = paymentUser(['payments.create', 'payments.view']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $inv1 = makePaymentInvoice($customer, '300.00');
    $inv2 = makePaymentInvoice($customer, '200.00');

    // Pay 500 against only the first invoice — 200 remains unallocated
    $payment = app(RecordPayment::class)(
        paymentData($customer, '500.00', [
            ['invoice_id' => $inv1->id, 'amount' => '300.00'],
        ]),
        (string) Str::uuid(),
    );

    expect($payment->unallocatedCredit())->toBe('200.00');

    // Now allocate the credit to the second invoice
    app(AllocateCredit::class)($payment, [
        ['invoice_id' => $inv2->id, 'amount' => '200.00'],
    ]);

    $inv2->refresh();
    expect($inv2->status)->toBe(InvoiceStatus::Paid);

    $payment->refresh();
    expect($payment->unallocatedCredit())->toBe('0.00');
});

test('allocation exceeding invoice balance is rejected', function (): void {
    $user = paymentUser(['payments.create', 'payments.view']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $invoice = makePaymentInvoice($customer, '100.00');

    expect(fn () => app(RecordPayment::class)(
        paymentData($customer, '200.00', [
            ['invoice_id' => $invoice->id, 'amount' => '150.00'],
        ]),
        (string) Str::uuid(),
    ))->toThrow(ValidationException::class);
});

test('allocation to draft invoice is rejected', function (): void {
    $user = paymentUser(['payments.create', 'payments.view']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $location = Location::forceCreate(['name' => 'WH-DRAFT-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT), 'type' => 'warehouse', 'is_active' => true]);
    $draft = Invoice::forceCreate([
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'number' => null,
        'status' => InvoiceStatus::Draft,
        'channel' => 'invoice',
        'subtotal' => '100.00',
        'discount_total' => '0.00',
        'tax_total' => '0.00',
        'total' => '100.00',
        'amount_paid' => '0.00',
        'prices_include_tax' => false,
        'created_by' => $user->id,
    ]);

    expect(fn () => app(RecordPayment::class)(
        paymentData($customer, '100.00', [
            ['invoice_id' => $draft->id, 'amount' => '100.00'],
        ]),
        (string) Str::uuid(),
    ))->toThrow(ValidationException::class);
});

test('allocation to another customers invoice is rejected', function (): void {
    $user = paymentUser(['payments.create', 'payments.view']);
    $this->actingAs($user);

    $customerA = makeCustomer();
    $customerB = makeCustomer();
    $invoice = makePaymentInvoice($customerB, '100.00');

    expect(fn () => app(RecordPayment::class)(
        paymentData($customerA, '100.00', [
            ['invoice_id' => $invoice->id, 'amount' => '100.00'],
        ]),
        (string) Str::uuid(),
    ))->toThrow(ValidationException::class);
});

test('idempotent replay returns existing payment without re-posting', function (): void {
    $user = paymentUser(['payments.create', 'payments.view']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $invoice = makePaymentInvoice($customer, '200.00');
    $key = (string) Str::uuid();

    $first = app(RecordPayment::class)(
        paymentData($customer, '200.00', [['invoice_id' => $invoice->id, 'amount' => '200.00']]),
        $key,
    );

    $second = app(RecordPayment::class)(
        paymentData($customer, '200.00', [['invoice_id' => $invoice->id, 'amount' => '200.00']]),
        $key,
    );

    expect($first->id)->toBe($second->id);

    // Should not double-allocate
    expect(Payment::count())->toBe(1);
    expect(PaymentAllocation::count())->toBe(1);

    $invoice->refresh();
    expect($invoice->amount_paid)->toBe('200.00');
});

// ─── ReversePayment ────────────────────────────────────────────────────────────

test('reversal restores invoice status and amount_paid', function (): void {
    $user = paymentUser(['payments.create', 'payments.view', 'payments.reverse']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $invoice = makePaymentInvoice($customer, '500.00');

    $payment = app(RecordPayment::class)(
        paymentData($customer, '500.00', [['invoice_id' => $invoice->id, 'amount' => '500.00']]),
        (string) Str::uuid(),
    );

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Paid);

    app(ReversePayment::class)($payment, 'Customer requested refund');

    $payment->refresh();
    expect($payment->status)->toBe('reversed')
        ->and($payment->reverse_reason)->toBe('Customer requested refund')
        ->and($payment->reversed_by)->toBe($user->id);

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->amount_paid)->toBe('0.00');
});

test('reversal of partial payment restores partially_paid invoice to issued', function (): void {
    $user = paymentUser(['payments.create', 'payments.view', 'payments.reverse']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $invoice = makePaymentInvoice($customer, '500.00');

    $payment = app(RecordPayment::class)(
        paymentData($customer, '200.00', [['invoice_id' => $invoice->id, 'amount' => '200.00']]),
        (string) Str::uuid(),
    );

    app(ReversePayment::class)($payment, 'Error in amount');

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->amount_paid)->toBe('0.00');
});

test('reversing an already reversed payment throws', function (): void {
    $user = paymentUser(['payments.create', 'payments.view', 'payments.reverse']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $payment = app(RecordPayment::class)(
        paymentData($customer, '100.00'),
        (string) Str::uuid(),
    );

    app(ReversePayment::class)($payment, 'First reversal');

    expect(fn () => app(ReversePayment::class)($payment->fresh(), 'Second reversal'))
        ->toThrow(ValidationException::class);
});

test('reversal without a reason is rejected', function (): void {
    $user = paymentUser(['payments.create', 'payments.view', 'payments.reverse']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $payment = app(RecordPayment::class)(
        paymentData($customer, '100.00'),
        (string) Str::uuid(),
    );

    expect(fn () => app(ReversePayment::class)($payment, ''))
        ->toThrow(ValidationException::class);
});

// ─── VoidInvoice blocked ───────────────────────────────────────────────────────

test('VoidInvoice is blocked when active payment allocations exist', function (): void {
    $user = paymentUser(['payments.create', 'payments.view', 'invoices.void', 'invoices.view']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $invoice = makePaymentInvoice($customer, '300.00');

    app(RecordPayment::class)(
        paymentData($customer, '300.00', [['invoice_id' => $invoice->id, 'amount' => '300.00']]),
        (string) Str::uuid(),
    );

    $invoice->refresh();

    expect(fn () => app(VoidInvoice::class)($invoice, 'Should be blocked'))
        ->toThrow(ValidationException::class, 'Reverse the payments first');
});

test('VoidInvoice succeeds after payment is reversed', function (): void {
    $user = paymentUser(['payments.create', 'payments.view', 'payments.reverse', 'invoices.void', 'invoices.view']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $invoice = makePaymentInvoice($customer, '300.00');

    $payment = app(RecordPayment::class)(
        paymentData($customer, '300.00', [['invoice_id' => $invoice->id, 'amount' => '300.00']]),
        (string) Str::uuid(),
    );

    app(ReversePayment::class)($payment, 'Reversing to allow void');
    $invoice->refresh();

    app(VoidInvoice::class)($invoice, 'Voiding after reversal');
    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Void);
});

// ─── CustomerBalance ───────────────────────────────────────────────────────────

test('customer outstanding balance reflects unallocated credit', function (): void {
    $user = paymentUser(['payments.create']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $invoice = makePaymentInvoice($customer, '500.00');

    // Pay 700 — 500 allocated + 200 unallocated credit
    app(RecordPayment::class)(
        paymentData($customer, '700.00', [['invoice_id' => $invoice->id, 'amount' => '500.00']]),
        (string) Str::uuid(),
    );

    $outstanding = app(CustomerBalance::class)->outstanding($customer);

    // Open invoices: none (invoice is now Paid). Unallocated credit: 200.
    // outstanding = 0 - 200 = -200 (pre-paid)
    expect($outstanding)->toBe('-200.00');
});

test('credit limit check accounts for unallocated credit reducing net outstanding', function (): void {
    $this->actingAs(paymentUser(['payments.create']));

    $customer = makeCustomer();
    $customer->update(['credit_limit' => '300.00']);

    $invoice = makePaymentInvoice($customer, '300.00');

    // Customer owes 300.00 but we also have an unallocated payment credit of 100.
    // Net outstanding = 300 - 100 = 200 (within 300 limit → allowed)
    app(RecordPayment::class)(
        paymentData($customer, '100.00'),  // no allocations — goes to credit
        (string) Str::uuid(),
    );

    $limitService = app(CreditLimitService::class);
    // The customer has 200 net outstanding vs 300 limit — headroom = 100
    expect($limitService->evaluate($customer, '100.00')['outcome'])->toBe('within_limit');
    // But 150 more would take it to 350, exceeding the 300 limit
    expect($limitService->evaluate($customer, '150.00')['outcome'])->not->toBe('within_limit');
});

// ─── Authorization ─────────────────────────────────────────────────────────────

test('cashier cannot reverse a payment', function (): void {
    $cashier = paymentUser(['payments.view', 'payments.create']); // no payments.reverse
    $this->actingAs($cashier);

    $customer = makeCustomer();
    $payment = app(RecordPayment::class)(
        paymentData($customer, '100.00'),
        (string) Str::uuid(),
    );

    // Need a user with reverse for the actual check inside ReversePayment
    expect(fn () => app(ReversePayment::class)($payment, 'Unauthorized'))
        ->toThrow(AuthorizationException::class);
});

test('viewer cannot create a payment', function (): void {
    $viewer = paymentUser(['payments.view']); // no payments.create
    $this->actingAs($viewer);

    $customer = makeCustomer();

    expect(fn () => app(RecordPayment::class)(
        paymentData($customer, '100.00'),
        (string) Str::uuid(),
    ))->toThrow(AuthorizationException::class);
});

test('unauthenticated request to payments index is redirected', function (): void {
    $this->get(route('payments.index'))->assertRedirect(route('login'));
});

// ─── Livewire screen ───────────────────────────────────────────────────────────

test('payments index screen loads and shows existing payments', function (): void {
    $user = paymentUser(['payments.view', 'payments.create']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $payment = app(RecordPayment::class)(
        paymentData($customer, '250.00'),
        (string) Str::uuid(),
    );

    Livewire::actingAs($user)
        ->test(PaymentsIndex::class)
        ->assertSee($payment->number)
        ->assertSee($customer->name);
});

test('payments index filters by customer name', function (): void {
    $user = paymentUser(['payments.view', 'payments.create']);
    $this->actingAs($user);

    $customerA = makeCustomer();
    $customerB = makeCustomer();

    app(RecordPayment::class)(paymentData($customerA, '100.00'), (string) Str::uuid());
    app(RecordPayment::class)(paymentData($customerB, '200.00'), (string) Str::uuid());

    Livewire::actingAs($user)
        ->test(PaymentsIndex::class)
        ->set('customerFilter', $customerA->name)
        ->assertSee($customerA->name)
        ->assertDontSee($customerB->name);
});

test('payments index totals bar shows active payment total', function (): void {
    $user = paymentUser(['payments.view', 'payments.create']);
    $this->actingAs($user);
    $customer = makeCustomer();

    app(RecordPayment::class)(paymentData($customer, '1000.00'), (string) Str::uuid());

    Livewire::actingAs($user)
        ->test(PaymentsIndex::class)
        ->assertSee('1,000.00');
});

// ─── Receipt PDF ───────────────────────────────────────────────────────────────

test('payment receipt PDF is returned for authorised user', function (): void {
    $user = paymentUser(['payments.view', 'payments.create']);
    $this->actingAs($user);
    $customer = makeCustomer();

    app(Settings::class)->put('business.name', 'Test Store Ltd');

    $payment = app(RecordPayment::class)(
        paymentData($customer, '450.00'),
        (string) Str::uuid(),
    );

    $response = $this->get(route('payments.receipt', ['payment' => $payment->id]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('payment receipt is denied for user without payments.view', function (): void {
    $user = paymentUser([]); // no permissions
    $other = paymentUser(['payments.create', 'payments.view']);
    $this->actingAs($other);

    $customer = makeCustomer();
    $payment = app(RecordPayment::class)(
        paymentData($customer, '100.00'),
        (string) Str::uuid(),
    );

    $this->actingAs($user)
        ->get(route('payments.receipt', ['payment' => $payment->id]))
        ->assertForbidden();
});

// ─── Validation edge cases ─────────────────────────────────────────────────────

test('zero amount payment is rejected', function (): void {
    $user = paymentUser(['payments.create']);
    $this->actingAs($user);

    $customer = makeCustomer();

    expect(fn () => app(RecordPayment::class)(
        array_merge(paymentData($customer, '0.00'), ['amount' => '0.00']),
        (string) Str::uuid(),
    ))->toThrow(ValidationException::class);
});

test('missing received_at is rejected', function (): void {
    $user = paymentUser(['payments.create']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $data = paymentData($customer, '100.00');
    $data['received_at'] = '';

    expect(fn () => app(RecordPayment::class)($data, (string) Str::uuid()))
        ->toThrow(ValidationException::class);
});

test('invalid method is rejected', function (): void {
    $user = paymentUser(['payments.create']);
    $this->actingAs($user);

    $customer = makeCustomer();
    $data = paymentData($customer, '100.00');
    $data['method'] = 'cryptocurrency';

    expect(fn () => app(RecordPayment::class)($data, (string) Str::uuid()))
        ->toThrow(ValidationException::class);
});
