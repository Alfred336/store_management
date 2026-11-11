<?php

use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Livewire\Auth\ChangePassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── EnforceMustChangePassword middleware — HTTP layer ────────────────────────

test('user with must_change_password is redirected from dashboard to change-password page', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertRedirect(route('password.change'));
});

test('user with must_change_password is redirected from POS to change-password page', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user);

    $this->get(route('pos.index'))->assertRedirect(route('password.change'));
});

test('user with must_change_password can access the change-password page', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user);

    $this->get(route('password.change'))->assertOk();
});

test('user with must_change_password can log out', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user);

    $this->post(route('logout'))->assertRedirect();
    $this->assertGuest();
});

test('livewire wire call from non-exempt page is redirected to change-password page for must_change_password user', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user);

    // A Livewire wire call with no special headers must be redirected.
    $this->post(route('default-livewire.update'))->assertRedirect(route('password.change'));
});

test('livewire wire call is blocked even when a spoofed Referer header points to the change-password page', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user);

    // The middleware must NOT use the client-controlled Referer header to decide
    // whether to allow a request through.  A spoofed Referer must be ignored.
    $this->withHeaders(['Referer' => route('password.change')])
        ->post(route('default-livewire.update'))
        ->assertRedirect(route('password.change'));
});

// ─── ChangePassword component ─────────────────────────────────────────────────

test('changing password clears must_change_password and redirects to dashboard', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user);

    Livewire::test(ChangePassword::class)
        ->set('current_password', 'password')
        ->set('password', 'NewSecure!123')
        ->set('password_confirmation', 'NewSecure!123')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->must_change_password)->toBeFalse();
});

test('changing password for unverified user sets email_verified_at and redirects to dashboard', function (): void {
    $user = User::factory()->unverified()->create(['must_change_password' => true]);
    $this->actingAs($user);

    Livewire::test(ChangePassword::class)
        ->set('current_password', 'password')
        ->set('password', 'NewSecure!123')
        ->set('password_confirmation', 'NewSecure!123')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $fresh = $user->fresh();
    expect($fresh->must_change_password)->toBeFalse();
    expect($fresh->email_verified_at)->not->toBeNull();
});

test('new password must differ from current password', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user);

    Livewire::test(ChangePassword::class)
        ->set('current_password', 'password')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('updatePassword')
        ->assertHasErrors(['password']);

    expect($user->fresh()->must_change_password)->toBeTrue();
});

test('wrong current password is rejected when changing password', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user);

    Livewire::test(ChangePassword::class)
        ->set('current_password', 'wrong-password')
        ->set('password', 'NewSecure!123')
        ->set('password_confirmation', 'NewSecure!123')
        ->call('updatePassword')
        ->assertHasErrors(['current_password']);
});

// ─── Validation failures preserve typed values ────────────────────────────────

test('current_password field value is preserved after a wrong-password error', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user);

    Livewire::test(ChangePassword::class)
        ->set('current_password', 'wrong-password')
        ->set('password', 'NewSecure!123')
        ->set('password_confirmation', 'NewSecure!123')
        ->call('updatePassword')
        ->assertHasErrors(['current_password'])
        ->assertSet('current_password', 'wrong-password')
        ->assertSet('password', 'NewSecure!123')
        ->assertSet('password_confirmation', 'NewSecure!123');
});

test('all field values are preserved after a weak-password validation error', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user);

    Livewire::test(ChangePassword::class)
        ->set('current_password', 'password')
        ->set('password', 'weak')
        ->set('password_confirmation', 'weak')
        ->call('updatePassword')
        ->assertHasErrors(['password'])
        ->assertSet('current_password', 'password')
        ->assertSet('password', 'weak')
        ->assertSet('password_confirmation', 'weak');
});

// ─── Full onboarding flow ─────────────────────────────────────────────────────

test('full onboarding flow: change password then dashboard is accessible', function (): void {
    app(EnsureSystemRoles::class)();
    $user = User::factory()->unverified()->create(['must_change_password' => true]);
    $user->assignRole('Cashier');
    $this->actingAs($user);

    // Step 1: change the temporary password.
    Livewire::test(ChangePassword::class)
        ->set('current_password', 'password')
        ->set('password', 'NewSecure!123')
        ->set('password_confirmation', 'NewSecure!123')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->must_change_password)->toBeFalse();
    expect($user->fresh()->email_verified_at)->not->toBeNull();

    // Step 2: session is still authenticated.
    $this->assertAuthenticatedAs($user);

    // Step 3: the dashboard is now accessible.
    $this->get(route('dashboard'))->assertOk();
});

test('password change keeps the user authenticated on the current session', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user);

    Livewire::test(ChangePassword::class)
        ->set('current_password', 'password')
        ->set('password', 'NewSecure!123')
        ->set('password_confirmation', 'NewSecure!123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

// ─── No redirect loop ─────────────────────────────────────────────────────────

test('password.change route is accessible without triggering a redirect loop', function (): void {
    $user = User::factory()->unverified()->create(['must_change_password' => true]);
    $this->actingAs($user);

    $this->get(route('password.change'))->assertOk();
});

test('dashboard is accessible after changing password for unverified user', function (): void {
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create([
        'must_change_password' => false,
        'email_verified_at' => now(),
    ]);
    $user->assignRole('Cashier');
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertOk();
});

// ─── Hash is updated ─────────────────────────────────────────────────────────

test('new password hash is saved after a successful change', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user);

    Livewire::test(ChangePassword::class)
        ->set('current_password', 'password')
        ->set('password', 'NewSecure!123')
        ->set('password_confirmation', 'NewSecure!123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('NewSecure!123', $user->fresh()->password))->toBeTrue();
    expect(Hash::check('password', $user->fresh()->password))->toBeFalse();
});
