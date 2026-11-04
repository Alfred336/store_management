<?php

use App\Exceptions\InsufficientStockException;
use App\Livewire\Actions\Customers\EnsureWalkInCustomer;
use App\Livewire\Actions\Sales\ClosePosSession;
use App\Livewire\Actions\Sales\CompletePosSale;
use App\Livewire\Actions\Sales\HoldSale;
use App\Livewire\Actions\Sales\OpenPosSession;
use App\Livewire\Actions\Sales\ResumeSale;
use App\Livewire\Sales\Pos\Index as PosIndex;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Payment;
use App\Models\PosHeldSale;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function posUser(array $extra = []): User
{
    $base = [
        'pos.use', 'pos.open_session', 'pos.close_session',
        'invoices.issue', 'invoices.create', 'payments.create',
    ];

    $user = User::factory()->create(['name' => 'POS Cashier']);

    foreach (array_merge($base, $extra) as $perm) {
        Permission::findOrCreate($perm, 'web');
    }

    $user->givePermissionTo(array_merge($base, $extra));

    return $user;
}

function posAdminUser(): User
{
    return posUser(['pos.close_any_session', 'pos.credit_sale']);
}

function posLocation(): Location
{
    return Location::forceCreate([
        'name' => 'POS-Test-'.random_int(1, 9999),
        'type' => 'store',
        'is_active' => true,
    ]);
}

function posProduct(Location $location, string $sellingPrice = '100.00', bool $withStock = true): Product
{
    $unit = Unit::forceCreate([
        'name' => 'Piece-'.random_int(1, 9999),
        'abbreviation' => 'pc'.random_int(1, 999),
    ]);

    $product = Product::forceCreate([
        'sku' => 'POS-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'name' => 'POS Product '.random_int(1, 9999),
        'base_unit_id' => $unit->id,
        'selling_price' => $sellingPrice,
        'tax_type' => 'exempt',
        'reorder_level' => '0.000',
        'track_batches' => false,
        'is_active' => true,
    ]);

    if ($withStock) {
        StockLevel::forceCreate([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'batch_id' => null,
            'quantity' => '100.000',
        ]);
    }

    return $product;
}

function posSession(User $user, Location $location): PosSession
{
    return PosSession::forceCreate([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'opened_at' => now(),
        'opening_cash' => '500.00',
        'status' => 'open',
    ]);
}

function walkInCustomer(): Customer
{
    return app(EnsureWalkInCustomer::class)();
}

function namedCustomer(string $creditLimit = '0.00'): Customer
{
    return Customer::forceCreate([
        'code' => 'POSTEST-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'name' => 'POS Named Customer '.random_int(1, 9999),
        'type' => 'business',
        'credit_limit' => $creditLimit,
        'payment_terms_days' => 30,
        'is_active' => true,
        'is_walk_in' => false,
    ]);
}

// ─── 1. Open session ──────────────────────────────────────────────────────────

test('cashier_can_open_a_pos_session', function (): void {
    $user = posUser();
    $location = posLocation();

    $this->actingAs($user);

    $session = app(OpenPosSession::class)([
        'location_id' => $location->id,
        'opening_cash' => '250.00',
    ]);

    expect($session->status)->toBe('open')
        ->and($session->user_id)->toBe($user->id)
        ->and($session->location_id)->toBe($location->id)
        ->and((string) $session->opening_cash)->toBe('250.00');
});

// ─── 2. Duplicate session blocked ─────────────────────────────────────────────

test('opening_a_second_session_is_blocked', function (): void {
    $user = posUser();
    $location = posLocation();

    $this->actingAs($user);

    posSession($user, $location); // first open session

    expect(fn () => app(OpenPosSession::class)([
        'location_id' => $location->id,
        'opening_cash' => '0.00',
    ]))->toThrow(ValidationException::class);
});

// ─── 3. Close session computes expected cash ──────────────────────────────────

test('closing_a_session_computes_expected_cash_and_difference', function (): void {
    $user = posUser();
    $location = posLocation();
    $this->actingAs($user);

    $session = posSession($user, $location);

    // Simulate a cash payment in this session
    Payment::forceCreate([
        'number' => 'PAY-TEST-'.random_int(1, 9999),
        'customer_id' => walkInCustomer()->id,
        'method' => 'cash',
        'amount' => '200.00',
        'received_at' => now(),
        'received_by' => $user->id,
        'status' => 'active',
        'idempotency_key' => Str::uuid()->toString(),
        'pos_session_id' => $session->id,
    ]);

    // expected_cash = opening_cash + cash_payments = 500 + 200 = 700
    // closing_cash_counted = 650 → difference = -50
    $closed = app(ClosePosSession::class)($session, [
        'closing_cash_counted' => '650.00',
    ]);

    expect($closed->status)->toBe('closed')
        ->and((string) $closed->expected_cash)->toBe('700.00')
        ->and((string) $closed->difference)->toBe('-50.00');
});

// ─── 4. Manager closes another user's session ─────────────────────────────────

