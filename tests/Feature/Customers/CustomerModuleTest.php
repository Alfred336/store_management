<?php

use App\Enums\InvoiceStatus;
use App\Exports\CustomersExport;
use App\Livewire\Actions\Customers\CreateCustomer;
use App\Livewire\Actions\Customers\DeleteCustomer;
use App\Livewire\Actions\Customers\GetCustomerStatement;
use App\Livewire\Actions\Customers\ImportCustomers;
use App\Livewire\Actions\Customers\UpdateCustomer;
use App\Livewire\Actions\Payments\ReversePayment;
use App\Livewire\Customers\Index as CustomersIndex;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\Customers\CreditLimitService;
use App\Services\Customers\CustomerBalance;
use App\Services\Settings\Settings;
use App\Services\Setup\SetupStatus;
use Database\Seeders\WalkInCustomerSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;

function customerPermissionsUser(array $permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user->givePermissionTo($permissions);

    return $user;
}

beforeEach(function (): void {
    app(SetupStatus::class)->forget();
    ensureWalkInCustomerForTests();
});

test('customers can be created searched and updated with a unique transaction-safe code', function () {
    $user = customerPermissionsUser(['customers.view', 'customers.create', 'customers.update', 'customers.delete']);

    Livewire::actingAs($user)->test(CustomersIndex::class)
        ->assertSee('Walk-in Customer')
        ->call('openForm')
        ->set('name', '  Mlimani Traders  ')
        ->set('type', 'business')
        ->set('phone', '+255 700 000 111')
        ->set('email', 'SALES@MLIMANI.EXAMPLE')
        ->set('address', 'Dar es Salaam')
        ->set('tin', 'TIN-001')
        ->set('vrn', 'VRN-001')
        ->set('creditLimit', '250000.00')
        ->set('paymentTermsDays', '30')
        ->set('isActive', true)
        ->call('store')
        ->assertHasNoErrors();

    $customer = Customer::query()->where('name', 'Mlimani Traders')->firstOrFail();
    expect($customer->code)->toMatch('/^CUS-\d{4}-\d{6}$/')
        ->and($customer->email)->toBe('sales@mlimani.example')
        ->and($customer->vrn)->toBe('VRN-001')
        ->and($customer->credit_limit)->toBe('250000.00')
        ->and($customer->payment_terms_days)->toBe(30);

    Livewire::actingAs($user)->test(CustomersIndex::class)
        ->set('search', 'CUS-')
        ->assertSee($customer->code)
        ->call('openForm', $customer->id)
        ->set('name', 'Mlimani Traders Updated')
        ->set('isActive', false)
        ->call('update')
        ->assertHasNoErrors();

    expect($customer->fresh()->name)->toBe('Mlimani Traders Updated')
        ->and($customer->fresh()->is_active)->toBeFalse();
});

test('customer validation rejects invalid types and negative credit limits', function () {
    $user = customerPermissionsUser(['customers.view', 'customers.create']);

    Livewire::actingAs($user)->test(CustomersIndex::class)
        ->call('openForm')
        ->set('name', 'Invalid Customer')
        ->set('type', 'supplier')
        ->set('creditLimit', '-0.01')
        ->call('store')
        ->assertHasErrors(['type', 'creditLimit']);
});

test('customer list and statement work for a customer with no invoices', function () {
    $user = customerPermissionsUser(['customers.view']);
    $customer = Customer::query()->create([
        'code' => 'TEST-0001',
        'name' => 'Statement Customer',
        'type' => 'individual',
    ]);

    expect(Invoice::query()->where('customer_id', $customer->id)->exists())->toBeFalse();

    Livewire::actingAs($user)->test(CustomersIndex::class)
        ->call('show', $customer->id)
        ->assertSee('Statement Customer')
        ->assertSee('Outstanding balance')
        ->assertSee('0.00')
        ->assertSee('No invoices yet.')
        ->assertSee('No payments yet.');
});

