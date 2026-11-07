<?php

use App\Livewire\Auth\ChangePassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
