<?php

use App\Livewire\Actions\Customers\EnsureWalkInCustomer;
use App\Models\Customer;
use App\Models\Location;
use App\Models\User;
use App\Services\Access\LocationAccess;
use App\Services\Setup\SetupStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

beforeEach(function (): void {
    app(SetupStatus::class)->forget();

    if ($testNow = env('TEST_NOW')) {
        Carbon::setTestNow(Carbon::parse($testNow));
    }
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function ensureWalkInCustomerForTests(): Customer
{
    return app(EnsureWalkInCustomer::class)();
}

/** Create an active location and optionally assign it to users. */
function makeTestLocation(?string $name = null): Location
{
    return Location::factory()->create([
        'name' => $name ?? ('Test Location '.uniqid()),
        'type' => 'store',
        'is_active' => true,
    ]);
}

/** Attach locations to a user (without removing existing ones) and flush the LocationAccess cache. */
function grantLocation(User $user, Location ...$locations): User
{
    $user->locations()->syncWithoutDetaching(collect($locations)->pluck('id')->all());
    app(LocationAccess::class)->flush($user->id);

    return $user;
}
