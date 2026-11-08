<?php

namespace App\Services\Auth;

use App\Mail\VerificationCodeMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Manages the 6-digit email verification code flow for admin-created users.
 *
 * Codes are stored hashed (HMAC-SHA256) and expire after 15 minutes.
 * After 5 wrong attempts the code is invalidated; resend is throttled to
 * one request per 60 seconds.  Emails are sent synchronously.
 */
class EmailVerificationService
{
    public const CODE_EXPIRY_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Generate a 6-digit code, invalidate previous pending codes, persist the
     * hash, send the email synchronously, and return the plain-text code.
     */
    public function sendCode(User $user): string
    {
        $plain = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = $this->hash($plain);

        DB::transaction(function () use ($user, $hash): void {
            // Invalidate any previous pending codes.
            EmailVerificationCode::where('user_id', $user->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            EmailVerificationCode::create([
                'user_id' => $user->id,
                'code_hash' => $hash,
                'expires_at' => now()->addMinutes(self::CODE_EXPIRY_MINUTES),
                'attempts' => 0,
                'created_at' => now(),
            ]);
        });

        // Send synchronously — does not require a queue worker.
        Mail::to($user->email)->send(new VerificationCodeMail($user, $plain));

        return $plain;
    }

    /**
     * Attempt to verify a submitted code for the given user.
     *
     * On success:  marks the code as used and sets email_verified_at.
     * On failure:  increments attempts on the code row.
     *
     * Returns true on success, false on any failure (wrong, expired, maxed).
     */
    public function verifyCode(User $user, string $submitted): bool
    {
        return DB::transaction(function () use ($user, $submitted): bool {
            $record = $this->activeCode($user);

            if ($record === null) {
                return false;
            }

            if (! hash_equals($record->code_hash, $this->hash($submitted))) {
                $record->increment('attempts');

                return false;
            }

            $record->forceFill(['used_at' => now()])->save();
            $user->forceFill(['email_verified_at' => now()])->save();

            return true;
        });
    }

    /**
     * Find the active (un-used, unexpired, under attempt limit) code for a user.
     */
    public function activeCode(User $user): ?EmailVerificationCode
    {
        return EmailVerificationCode::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->latest('id')
            ->first();
    }

    /** True when the resend cooldown has elapsed for this user. */
    public function canResend(User $user): bool
    {
        return ! RateLimiter::tooManyAttempts($this->resendKey($user), 1);
    }

    /** Seconds until the resend cooldown expires (0 if already allowed). */
    public function resendAvailableIn(User $user): int
    {
        return max(0, RateLimiter::availableIn($this->resendKey($user)));
    }

    /** Record a resend attempt against the rate-limiter. */
    public function recordResend(User $user): void
    {
        RateLimiter::hit($this->resendKey($user), self::RESEND_COOLDOWN_SECONDS);
    }

    /**
     * Delete pending (unused) codes — called on admin password-reset or deactivation.
     * Does NOT reset email_verified_at (the email stays verified on admin reset).
     */
    public function deletePendingCodes(User $user): void
    {
        EmailVerificationCode::where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function hash(string $plain): string
    {
        return hash_hmac('sha256', $plain, config('app.key'));
    }

    private function resendKey(User $user): string
    {
        return "resend-verification-code:{$user->id}";
    }
}