test('customer statement reads outstanding invoice balances and recent payment history', function () {
    $payer = customerPermissionsUser(['customers.view', 'payments.create']);
    $this->actingAs($payer);

    $customer = Customer::query()->create([
        'code' => 'TEST-STATEMENT-1',
        'name' => 'Statement With History',
        'type' => 'business',
    ]);
    $location = Location::forceCreate(['name' => 'Statement Test Store', 'type' => 'store', 'is_active' => true]);

    // Create an invoice directly (bypassing full issue flow for statement test)
    $invoice = Invoice::query()->create([
        'number' => 'INV-2026-000001',
        'channel' => 'invoice',
        'status' => InvoiceStatus::PartiallyPaid,
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'issue_date' => '2026-11-01',
        'subtotal' => '125.50',
        'total' => '125.50',
        'amount_paid' => '25.50',
    ]);

    // Create payment and allocation directly — no stock flow needed for statement test
    $payment = Payment::forceCreate([
        'number' => 'PAY-2026-000001',
        'customer_id' => $customer->id,
        'method' => 'cash',
        'amount' => '25.50',
        'reference' => 'PMT-001',
        'received_at' => now(),
        'received_by' => $payer->id,
        'status' => 'active',
        'idempotency_key' => 'TEST-STMT-IDEMP-001',
    ]);

    // Allocation makes the payment non-credit so outstanding stays 100.00
    PaymentAllocation::forceCreate([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'amount' => '25.50',
    ]);

    $user = customerPermissionsUser(['customers.view']);
    Livewire::actingAs($user)->test(CustomersIndex::class)
        ->call('show', $customer->id)
        ->assertSee('100.00')
        ->assertSee('INV-2026-000001')
        ->assertSee('PMT-001')
        ->assertSee('25.50');
});

test('statement data and PDF use received_at not created_at for the payment date', function () {
    $user = customerPermissionsUser(['customers.view', 'payments.create']);
    $this->actingAs($user);

    $customer = Customer::query()->create([
        'code' => 'TEST-RCVDAT-1',
        'name' => 'Received At Test Customer',
        'type' => 'business',
    ]);

    // Payment received on a past date — created_at will be today (now())
    $pastDate = '2026-03-15';
    $payment = Payment::forceCreate([
        'number' => 'PAY-RCVDAT-001',
        'customer_id' => $customer->id,
        'method' => 'bank_transfer',
        'amount' => '500.00',
        'received_at' => $pastDate,
        'received_by' => $user->id,
        'status' => 'active',
        'idempotency_key' => 'TEST-RCVDAT-IDEMP-001',
    ]);

    // Statement data has received_at = '2026-03-15', not today
    $statement = app(GetCustomerStatement::class)($customer, null);
    $paymentRow = $statement['payments']->first();

    expect($paymentRow['received_at'])->toBe($pastDate)
        ->and($paymentRow['received_at'])->not->toBe(now()->toDateString());

    // The PDF template HTML contains '2026-03-15' in the payments table
    app(Settings::class)->put('business.name', 'Test Store');
    $html = view('pdf.customers.statement', compact('customer', 'statement'))->render();
    expect($html)->toContain($pastDate)
        ->and($html)->not->toContain('paid_at')
        ->and($html)->not->toContain('payment_date');
});

