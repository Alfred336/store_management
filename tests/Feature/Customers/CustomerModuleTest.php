<?php

use App\Exports\CustomersExport;
use App\Livewire\Actions\Customers\CreateCustomer;
use App\Livewire\Actions\Customers\DeleteCustomer;
use App\Livewire\Actions\Customers\ImportCustomers;
use App\Livewire\Actions\Customers\UpdateCustomer;
use App\Livewire\Customers\Index as CustomersIndex;
use App\Models\Customer;
use App\Models\User;
use App\Services\Customers\CreditLimitService;
use App\Services\Settings\Settings;
use App\Services\Setup\SetupStatus;
use Database\Seeders\WalkInCustomerSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        ->set('creditLimit', '250000.00')
        ->set('paymentTermsDays', '30')
        ->set('isActive', true)
        ->call('store')
        ->assertHasNoErrors();

    $customer = Customer::query()->where('name', 'Mlimani Traders')->firstOrFail();
    expect($customer->code)->toMatch('/^CUS-\d{4}-\d{6}$/')
        ->and($customer->email)->toBe('sales@mlimani.example')
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

test('customer list and statement work when invoice and payment tables do not exist yet', function () {
    $user = customerPermissionsUser(['customers.view']);
    $customer = Customer::query()->create([
        'code' => 'TEST-0001',
        'name' => 'Statement Customer',
        'type' => 'individual',
    ]);

    expect(DB::getSchemaBuilder()->hasTable('invoices'))->toBeFalse()
        ->and(DB::getSchemaBuilder()->hasTable('payments'))->toBeFalse();

    Livewire::actingAs($user)->test(CustomersIndex::class)
        ->call('show', $customer->id)
        ->assertSee('Statement Customer')
        ->assertSee('Outstanding balance')
        ->assertSee('0.00')
        ->assertSee('No invoices yet.')
        ->assertSee('No payments yet.');
});

test('customer statement reads outstanding invoice balances and recent payment history when those tables exist', function () {
    Schema::create('invoices', function ($table): void {
        $table->id();
        $table->foreignId('customer_id')->constrained();
        $table->string('number')->unique();
        $table->date('issue_date');
        $table->string('status');
        $table->decimal('total', 14, 2);
        $table->decimal('amount_paid', 14, 2)->default(0);
        $table->timestamps();
    });
    Schema::create('payments', function ($table): void {
        $table->id();
        $table->foreignId('customer_id')->constrained();
        $table->decimal('amount', 14, 2);
        $table->string('reference')->nullable();
        $table->string('method')->nullable();
        $table->timestamp('paid_at')->nullable();
        $table->timestamps();
    });

    $customer = Customer::query()->create([
        'code' => 'TEST-STATEMENT-1',
        'name' => 'Statement With History',
        'type' => 'business',
    ]);
    DB::table('invoices')->insert([
        'customer_id' => $customer->id,
        'number' => 'INV-2026-000001',
        'issue_date' => '2026-11-01',
        'status' => 'partially_paid',
        'total' => '125.50',
        'amount_paid' => '25.50',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('payments')->insert([
        'customer_id' => $customer->id,
        'amount' => '25.50',
        'reference' => 'PMT-001',
        'method' => 'cash',
        'paid_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = customerPermissionsUser(['customers.view']);
    Livewire::actingAs($user)->test(CustomersIndex::class)
        ->call('show', $customer->id)
        ->assertSee('100.00')
        ->assertSee('INV-2026-000001')
        ->assertSee('PMT-001')
        ->assertSee('25.50');
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
    $csv = "name,type,phone,email,address,tin,notes,credit_limit,payment_terms_days,is_active\n".
        "Lake Zone Stores,business,+255700000009,accounts@lakezone.example,Mwanza,TIN-009,Imported customer,75000.00,14,1\n";

    Livewire::actingAs($user)->test(CustomersIndex::class)
        ->set('importFile', UploadedFile::fake()->createWithContent('customers.csv', $csv))
        ->call('importCustomers')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('customers', [
        'name' => 'Lake Zone Stores',
        'email' => 'accounts@lakezone.example',
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

test('customers are forbidden without view permission and create permission is enforced', function () {
    $customer = Customer::query()->where('is_walk_in', false)->first();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(CustomersIndex::class)->assertForbidden();

    $viewer = customerPermissionsUser(['customers.view']);
    Livewire::actingAs($viewer)->test(CustomersIndex::class)->call('openForm')->assertForbidden();

    expect($customer)->toBeNull();
});
