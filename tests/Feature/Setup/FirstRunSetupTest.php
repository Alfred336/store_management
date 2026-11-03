<?php

use App\Livewire\Actions\Setup\CompleteInitialSetup;
use App\Livewire\Setup\Index as SetupIndex;
use App\Models\User;
use App\Services\Settings\Settings;
use App\Services\Setup\SetupStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    app(SetupStatus::class)->forget();
});

function completeSetupForm(): array
{
    return [
        'businessName' => 'Kilimanjaro Supplies',
        'businessEmail' => 'hello@kilimanjaro.example',
        'phone' => '+255 700 000 000',
        'address' => 'Arusha, Tanzania',
        'businessTin' => 'TIN-123',
        'businessVrn' => 'VRN-456',
        'adminName' => 'Business Owner',
        'adminEmail' => 'owner@example.test',
        'password' => 'Kilimanjaro12345',
        'passwordConfirmation' => 'Kilimanjaro12345',
    ];
}

test('first-run setup creates one administrator and stores company details in settings', function () {
    expect(app(SetupStatus::class)->isRequired())->toBeTrue();

    Livewire::test(SetupIndex::class)
        ->fill(completeSetupForm())
        ->call('complete')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'owner@example.test')->firstOrFail();
    expect($user->hasRole('Admin'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(app(Settings::class)->get('business.name'))->toBe('Kilimanjaro Supplies')
        ->and(app(Settings::class)->get('business.currency'))->toBe('TZS');
    expect(app(Settings::class)->get('setup_completed_at'))->not->toBeNull();

    $this->assertDatabaseHas('customers', ['name' => 'Walk-in Customer', 'is_walk_in' => true]);

    $this->assertAuthenticatedAs($user);
    expect(User::query()->count())->toBe(1);
});

test('initial setup validates required fields and password confirmation', function () {
    Livewire::test(SetupIndex::class)
        ->set('businessName', '')
        ->set('adminName', 'Owner')
        ->set('adminEmail', 'owner@example.test')
        ->set('password', 'short')
        ->set('passwordConfirmation', 'different')
        ->call('complete')
        ->assertHasErrors(['businessName', 'password']);

    expect(User::query()->count())->toBe(0)
        ->and(app(Settings::class)->get('business.name'))->toBeNull();
});

test('initial setup is blocked once any user exists', function () {
    User::factory()->create();

    $this->get(route('setup.index'))->assertNotFound();
});

test('initial setup can only be completed once', function () {
    Livewire::test(SetupIndex::class)
        ->fill(completeSetupForm())
        ->call('complete')
        ->assertHasNoErrors();

    $this->get(route('setup.index'))->assertNotFound();
});

test('completed setup remains blocked after the final user is deleted and cache is flushed', function () {
    Livewire::test(SetupIndex::class)
        ->fill(completeSetupForm())
        ->call('complete')
        ->assertHasNoErrors();

    User::query()->delete();
    cache()->flush();

    expect(app(SetupStatus::class)->isRequired())->toBeFalse();
    $this->get(route('setup.index'))->assertNotFound();
});

test('setup Action refuses a second administrator when the persistent completion marker exists', function () {
    app(Settings::class)->put('setup_completed_at', now()->toIso8601String());

    expect(app(SetupStatus::class)->isRequired())->toBeFalse();
    expect(fn () => app(CompleteInitialSetup::class)([
        'business_name' => 'Second Setup Attempt',
        'business_email' => null,
        'phone' => null,
        'address' => null,
        'tin' => null,
        'vrn' => null,
        'admin_name' => 'Another Owner',
        'admin_email' => 'another@example.test',
        'password' => 'AnotherPassword123',
    ]))->toThrow(ValidationException::class);

    expect(User::query()->count())->toBe(0);
});

test('migration backfills setup completion for existing users idempotently', function () {
    User::factory()->create();
    $migration = require base_path('database/migrations/2026_11_01_000004_backfill_setup_completed_at_setting.php');

    $migration->up();
    $firstValue = DB::table('settings')->where('key', 'setup_completed_at')->value('value');
    $migration->up();

    expect($firstValue)->not->toBeNull()
        ->and(DB::table('settings')->where('key', 'setup_completed_at')->count())->toBe(1)
        ->and(DB::table('settings')->where('key', 'setup_completed_at')->value('value'))->toBe($firstValue);
});

test('setup reopen requires force and clears only the completion marker', function () {
    app(Settings::class)->put('setup_completed_at', now()->toIso8601String());

    $this->artisan('setup:reopen')->assertFailed();
    expect(app(Settings::class)->get('setup_completed_at'))->not->toBeNull();

    $this->artisan('setup:reopen', ['--force' => true])->assertSuccessful();
    expect(app(Settings::class)->get('setup_completed_at'))->toBeNull();
});
