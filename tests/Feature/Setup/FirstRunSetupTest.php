<?php

use App\Livewire\Setup\Index as SetupIndex;
use App\Models\User;
use App\Services\Settings\Settings;
use Livewire\Livewire;

function completeSetupForm(): array
{
    return [
        'businessName' => 'Kilimanjaro Supplies',
        'businessEmail' => 'hello@kilimanjaro.example',
        'phone' => '+255 700 000 000',
        'address' => 'Arusha, Tanzania',
        'businessTin' => 'TIN-123',
        'businessVrn' => 'VRN-456',
        'adminName' => 'Business Owner',
        'adminEmail' => 'owner@example.test',
        'password' => 'Kilimanjaro12345',
        'passwordConfirmation' => 'Kilimanjaro12345',
    ];
}

test('first-run setup creates one administrator and stores company details in settings', function () {
    Livewire::test(SetupIndex::class)
        ->fill(completeSetupForm())
        ->call('complete')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'owner@example.test')->firstOrFail();
    expect($user->hasRole('Owner/Admin'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(app(Settings::class)->get('business.name'))->toBe('Kilimanjaro Supplies')
        ->and(app(Settings::class)->get('business.currency'))->toBe('TZS');

    $this->assertAuthenticatedAs($user);
    expect(User::query()->count())->toBe(1);
});

test('initial setup validates required fields and password confirmation', function () {
    Livewire::test(SetupIndex::class)
        ->set('businessName', '')
        ->set('adminName', 'Owner')
        ->set('adminEmail', 'owner@example.test')
        ->set('password', 'short')
        ->set('passwordConfirmation', 'different')
        ->call('complete')
        ->assertHasErrors(['businessName', 'password']);

    expect(User::query()->count())->toBe(0)
        ->and(app(Settings::class)->get('business.name'))->toBeNull();
});

test('initial setup is blocked once any user exists', function () {
    User::factory()->create();

    Livewire::test(SetupIndex::class)->assertNotFound();
});

test('initial setup can only be completed once', function () {
    Livewire::test(SetupIndex::class)
        ->fill(completeSetupForm())
        ->call('complete')
        ->assertHasNoErrors();

    Livewire::test(SetupIndex::class)->assertNotFound();
});
