<?php

use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Livewire\Actions\Users\CreateUser;
use App\Livewire\Actions\Users\UpdateUser;
use App\Models\Location;
use App\Models\PosSession;
use App\Models\User;
use App\Services\Access\LocationAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function laAdmin(): User
{
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('Admin');

    return $user;
}

function laCashier(): User
{
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('Cashier');

    return $user;
}

// ─── LocationAccess service ──────────────────────────────────────────────────

test('allowedLocationIds returns null for Admin', function (): void {
    $admin = laAdmin();
    $result = app(LocationAccess::class)->allowedLocationIds($admin);

    expect($result)->toBeNull();
});

test('allowedLocationIds returns assigned location IDs for non-Admin', function (): void {
    $user = laCashier();
    $loc1 = Location::factory()->create(['is_active' => true]);
    $loc2 = Location::factory()->create(['is_active' => true]);
    $user->locations()->sync([$loc1->id, $loc2->id]);

    $result = app(LocationAccess::class)->allowedLocationIds($user);

    expect($result)->toBeArray()
        ->toContain($loc1->id)
        ->toContain($loc2->id);
});

test('allowedLocationIds excludes inactive locations', function (): void {
    $user = laCashier();
    $active = Location::factory()->create(['is_active' => true]);
    $inactive = Location::factory()->create(['is_active' => false]);
    $user->locations()->sync([$active->id, $inactive->id]);

    $result = app(LocationAccess::class)->allowedLocationIds($user);

    expect($result)->toContain($active->id)
        ->not->toContain($inactive->id);
});

test('allowedLocationIds returns empty array for user with no locations', function (): void {
    $user = laCashier();

    $result = app(LocationAccess::class)->allowedLocationIds($user);

    expect($result)->toBeArray()->toBeEmpty();
});

test('canAccess returns true for Admin regardless of location', function (): void {
    $admin = laAdmin();
    $loc = Location::factory()->create();

    expect(app(LocationAccess::class)->canAccess($admin, $loc->id))->toBeTrue();
});

test('canAccess returns true for user with assigned location', function (): void {
    $user = laCashier();
    $loc = Location::factory()->create(['is_active' => true]);
    $user->locations()->sync([$loc->id]);

    expect(app(LocationAccess::class)->canAccess($user, $loc->id))->toBeTrue();
});

test('canAccess returns false for user without the location', function (): void {
    $user = laCashier();
    $loc = Location::factory()->create(['is_active' => true]);
    $other = Location::factory()->create(['is_active' => true]);
    $user->locations()->sync([$loc->id]);

    expect(app(LocationAccess::class)->canAccess($user, $other->id))->toBeFalse();
});

test('ensure throws AuthorizationException when user cannot access location', function (): void {
    $user = laCashier();
    $loc = Location::factory()->create(['is_active' => true]);

    expect(fn () => app(LocationAccess::class)->ensure($user, $loc->id))
        ->toThrow(AuthorizationException::class);
});

test('flush clears per-request cache so next call re-reads from DB', function (): void {
    $user = laCashier();
    $loc = Location::factory()->create(['is_active' => true]);

    $svc = app(LocationAccess::class);

    // Prime the cache (empty)
    $svc->allowedLocationIds($user);

    // Assign location directly — bypasses the service
    $user->locations()->sync([$loc->id]);

    // Cache still stale
    expect($svc->allowedLocationIds($user))->not->toContain($loc->id);

    // After flush, reads fresh data
    $svc->flush($user->id);
    expect($svc->allowedLocationIds($user))->toContain($loc->id);
});

test('currentLocationId auto-selects the single assigned location', function (): void {
    $user = laCashier();
    $loc = Location::factory()->create(['is_active' => true]);
    $user->locations()->sync([$loc->id]);

    $result = app(LocationAccess::class)->currentLocationId($user);

    expect($result)->toBe($loc->id);
});

test('setCurrentLocationId stores location in session for allowed user', function (): void {
    $user = laCashier();
    $loc = Location::factory()->create(['is_active' => true]);
    $user->locations()->sync([$loc->id]);

    $this->actingAs($user);

    app(LocationAccess::class)->setCurrentLocationId($user, $loc->id);

    expect(session('location.current_id'))->toBe($loc->id);
});

test('setCurrentLocationId throws for location not assigned to user', function (): void {
    $user = laCashier();
    $loc = Location::factory()->create(['is_active' => true]);

    $this->actingAs($user);

    expect(fn () => app(LocationAccess::class)->setCurrentLocationId($user, $loc->id))
        ->toThrow(AuthorizationException::class);
});

