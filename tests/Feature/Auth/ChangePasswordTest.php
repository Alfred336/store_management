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

test('livewire update endpoint without required headers returns 404', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user);

    // The web-group pass now exempts *livewire.update so it no longer redirects here.
    // RequireLivewireHeaders runs next and returns 404 for requests missing X-Livewire.
    $this->post(route('default-livewire.update'))->assertNotFound();
});

test('livewire update for a non-exempt page component is blocked by persistent middleware while must_change_password is true', function (): void {
    // Need a fully-permissioned user to load the dashboard snapshot.
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole('Admin');
    $this->actingAs($user);

    // GET the dashboard while the flag is false to obtain a valid, server-signed snapshot.
    $html = $this->get(route('dashboard'))->assertOk()->getContent();
    preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
    $snapshot = htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE);

    // Flip the flag in the DB; the next request re-loads the user from the session.
    $user->forceFill(['must_change_password' => true])->save();

    // Real Livewire POST:
    //   • web-group: EnforceMustChangePassword exempts *livewire.update → passes through
    //   • RequireLivewireHeaders: X-Livewire + JSON content-type present → passes
    //   • snapshot-verified: persistent middleware re-runs EnforceMustChangePassword
    //     against a fake request built from memo.path (/dashboard) → not exempt → HTTP 302
    $this->withHeaders(['X-Livewire' => '1', 'Content-Type' => 'application/json'])
        ->postJson(route('default-livewire.update'), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [],
            ]],
        ])
        ->assertRedirect(route('password.change'));
});

test('livewire update for the change-password component succeeds through real http and clears must_change_password', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user);

    // GET the change-password page to obtain a valid, server-signed snapshot.
    $html = $this->get(route('password.change'))->assertOk()->getContent();
    preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
    $snapshot = htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE);

    // Real Livewire POST:
    //   • web-group: exempted (routeIs *livewire.update) → passes
    //   • persistent middleware: re-runs against memo.path (password.change) → exempt → passes
    //   • updatePassword runs, sets must_change_password=false, adds redirect effect
    $response = $this->withHeaders(['X-Livewire' => '1', 'Content-Type' => 'application/json'])
        ->postJson(route('default-livewire.update'), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => [
                    'current_password' => 'password',
                    'password' => 'NewSecure!123',
                    'password_confirmation' => 'NewSecure!123',
                ],
                'calls' => [['method' => 'updatePassword', 'params' => []]],
            ]],
        ]);

    $response->assertOk();
    expect($response->json('components.0.effects.redirect'))->toBe(route('dashboard'));
    expect($user->fresh()->must_change_password)->toBeFalse();
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
