<?php

use App\Livewire\Settings\Index as SettingsIndex;
use App\Models\User;
use App\Services\Settings\Settings;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function userWithSettingsPermissions(array $permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user->givePermissionTo($permissions);

    return $user;
}

test('settings screen saves the company profile, VAT and numbering settings', function () {
    $user = userWithSettingsPermissions(['settings.view', 'settings.update']);

    Livewire::actingAs($user)->test(SettingsIndex::class)
        ->set('businessName', 'Mlimani Traders')
        ->set('businessEmail', 'office@example.test')
        ->set('businessPhone', '+255700000000')
        ->set('businessAddress', 'Dar es Salaam')
        ->set('businessTin', 'TIN-1')
        ->set('businessVrn', 'VRN-2')
        ->set('currency', 'TZS')
        ->set('vatRate', '18.00')
        ->set('pricesIncludeTax', false)
        ->set('invoicePrefix', 'INV')
        ->set('receiptPrefix', 'RCT')
        ->set('paymentPrefix', 'PAY')
        ->set('resetYearly', true)
        ->set('numberDigits', '6')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(Settings::class)->get('business.name'))->toBe('Mlimani Traders')
        ->and(app(Settings::class)->get('tax.prices_include_tax'))->toBeFalse()
        ->and(app(Settings::class)->get('numbering.invoice_prefix'))->toBe('INV');
});

test('users without settings view permission cannot open settings', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(SettingsIndex::class)->assertForbidden();
});

test('settings service caches reads and invalidates after writes', function () {
    $settings = app(Settings::class);

    expect($settings->get('business.name', 'Default'))->toBe('Default');
    $settings->put('business.name', 'Fresh Value');

    expect($settings->get('business.name'))->toBe('Fresh Value');
});