// ─── EnsureLocationAccess middleware ─────────────────────────────────────────

test('middleware: user with location is not redirected to location.none', function (): void {
    $user = laCashier();
    $loc = Location::factory()->create(['is_active' => true]);
    $user->locations()->sync([$loc->id]);

    $response = $this->actingAs($user)->get(route('products.index'));

    expect($response->getStatusCode())->not->toBe(302);
});

test('middleware redirects user with no locations to location.none', function (): void {
    $user = laCashier();

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertRedirect(route('location.none'));
});

test('middleware allows Admin with no location assignments through', function (): void {
    $admin = laAdmin();
    // Admin bypasses EnsureLocationAccess entirely regardless of location_user rows

    $response = $this->actingAs($admin)->get(route('products.index'));

    // Should NOT be redirected to location.none
    $this->assertNotEquals(route('location.none'), $response->headers->get('Location'));
});

test('middleware does not redirect on exempt auth routes', function (): void {
    $user = laCashier();

    // No locations assigned — but the no-location page itself must still be reachable
    $this->actingAs($user)
        ->get(route('location.none'))
        ->assertOk();
});

// ─── CreateUser assigns locations ─────────────────────────────────────────────

test('CreateUser assigns locations to new non-Admin user', function (): void {
    Mail::fake();

    $admin = laAdmin();
    $this->actingAs($admin);

    $loc1 = Location::factory()->create(['is_active' => true]);
    $loc2 = Location::factory()->create(['is_active' => true]);

    $result = app(CreateUser::class)(
        name: 'Bob',
        email: 'bob@example.com',
        roleNames: ['Cashier'],
        locationIds: [$loc1->id, $loc2->id],
    );

    $assigned = $result['user']->locations()->pluck('location_id')->map(fn ($id) => (int) $id)->all();
    expect($assigned)->toContain($loc1->id)->toContain($loc2->id);
});

test('CreateUser ignores locations when assigning Admin role', function (): void {
    Mail::fake();

    $admin = laAdmin();
    $this->actingAs($admin);

    $loc = Location::factory()->create(['is_active' => true]);

    $result = app(CreateUser::class)(
        name: 'Sue',
        email: 'sue@example.com',
        roleNames: ['Admin'],
        locationIds: [$loc->id],
    );

    expect($result['user']->locations()->count())->toBe(0);
});

// ─── UpdateUser assigns/removes locations ─────────────────────────────────────

test('UpdateUser syncs new locations and flushes the cache', function (): void {
    $admin = laAdmin();
    $this->actingAs($admin);

    $user = laCashier();
    $loc1 = Location::factory()->create(['is_active' => true]);
    $loc2 = Location::factory()->create(['is_active' => true]);
    $user->locations()->sync([$loc1->id]);

    app(UpdateUser::class)($user, $user->name, $user->email, ['Cashier'], [$loc2->id]);

    $assigned = $user->fresh()->locations()->pluck('location_id')->map(fn ($id) => (int) $id)->all();
    expect($assigned)->toBe([$loc2->id]);
});

test('UpdateUser blocks removing a location with an open POS session', function (): void {
    $admin = laAdmin();
    $this->actingAs($admin);

    $user = laCashier();
    $loc = Location::factory()->create(['is_active' => true]);
    $user->locations()->sync([$loc->id]);

    PosSession::create([
        'user_id' => $user->id,
        'location_id' => $loc->id,
        'opened_at' => now(),
        'opening_cash' => '0.00',
        'status' => 'open',
    ]);

    expect(fn () => app(UpdateUser::class)($user, $user->name, $user->email, ['Cashier'], []))
        ->toThrow(ValidationException::class);
});

test('non-Admin actor cannot assign locations they do not hold', function (): void {
    app(EnsureSystemRoles::class)();

    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole('Cashier');
    // Grant the manager permission to update users so Gate::authorize passes
    $manager->givePermissionTo('users.update');
    $ownedLoc = Location::factory()->create(['is_active' => true]);
    $manager->locations()->sync([$ownedLoc->id]);

    $target = User::factory()->create(['is_active' => true]);
    $target->assignRole('Cashier');
    $target->locations()->sync([$ownedLoc->id]);

    $foreignLoc = Location::factory()->create(['is_active' => true]);

    $this->actingAs($manager);

    expect(fn () => app(UpdateUser::class)($target, $target->name, $target->email, ['Cashier'], [$foreignLoc->id]))
        ->toThrow(ValidationException::class);
});
