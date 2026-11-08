<?php

use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Livewire\Actions\Users\DeactivateUser;
use App\Livewire\Actions\Users\ResetUserPassword;
use App\Livewire\Auth\ChangePassword;
use App\Livewire\Auth\VerifyEmailCode;
use App\Mail\VerificationCodeMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

/** Create an unverified user ready to verify (password already changed). */
function unverifiedUser(): User
{
    return User::factory()->unverified()->create([
        'must_change_password' => false,
    ]);
}

// ─── EmailVerificationService ─────────────────────────────────────────────────

test('sendCode stores a hashed code and returns the plain text code', function (): void {
    Mail::fake();

    $user = unverifiedUser();
    $service = app(EmailVerificationService::class);

    $plain = $service->sendCode($user);

    expect($plain)->toHaveLength(6)->toMatch('/^\d{6}$/');

    $record = EmailVerificationCode::where('user_id', $user->id)->first();
    expect($record)->not->toBeNull();
    expect($record->code_hash)->not->toBe($plain); // stored hashed
    expect($record->attempts)->toBe(0);
    expect($record->used_at)->toBeNull();
    expect($record->expires_at->isFuture())->toBeTrue();
});

test('sendCode sends a mail to the user', function (): void {
    Mail::fake();

    $user = unverifiedUser();
    app(EmailVerificationService::class)->sendCode($user);

    Mail::assertSent(VerificationCodeMail::class, fn ($m) => $m->hasTo($user->email));
});

test('sendCode invalidates previous pending codes', function (): void {
    Mail::fake();

    $user = unverifiedUser();
    $service = app(EmailVerificationService::class);

    $service->sendCode($user);
    $firstId = EmailVerificationCode::where('user_id', $user->id)->latest('id')->value('id');

    $service->sendCode($user);

    $first = EmailVerificationCode::find($firstId);
    expect($first->used_at)->not->toBeNull(); // invalidated
    expect(EmailVerificationCode::where('user_id', $user->id)->whereNull('used_at')->count())->toBe(1);
});

