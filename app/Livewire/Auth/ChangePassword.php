<?php

namespace App\Livewire\Auth;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Closure;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Forced password-change screen shown when must_change_password is true.
 * No password.confirm gate is applied here (the user just authenticated).
 */
#[Layout('layouts.auth')]
#[Title('Change your password')]
class ChangePassword extends Component
{
    use PasswordValidationRules;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        abort_unless(Auth::check(), 401);
    }

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => [
                    ...$this->passwordRules(),
                    function (string $attribute, mixed $value, Closure $fail): void {
                        if (Hash::check($value, Auth::user()->getAuthPassword())) {
                            $fail('Your new password must be different from your current password.');
                        }
                    },
                ],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');
            throw $e;
        }

        /** @var User $user */
        $user = Auth::user();

        // Admin-created users must verify their email after changing their temporary password.
        // The password save and code creation must both succeed or both roll back — a mail
        // failure must not leave the user with a changed password but no code to verify.
        if ($user->email_verified_at === null) {
            DB::beginTransaction();
            try {
                $user->forceFill([
                    'password' => $validated['password'],
                    'must_change_password' => false,
                ])->save();

                // sendCode() runs its own nested transaction (savepoint) then sends the mail.
                // If Mail::send() throws, the exception propagates here, we roll back the
                // outer transaction, and neither the password change nor the code record persist.
                app(EmailVerificationService::class)->sendCode($user);

                DB::commit();
            } catch (\Throwable) {
                DB::rollBack();
                $this->addError('password', 'Could not send the verification email. Please try again or contact your administrator.');

                return;
            }

            $this->redirect(route('verification.code'), navigate: true);

            return;
        }

        // Already-verified path (e.g. admin password-reset where email stays verified).
        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        Flux::toast(variant: 'success', text: 'Password updated successfully.');

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.change-password');
    }
}
