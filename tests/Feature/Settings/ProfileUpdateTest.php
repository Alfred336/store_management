<?php

use App\Livewire\Settings\Profile;
use App\Models\User;
use App\Services\Authorization\AdminAccountProtection;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/settings/profile')->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('settings.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

dataset('last administrator roles', ['Admin']);

test('the only Admin cannot delete their account and receives a clear message', function (string $roleName) {
    $role = Role::findOrCreate($roleName, 'web');
    $admin = User::factory()->create();
    $admin->assignRole($role);
    $this->actingAs($admin);

    Livewire::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasErrors(['account' => 'You cannot delete or deactivate the last Admin account. Assign Admin to another user first.']);

    expect($admin->fresh())->not->toBeNull()
        ->and(app(AdminAccountProtection::class)->isLastAdmin($admin))->toBeTrue();

    expect(Gate::forUser($admin)->allows('delete', $admin))->toBeFalse();
})->with('last administrator roles');

test('an Admin can delete their own account when another Admin remains', function () {
    $adminRole = Role::findOrCreate('Admin', 'web');
    $firstAdmin = User::factory()->create();
    $secondAdmin = User::factory()->create();
    $firstAdmin->assignRole($adminRole);
    $secondAdmin->assignRole($adminRole);
    expect(app(AdminAccountProtection::class)->adminCount())->toBe(2)
        ->and(app(AdminAccountProtection::class)->isLastAdmin($firstAdmin))->toBeFalse();
    $this->actingAs($firstAdmin);

    Livewire::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($firstAdmin->fresh())->toBeNull()
        ->and($secondAdmin->fresh())->not->toBeNull();
});

test('user policy blocks deactivation of the final Admin', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Admin', 'web'));

    expect(Gate::forUser($user)->allows('deactivate', $user))->toBeFalse();
});