test('walk-in customer seeding is idempotent and all update/deletion paths are blocked', function () {
    $this->seed(WalkInCustomerSeeder::class);
    $this->seed(WalkInCustomerSeeder::class);

    $walkIn = Customer::query()->where('is_walk_in', true)->firstOrFail();
    expect(Customer::query()->where('is_walk_in', true)->count())->toBe(1);

    $user = customerPermissionsUser(['customers.view', 'customers.create', 'customers.update', 'customers.delete']);
    Livewire::actingAs($user)->test(CustomersIndex::class)->call('openForm', $walkIn->id)->assertForbidden();
    Livewire::actingAs($user)->test(CustomersIndex::class)->call('confirmDelete', $walkIn->id)->assertForbidden();
    Livewire::actingAs($user)->test(CustomersIndex::class)
        ->call('show', $walkIn->id)
        ->set('name', 'Changed')
        ->set('isActive', false)
        ->call('update')
        ->assertForbidden();
    Livewire::actingAs($user)->test(CustomersIndex::class)
        ->call('show', $walkIn->id)
        ->call('delete')
        ->assertForbidden();
    Livewire::actingAs($user)->test(CustomersIndex::class)
        ->call('openForm')
        ->set('name', 'Walk-in Customer')
        ->call('store')
        ->assertHasErrors('name');

    $this->actingAs($user);
    expect(fn () => app(DeleteCustomer::class)($walkIn))->toThrow(AuthorizationException::class);
    expect(fn () => app(UpdateCustomer::class)($walkIn, ['name' => 'Changed', 'is_active' => false]))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(CreateCustomer::class)(['name' => 'Walk-in Customer', 'type' => 'individual', 'is_walk_in' => true]))
        ->toThrow(ValidationException::class);

    expect(fn () => Livewire::actingAs($user)->test(CustomersIndex::class)->set('customerId', $walkIn->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
    $this->assertDatabaseHas('customers', ['id' => $walkIn->id, 'name' => 'Walk-in Customer', 'is_active' => true]);
});

test('first user creation invalidates the cached setup status', function () {
    $setupStatus = app(SetupStatus::class);
    $setupStatus->forget();
    expect($setupStatus->isRequired())->toBeTrue();

    User::factory()->create();

    expect($setupStatus->isRequired())->toBeFalse();

    $userQueries = 0;
    DB::listen(function ($query) use (&$userQueries): void {
        if (str_contains(strtolower($query->sql), 'users')) {
            $userQueries++;
        }
    });

    expect($setupStatus->isRequired())->toBeFalse()
        ->and($userQueries)->toBe(0);
});

test('deleting every user invalidates setup completion and allows first-run setup again', function () {
    $user = User::factory()->create();
    $status = app(SetupStatus::class);
    expect($status->isRequired())->toBeFalse();

    $user->delete();

    expect($status->isRequired())->toBeTrue();
});

test('customers can be imported from CSV and exported to xlsx and CSV', function () {
    $user = customerPermissionsUser(['customers.view', 'customers.create']);
    $csv = "name,type,phone,email,address,tin,vrn,notes,credit_limit,payment_terms_days,is_active\n".
        "Lake Zone Stores,business,+255700000009,accounts@lakezone.example,Mwanza,TIN-009,VRN-009,Imported customer,75000.00,14,1\n";

    Livewire::actingAs($user)->test(CustomersIndex::class)
        ->set('importFile', UploadedFile::fake()->createWithContent('customers.csv', $csv))
        ->call('importCustomers')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('customers', [
        'name' => 'Lake Zone Stores',
        'email' => 'accounts@lakezone.example',
        'vrn' => 'VRN-009',
        'credit_limit' => '75000.00',
        'payment_terms_days' => 14,
    ]);

    $exportUser = customerPermissionsUser(['customers.view']);
    $this->actingAs($exportUser);
    $this->get(route('customers.export', ['format' => 'csv']))->assertOk();
    $this->get(route('customers.export', ['format' => 'xlsx']))->assertOk();
    $this->get(route('customers.export', ['format' => 'unsupported']))->assertNotFound();
});

test('customer import enforces allowed file formats and maximum size', function () {
    $user = customerPermissionsUser(['customers.view', 'customers.create']);
    $component = Livewire::actingAs($user)->test(CustomersIndex::class);

    $component
        ->set('importFile', UploadedFile::fake()->createWithContent('customers.exe', 'not a supported import'))
        ->call('importCustomers')
        ->assertHasErrors('importFile');

    Livewire::actingAs($user)->test(CustomersIndex::class)
        ->set('importFile', UploadedFile::fake()->create('oversized.csv', 10241, 'text/csv'))
        ->call('importCustomers')
        ->assertHasErrors('importFile');

    $this->actingAs($user);
    expect(fn () => app(ImportCustomers::class)(UploadedFile::fake()->create('invalid.exe', 1, 'application/x-msdownload')))
        ->toThrow(ValidationException::class);
    expect(fn () => app(ImportCustomers::class)(UploadedFile::fake()->create('large.csv', 10241, 'text/csv')))
        ->toThrow(ValidationException::class);
});

test('ordinary creation and import cannot introduce another walk-in record', function () {
    $user = customerPermissionsUser(['customers.view', 'customers.create']);
    $this->actingAs($user);

    expect(fn () => app(CreateCustomer::class)([
        'name' => 'Walk-in Customer',
        'type' => 'individual',
        'is_walk_in' => true,
    ]))->toThrow(ValidationException::class);

    $csv = "name,type\nWalk-in Customer,individual\n";
    expect(fn () => app(ImportCustomers::class)(UploadedFile::fake()->createWithContent('walk-in.csv', $csv)))
        ->toThrow(ValidationException::class);

    expect(Customer::query()->where('is_walk_in', true)->count())->toBe(1);
});

test('customer exports neutralize formula-leading cells in CSV and XLSX data', function () {
    $customer = Customer::query()->create([
        'code' => 'TEST-FORMULA-0001',
        'name' => '+SUM(1,1)',
        'type' => 'business',
        'phone' => '-cmd',
        'email' => '@SUM(A1:A2)',
        'address' => '=HYPERLINK("https://example.invalid")',
        'tin' => '+123',
        'notes' => '-2+3',
    ]);
    $customer->forceFill(['code' => '=1+1'])->save();

    $export = new CustomersExport;
    $mapped = $export->map(Customer::query()->whereKey($customer->id)->firstOrFail());
    expect($mapped)->toContain("'=1+1", "'+SUM(1,1)", "'-cmd", "'@SUM(A1:A2)", "'=HYPERLINK(\"https://example.invalid\")", "'+123", "'-2+3");

    $csv = Excel::raw($export, ExcelWriter::CSV);
    expect($csv)->toContain("'=1+1", "'+SUM(1,1)", "'-cmd", "'@SUM(A1:A2)");

    $xlsx = Excel::raw($export, ExcelWriter::XLSX);
    $temporaryPath = tempnam(sys_get_temp_dir(), 'customer-export-');

    try {
        file_put_contents($temporaryPath, $xlsx);
        $sheet = IOFactory::load($temporaryPath)->getActiveSheet();
        expect($sheet->getCell('A3')->getValue())->toBe("'=1+1")
            ->and($sheet->getCell('B3')->getValue())->toBe("'+SUM(1,1)")
            ->and($sheet->getCell('D3')->getValue())->toBe("'-cmd")
            ->and($sheet->getCell('E3')->getValue())->toBe("'@SUM(A1:A2)");
    } finally {
        @unlink($temporaryPath);
    }
});

test('customer imports and exports require the matching permissions', function () {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);
    expect(fn () => app(ImportCustomers::class)(UploadedFile::fake()->createWithContent('customers.csv', "name,type\nA,business\n")))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($viewer)->get(route('customers.export', ['format' => 'csv']))->assertForbidden();
});