test('manager_can_force_close_another_user_session', function (): void {
    $cashier = posUser();
    $manager = posAdminUser();
    $location = posLocation();

    $session = posSession($cashier, $location);

    $this->actingAs($manager);

    $closed = app(ClosePosSession::class)($session, [
        'closing_cash_counted' => '500.00',
    ]);

    expect($closed->status)->toBe('closed');
});

// ─── 5. POS sale issues RCT-numbered invoice ──────────────────────────────────

test('completing_a_pos_sale_issues_a_receipt_numbered_invoice', function (): void {
    $user = posUser();
    $location = posLocation();
    $this->actingAs($user);

    $session = posSession($user, $location);
    $product = posProduct($location);

    $result = app(CompletePosSale::class)(
        items: [['product_id' => $product->id, 'quantity' => '1.000', 'unit_price' => '100.00', 'discount_amount' => '0.00']],
        customerId: walkInCustomer()->id,
        tenders: [['method' => 'cash', 'amount' => '100.00']],
        sessionId: $session->id,
        idempotencyKey: Str::uuid()->toString(),
    );

    expect($result->invoice->number)->toStartWith('RCT-')
        ->and($result->invoice->channel)->toBe('pos');
});

// ─── 6. Selling price used as default unit price ──────────────────────────────

test('pos_sale_uses_selling_price_as_default_unit_price', function (): void {
    $user = posUser();
    $location = posLocation();
    $this->actingAs($user);

    $session = posSession($user, $location);
    $product = posProduct($location, '250.00');

    $result = app(CompletePosSale::class)(
        items: [['product_id' => $product->id, 'quantity' => '2.000', 'unit_price' => (string) $product->selling_price, 'discount_amount' => '0.00']],
        customerId: walkInCustomer()->id,
        tenders: [['method' => 'cash', 'amount' => '500.00']],
        sessionId: $session->id,
        idempotencyKey: Str::uuid()->toString(),
    );

    expect((string) $result->invoice->total)->toBe('500.00');
});

// ─── 7. Amount tendered and change stored on payment ──────────────────────────

test('pos_payment_records_amount_tendered_and_change_given', function (): void {
    $user = posUser();
    $location = posLocation();
    $this->actingAs($user);

    $session = posSession($user, $location);
    $product = posProduct($location, '80.00');

    app(CompletePosSale::class)(
        items: [['product_id' => $product->id, 'quantity' => '1.000', 'unit_price' => '80.00', 'discount_amount' => '0.00']],
        customerId: walkInCustomer()->id,
        tenders: [['method' => 'cash', 'amount' => '100.00']], // overpaid by 20
        sessionId: $session->id,
        idempotencyKey: Str::uuid()->toString(),
    );

    $payment = Payment::where('pos_session_id', $session->id)->first();

    expect($payment)->not->toBeNull()
        ->and((string) $payment->amount_tendered)->toBe('100.00')
        ->and((string) $payment->change_given)->toBe('20.00');
});

// ─── 8. Multiple tenders aggregated correctly ─────────────────────────────────

test('multiple_tenders_are_aggregated_correctly', function (): void {
    $user = posUser();
    $location = posLocation();
    $this->actingAs($user);

    $session = posSession($user, $location);
    $product = posProduct($location, '150.00');

    $result = app(CompletePosSale::class)(
        items: [['product_id' => $product->id, 'quantity' => '1.000', 'unit_price' => '150.00', 'discount_amount' => '0.00']],
        customerId: walkInCustomer()->id,
        tenders: [
            ['method' => 'cash', 'amount' => '100.00'],
            ['method' => 'mobile_money', 'amount' => '50.00'],
        ],
        sessionId: $session->id,
        idempotencyKey: Str::uuid()->toString(),
    );

    $payments = Payment::where('pos_session_id', $session->id)->get();

    expect($payments)->toHaveCount(2)
        ->and((string) $result->invoice->total)->toBe('150.00');
});

// ─── 9. Credit sale requires pos.credit_sale permission ───────────────────────

test('credit_sale_needs_pos_credit_sale_permission', function (): void {
    $user = posUser(); // no pos.credit_sale
    $location = posLocation();
    $this->actingAs($user);

    $session = posSession($user, $location);
    $product = posProduct($location);
    $customer = namedCustomer();

    expect(fn () => app(CompletePosSale::class)(
        items: [['product_id' => $product->id, 'quantity' => '1.000', 'unit_price' => '100.00', 'discount_amount' => '0.00']],
        customerId: $customer->id,
        tenders: [], // credit sale
        sessionId: $session->id,
        idempotencyKey: Str::uuid()->toString(),
    ))->toThrow(HttpException::class);
});

// ─── 10. Credit sale blocked when limit exceeded ──────────────────────────────

test('credit_sale_is_blocked_when_customer_limit_exceeded', function (): void {
    $user = posAdminUser();
    $location = posLocation();
    $this->actingAs($user);

    $session = posSession($user, $location);
    $product = posProduct($location, '1000.00');
    $customer = namedCustomer('50.00'); // credit limit = 50, invoice total = 1000 → blocked

    expect(fn () => app(CompletePosSale::class)(
        items: [['product_id' => $product->id, 'quantity' => '1.000', 'unit_price' => '1000.00', 'discount_amount' => '0.00']],
        customerId: $customer->id,
        tenders: [], // credit sale
        sessionId: $session->id,
        idempotencyKey: Str::uuid()->toString(),
    ))->toThrow(ValidationException::class);
});

