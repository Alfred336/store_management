<?php

namespace App\Livewire\Auth;

use App\Services\Auth\EmailVerificationService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Email verification screen for admin-created users.
 *
 * The user arrives here after changing their temporary password.
 * They enter the 6-digit code that was emailed to them.
 */
#[Layout('layouts.auth')]
#[Title('Verify your email')]
class VerifyEmailCode extends Component
{
    public string $code = '';

    public int $resendCooldown = 0;

    public function mount(): void
    {
        abort_unless(Auth::check(), 401);

        $user = Auth::user();

        // If the user still needs to change their password, send them there first.
        if ($user->must_change_password) {
            $this->redirect(route('password.change'), navigate: true);

            return;
        }

        // If email is already verified, nothing to do here.
        if ($user->email_verified_at !== null) {
            $this->redirect(route('dashboard'), navigate: true);

            return;
        }

        $this->resendCooldown = app(EmailVerificationService::class)->resendAvailableIn($user);
    }

    /**
     * Submit the 6-digit code for verification.
     */
    public function verify(): void
    {
        $this->validate(['code' => ['required', 'digits:6']]);

        $user = Auth::user();

        $throttleKey = 'verify-code:'.$user->id.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 20)) {
            $this->addError('code', 'Too many attempts. Please try again later.');

            return;
        }

        RateLimiter::hit($throttleKey, 300);

        $verified = app(EmailVerificationService::class)->verifyCode($user, $this->code);

        if ($verified) {
            RateLimiter::clear($throttleKey);
            $this->redirect(route('dashboard'), navigate: true);

            return;
        }

        $this->reset('code');
        $this->addError('code', 'The code is incorrect or has expired. Please try again or request a new code.');
    }

    /**
     * Re-send a fresh verification code to the user's email.
     */
    public function resend(): void
    {
        $user = Auth::user();
        $service = app(EmailVerificationService::class);

        if (! $service->canResend($user)) {
            $this->resendCooldown = $service->resendAvailableIn($user);

            return;
        }

        $service->sendCode($user);
        $service->recordResend($user);
        $this->resendCooldown = EmailVerificationService::RESEND_COOLDOWN_SECONDS;

        Flux::toast(variant: 'success', text: 'A new verification code has been sent to your email.');
    }

    /**
     * Log out and return to the login screen.
     */
    public function logout(): void
    {
        Auth::logout();
        Session::invalidate();
        Session::regenerateToken();

        $this->redirect(route('login'), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.auth.verify-email-code');
    }
}
