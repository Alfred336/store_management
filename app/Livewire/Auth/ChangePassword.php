<?php

namespace App\Livewire\Auth;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Closure;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
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
        $validated = $this->validate([
            'current_password' => $this->currentPasswordRules(),
            'password' => [
                ...$this->passwordRules(),
                function (string $_attribute, mixed $value, Closure $fail): void {
                    if (Hash::check($value, Auth::user()->getAuthPassword())) {
                        $fail('Your new password must be different from your current password.');
                    }
                },
            ],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        // Refresh the guard's cached user instance so that any middleware running
        // during the redirect sees must_change_password = false immediately,
        // without waiting for a new DB round-trip via the session user ID.
        Auth::setUser($user);

        // Store the new password hash in the session so that AuthenticateSession
        // (if ever enabled) does not invalidate this session on the next request.
        Session::put('password_hash_'.Auth::getDefaultDriver(), $user->getAuthPassword());

        Flux::toast(variant: 'success', text: 'Password updated successfully.');

        $this->redirect(route('dashboard'));
    }

    public function render(): View
    {
        return view('livewire.auth.change-password');
    }
}
