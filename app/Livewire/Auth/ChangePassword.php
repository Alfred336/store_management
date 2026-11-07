<?php

namespace App\Livewire\Auth;

use App\Concerns\PasswordValidationRules;
use Closure;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
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

        Auth::user()->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: 'Password updated successfully.');

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.change-password');
    }
}