// ─── 11. Insufficient stock throws exception ──────────────────────────────────

test('insufficient_stock_throws_exception', function (): void {
    $user = posUser();
    $location = posLocation();
    $this->actingAs($user);

    $session = posSession($user, $location);
    $product = posProduct($location, '100.00', withStock: false); // no stock

    expect(fn () => app(CompletePosSale::class)(
        items: [['product_id' => $product->id, 'quantity' => '1.000', 'unit_price' => '100.00', 'discount_amount' => '0.00']],
        customerId: walkInCustomer()->id,
        tenders: [['method' => 'cash', 'amount' => '100.00']],
        sessionId: $session->id,
        idempotencyKey: Str::uuid()->toString(),
    ))->toThrow(InsufficientStockException::class);
});

// ─── 12. Walk-in cannot take a credit sale ────────────────────────────────────

test('walk_in_customer_cannot_take_a_credit_sale', function (): void {
    $user = posAdminUser();
    $location = posLocation();
    $this->actingAs($user);

    $session = posSession($user, $location);
    $product = posProduct($location);

    expect(fn () => app(CompletePosSale::class)(
        items: [['product_id' => $product->id, 'quantity' => '1.000', 'unit_price' => '100.00', 'discount_amount' => '0.00']],
        customerId: walkInCustomer()->id,
        tenders: [], // credit sale with walk-in → blocked
        sessionId: $session->id,
        idempotencyKey: Str::uuid()->toString(),
    ))->toThrow(ValidationException::class, 'Walk-in customer cannot take a credit sale.');
});

// ─── 13. Hold sale does not affect stock or numbering ────────────────────────

test('hold_sale_does_not_affect_stock_or_numbering', function (): void {
    $user = posUser();
    $location = posLocation();
    $this->actingAs($user);

    $session = posSession($user, $location);
    $product = posProduct($location);

    $stockBefore = (string) StockLevel::where('product_id', $product->id)->value('quantity');
    $heldSalesBefore = PosHeldSale::count();

    $held = app(HoldSale::class)([
        'location_id' => $location->id,
        'pos_session_id' => $session->id,
        'cart' => [
            ['product_id' => $product->id, 'name' => $product->name, 'quantity' => '1.000', 'unit_price' => '100.00'],
        ],
    ]);

    $stockAfter = (string) StockLevel::where('product_id', $product->id)->value('quantity');

    expect($stockBefore)->toBe($stockAfter) // stock unchanged
        ->and(PosHeldSale::count())->toBe($heldSalesBefore + 1)
        ->and($held->id)->toBeInt();
});

// ─── 14. Resume sale restores cart and deletes held sale ──────────────────────

test('resume_sale_restores_cart_and_deletes_held_sale', function (): void {
    $user = posUser();
    $location = posLocation();
    $this->actingAs($user);

    $session = posSession($user, $location);
    $product = posProduct($location);

    $cart = [['product_id' => $product->id, 'name' => $product->name, 'quantity' => '3.000', 'unit_price' => '100.00']];

    $held = PosHeldSale::forceCreate([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'pos_session_id' => $session->id,
        'cart' => $cart,
        'label' => 'Test hold',
    ]);

    $resumed = app(ResumeSale::class)($held->id);

    expect($resumed->cart)->toBe($cart)
        ->and(PosHeldSale::find($held->id))->toBeNull();
});

// ─── 15. Void held sale deletes the record ────────────────────────────────────

test('void_held_sale_deletes_the_record', function (): void {
    $user = posUser();
    $location = posLocation();
    $this->actingAs($user);

    $session = posSession($user, $location);

    $held = PosHeldSale::forceCreate([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'pos_session_id' => $session->id,
        'cart' => [],
        'label' => '',
    ]);

    Livewire::actingAs($user)
        ->test(PosIndex::class)
        ->call('voidHeldSale', $held->id);

    expect(PosHeldSale::find($held->id))->toBeNull();
});

// ─── 16. POS route requires pos.use permission ────────────────────────────────

test('pos_route_requires_pos_use_permission', function (): void {
    $user = User::factory()->create();
    // no permissions

    $this->actingAs($user);

    $response = $this->get(route('pos.index'));

    $response->assertStatus(403);
});

// ─── 17. Closing session requires at least one permission ────────────────────

test('close_session_requires_at_least_one_permission', function (): void {
    $cashier = posUser(); // has pos.close_session
    $other = posUser(); // different user, only has pos.close_session for OWN sessions
    $location = posLocation();

    $session = posSession($cashier, $location);

    $this->actingAs($other); // other user, no pos.close_any_session

    expect(fn () => app(ClosePosSession::class)($session, [
        'closing_cash_counted' => '500.00',
    ]))->toThrow(HttpException::class);
});
