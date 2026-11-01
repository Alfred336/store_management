<?php

namespace App\Livewire\Setup;

use App\Actions\Setup\CompleteInitialSetup;
use App\Services\Setup\SetupStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\RedirectResponse;

/** Collect the initial business profile and owner account on a fresh install. */
#[Title('Initial setup')]
#[Layout('layouts.auth')]
class Index extends Component
{
    public string $businessName = '';

    public string $businessEmail = '';

    public string $phone = '';

    public string $address = '';

    public string $businessTin = '';

    public string $businessVrn = '';

    public string $adminName = '';

    public string $adminEmail = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(SetupStatus $setupStatus): void
    {
        abort_unless($setupStatus->isRequired(), 404);
    }

    /** Validate and save the one-time setup, then sign in the new administrator. */
    public function complete(CompleteInitialSetup $completeInitialSetup, SetupStatus $setupStatus): RedirectResponse
    {
        abort_unless($setupStatus->isRequired(), 404);

        $validated = $this->validate([
            'businessName' => ['required', 'string', 'max:255'],
            'businessEmail' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'businessTin' => ['nullable', 'string', 'max:50'],
            'businessVrn' => ['nullable', 'string', 'max:50'],
            'adminName' => ['required', 'string', 'max:255'],
            'adminEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed:passwordConfirmation', Password::min(12)->letters()->numbers()],
        ]);

        $user = $completeInitialSetup([
            'business_name' => $validated['businessName'],
            'business_email' => $validated['businessEmail'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'address' => $validated['address'] ?: null,
            'tin' => $validated['businessTin'] ?: null,
            'vrn' => $validated['businessVrn'] ?: null,
            'admin_name' => $validated['adminName'],
            'admin_email' => strtolower($validated['adminEmail']),
            'password' => $validated['password'],
        ]);

        Auth::login($user);
        session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function render(): View
    {
        return view('livewire.setup.index');
    }
}