test('flushing the configured cache cannot make setup reachable while a user exists', function () {
    $user = User::factory()->create();
    app(Settings::class)->put('setup_completed_at', now()->toIso8601String());
    $status = app(SetupStatus::class);
    expect($status->isRequired())->toBeFalse();

    Cache::flush();

    expect($status->isRequired())->toBeFalse();
    $user->delete();
    cache()->flush();

    expect($status->isRequired())->toBeFalse();
    $this->get(route('setup.index'))->assertNotFound();
});

test('deleting every user invalidates the completed setup cache', function () {
    $users = User::factory()->count(2)->create();
    $status = app(SetupStatus::class);
    expect($status->isRequired())->toBeFalse();

    $users->firstOrFail()->delete();
    expect($status->isRequired())->toBeFalse();

    $users->last()->delete();

    expect($status->isRequired())->toBeTrue();
    $this->get(route('setup.index'))->assertOk();
});

test('customer statement can be downloaded as a PDF', function () {
    $customer = Customer::query()->create([
        'code' => 'TEST-PDF-0001',
        'name' => 'PDF Statement Customer',
        'type' => 'individual',
    ]);
    $user = customerPermissionsUser(['customers.view']);

    $response = $this->actingAs($user)->get(route('customers.statement', $customer));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('credit limit service returns configured warning or block outcomes', function () {
    $customer = Customer::query()->create([
        'code' => 'TEST-CREDIT-0001',
        'name' => 'Credit Customer',
        'type' => 'business',
        'credit_limit' => '100.00',
    ]);
    $user = customerPermissionsUser(['customers.view']);
    $this->actingAs($user);

    expect(app(CreditLimitService::class)->evaluate($customer, '80.00')['outcome'])->toBe('within_limit');

    app(Settings::class)->put('customers.credit_limit_mode', 'warn');
    expect(app(CreditLimitService::class)->evaluate($customer, '125.00')['outcome'])->toBe('warn');

    app(Settings::class)->put('customers.credit_limit_mode', 'block');
    expect(app(CreditLimitService::class)->evaluate($customer, '125.00')['outcome'])->toBe('block');
});

test('overpayment credit reduces net outstanding balance', function () {
    $user = customerPermissionsUser(['customers.view', 'payments.create']);
    $this->actingAs($user);

    $customer = Customer::query()->create([
        'code' => 'TEST-NETBAL-1',
        'name' => 'Net Balance Customer A',
        'type' => 'business',
    ]);
    $location = Location::forceCreate(['name' => 'WH-NB-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT), 'type' => 'warehouse', 'is_active' => true]);

    // Open invoice: total 200, nothing paid yet
    $invoice = Invoice::forceCreate([
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'number' => 'INV-NB-001',
        'status' => InvoiceStatus::Issued,
        'channel' => 'invoice',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => '200.00',
        'discount_total' => '0.00',
        'tax_total' => '0.00',
        'total' => '200.00',
        'amount_paid' => '0.00',
        'prices_include_tax' => false,
        'created_by' => $user->id,
        'issued_by' => $user->id,
        'issued_at' => now(),
        'customer_name' => $customer->name,
    ]);

    // Pay 300 against the 200 invoice → 100 unallocated credit remains
    $payment = Payment::forceCreate([
        'number' => 'PAY-NB-001',
        'customer_id' => $customer->id,
        'method' => 'cash',
        'amount' => '300.00',
        'received_at' => now(),
        'received_by' => $user->id,
        'status' => 'active',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    PaymentAllocation::forceCreate([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'amount' => '200.00',
    ]);

    $invoice->update(['amount_paid' => '200.00', 'status' => InvoiceStatus::Paid]);

    // No open invoices; 100 unallocated credit → net outstanding = -100
    expect(app(CustomerBalance::class)->outstanding($customer))->toBe('-100.00');
});

test('reversed payment no longer reduces customer net outstanding balance', function () {
    $user = customerPermissionsUser(['customers.view', 'payments.create', 'payments.reverse']);
    $this->actingAs($user);

    $customer = Customer::query()->create([
        'code' => 'TEST-NETBAL-2',
        'name' => 'Net Balance Customer B',
        'type' => 'business',
    ]);
    $location = Location::forceCreate(['name' => 'WH-NB2-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT), 'type' => 'warehouse', 'is_active' => true]);

    // Open invoice: total 500, nothing paid
    Invoice::forceCreate([
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'number' => 'INV-NB-002',
        'status' => InvoiceStatus::Issued,
        'channel' => 'invoice',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => '500.00',
        'discount_total' => '0.00',
        'tax_total' => '0.00',
        'total' => '500.00',
        'amount_paid' => '0.00',
        'prices_include_tax' => false,
        'created_by' => $user->id,
        'issued_by' => $user->id,
        'issued_at' => now(),
        'customer_name' => $customer->name,
    ]);

    // Unallocated payment of 200 → reduces net balance from 500 to 300
    $payment = Payment::forceCreate([
        'number' => 'PAY-NB-002',
        'customer_id' => $customer->id,
        'method' => 'cash',
        'amount' => '200.00',
        'received_at' => now(),
        'received_by' => $user->id,
        'status' => 'active',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    expect(app(CustomerBalance::class)->outstanding($customer))->toBe('300.00');

    // After reversal the 200 credit vanishes → balance is back to 500
    app(ReversePayment::class)($payment, 'Error in payment');

    expect(app(CustomerBalance::class)->outstanding($customer))->toBe('500.00');
});

test('customers are forbidden without view permission and create permission is enforced', function () {
    $customer = Customer::query()->where('is_walk_in', false)->first();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(CustomersIndex::class)->assertForbidden();

    $viewer = customerPermissionsUser(['customers.view']);
    Livewire::actingAs($viewer)->test(CustomersIndex::class)->call('openForm')->assertForbidden();

    expect($customer)->toBeNull();
});