test('verifyCode returns true and sets email_verified_at on correct code', function (): void {
    Mail::fake();

    $user = unverifiedUser();
    $service = app(EmailVerificationService::class);
    $plain = $service->sendCode($user);

    $result = $service->verifyCode($user, $plain);

    expect($result)->toBeTrue();
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('verifyCode returns false and increments attempts on wrong code', function (): void {
    Mail::fake();

    $user = unverifiedUser();
    $service = app(EmailVerificationService::class);
    $service->sendCode($user);

    $result = $service->verifyCode($user, '000000');

    expect($result)->toBeFalse();
    expect($user->fresh()->email_verified_at)->toBeNull();

    $record = EmailVerificationCode::where('user_id', $user->id)->whereNull('used_at')->first();
    expect($record->attempts)->toBe(1);
});

test('verifyCode returns false when code has exceeded max attempts', function (): void {
    Mail::fake();

    $user = unverifiedUser();
    $service = app(EmailVerificationService::class);
    $plain = $service->sendCode($user);

    // Exhaust all attempts.
    for ($i = 0; $i < EmailVerificationService::MAX_ATTEMPTS; $i++) {
        $service->verifyCode($user, '000000');
    }

    // Even the correct code should now fail (code is inactive).
    $result = $service->verifyCode($user, $plain);
    expect($result)->toBeFalse();
    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('verifyCode returns false for an expired code', function (): void {
    Mail::fake();

    $user = unverifiedUser();
    $service = app(EmailVerificationService::class);
    $plain = $service->sendCode($user);

    // Manually expire the code.
    EmailVerificationCode::where('user_id', $user->id)
        ->whereNull('used_at')
        ->update(['expires_at' => now()->subMinute()]);

    $result = $service->verifyCode($user, $plain);
    expect($result)->toBeFalse();
});

test('resend rate-limiting prevents sending too quickly', function (): void {
    Mail::fake();

    $user = unverifiedUser();
    $service = app(EmailVerificationService::class);

    expect($service->canResend($user))->toBeTrue();

    $service->recordResend($user);

    expect($service->canResend($user))->toBeFalse();
    expect($service->resendAvailableIn($user))->toBeGreaterThan(0);
});

test('deletePendingCodes removes unused codes', function (): void {
    Mail::fake();

    $user = unverifiedUser();
    $service = app(EmailVerificationService::class);
    $service->sendCode($user);

    expect(EmailVerificationCode::where('user_id', $user->id)->whereNull('used_at')->count())->toBe(1);

    $service->deletePendingCodes($user);

    expect(EmailVerificationCode::where('user_id', $user->id)->whereNull('used_at')->count())->toBe(0);
});

// ─── VerifyEmailCode Livewire component ───────────────────────────────────────

test('verify-code page is accessible for unverified user with password already changed', function (): void {
    $user = unverifiedUser();
    $this->actingAs($user);

    $this->get(route('verification.notice'))->assertOk();
});

test('verified user is redirected to dashboard from verify-code page', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);

    Livewire::actingAs($user)
        ->test(VerifyEmailCode::class)
        ->assertRedirect(route('dashboard'));
});

test('user with must_change_password is redirected to password-change from verify-code page', function (): void {
    $user = User::factory()->unverified()->create(['must_change_password' => true]);

    Livewire::actingAs($user)
        ->test(VerifyEmailCode::class)
        ->assertRedirect(route('password.change'));
});

test('correct code verifies email and redirects to dashboard', function (): void {
    Mail::fake();

    $user = unverifiedUser();
    $plain = app(EmailVerificationService::class)->sendCode($user);

    Livewire::actingAs($user)
        ->test(VerifyEmailCode::class)
        ->set('code', $plain)
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('wrong code shows generic error and does not verify email', function (): void {
    Mail::fake();

    $user = unverifiedUser();
    app(EmailVerificationService::class)->sendCode($user);

    Livewire::actingAs($user)
        ->test(VerifyEmailCode::class)
        ->set('code', '000000')
        ->call('verify')
        ->assertHasErrors(['code']);

    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('non-digits in code field fail validation', function (): void {
    $user = unverifiedUser();

    Livewire::actingAs($user)
        ->test(VerifyEmailCode::class)
        ->set('code', 'abc123')
        ->call('verify')
        ->assertHasErrors(['code']);
});

test('resend sends a new code when cooldown has elapsed', function (): void {
    Mail::fake();

    $user = unverifiedUser();
    RateLimiter::clear("resend-verification-code:{$user->id}");

    Livewire::actingAs($user)
        ->test(VerifyEmailCode::class)
        ->call('resend');

    Mail::assertSent(VerificationCodeMail::class, fn ($m) => $m->hasTo($user->email));
});

test('resend sets cooldown when too soon', function (): void {
    Mail::fake();

    $user = unverifiedUser();
    RateLimiter::hit("resend-verification-code:{$user->id}", EmailVerificationService::RESEND_COOLDOWN_SECONDS);

    $component = Livewire::actingAs($user)
        ->test(VerifyEmailCode::class)
        ->call('resend');

    expect($component->get('resendCooldown'))->toBeGreaterThan(0);
    // No second mail should have been sent.
    Mail::assertNothingSent();
});

test('logout from verify-code page ends session', function (): void {
    $user = unverifiedUser();

    Livewire::actingAs($user)
        ->test(VerifyEmailCode::class)
        ->call('logout')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

// ─── ChangePassword flow integration ─────────────────────────────────────────

test('changing password when email is unverified sends a code and redirects to verify-code', function (): void {
    Mail::fake();

    $user = User::factory()->unverified()->create(['must_change_password' => true]);
    $this->actingAs($user);

    Livewire::test(ChangePassword::class)
        ->set('current_password', 'password')
        ->set('password', 'NewSecure!123')
        ->set('password_confirmation', 'NewSecure!123')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('verification.notice'));

    Mail::assertSent(VerificationCodeMail::class, fn ($m) => $m->hasTo($user->email));
    expect($user->fresh()->must_change_password)->toBeFalse();
    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('changing password when email is already verified redirects to dashboard', function (): void {
    // This covers the admin password-reset path where email stays verified.
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user);

    Livewire::test(ChangePassword::class)
        ->set('current_password', 'password')
        ->set('password', 'NewSecure!123')
        ->set('password_confirmation', 'NewSecure!123')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));
});

// ─── Routing — no redirect loop ───────────────────────────────────────────────

test('password.change route is accessible without email verification', function (): void {
    $user = User::factory()->unverified()->create(['must_change_password' => true]);
    $this->actingAs($user);

    // Must NOT be redirected to verification.notice — that would be a loop.
    $this->get(route('password.change'))->assertOk();
});

test('verify-code route is accessible without email verification', function (): void {
    $user = unverifiedUser();
    $this->actingAs($user);

    $this->get(route('verification.notice'))->assertOk();
});

test('dashboard redirects unverified user to verification.notice', function (): void {
    $user = unverifiedUser();
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});

// ─── ResetUserPassword deletes pending codes ─────────────────────────────────

test('resetting password deletes pending verification codes', function (): void {
    Mail::fake();

    app(EnsureSystemRoles::class)();
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $target = unverifiedUser();
    app(EmailVerificationService::class)->sendCode($target);
    expect(EmailVerificationCode::where('user_id', $target->id)->whereNull('used_at')->count())->toBe(1);

    app(ResetUserPassword::class)($target);

    expect(EmailVerificationCode::where('user_id', $target->id)->whereNull('used_at')->count())->toBe(0);
});

// ─── DeactivateUser deletes pending codes ────────────────────────────────────

test('deactivating a user deletes their pending verification codes', function (): void {
    Mail::fake();

    app(EnsureSystemRoles::class)();
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $target = unverifiedUser();
    $target->assignRole('Cashier');
    app(EmailVerificationService::class)->sendCode($target);
    expect(EmailVerificationCode::where('user_id', $target->id)->whereNull('used_at')->count())->toBe(1);

    app(DeactivateUser::class)($target);

    expect(EmailVerificationCode::where('user_id', $target->id)->whereNull('used_at')->count())->toBe(0);
});
