<?php

use App\Livewire\Settings\Profile;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\AdminAccountProtection;
use Illuminate\Support\Facades\DB;
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
        ->assertHasErrors(['account' => 'You cannot delete or deactivate the last active Admin account. Assign Admin to another user first.']);

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

// ─── Activity blocks self-deletion ───────────────────────────────────────────

$activityMessage = 'Your account has activity history and cannot be deleted. Ask an administrator to deactivate your account instead.';

dataset('self-delete activity types', [
    'invoice activity' => [static function (int $userId): void {
        $customerId = DB::table('customers')->insertGetId([
            'code' => 'ACT-TEST',
            'name' => 'Test Customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $locationId = DB::table('locations')->insertGetId([
            'name' => 'Test Location',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('invoices')->insert([
            'created_by' => $userId,
            'customer_id' => $customerId,
            'location_id' => $locationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }],
    'payment activity' => [static function (int $userId): void {
        $customerId = DB::table('customers')->insertGetId([
            'code' => 'PAY-TEST',
            'name' => 'Pay Customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('payments')->insert([
            'number' => 'PAY-001',
            'customer_id' => $customerId,
            'method' => 'cash',
            'amount' => '100.00',
            'received_at' => now(),
            'received_by' => $userId,
            'idempotency_key' => 'test-key-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }],
    'stock movement activity' => [static function (int $userId): void {
        $unit = Unit::factory()->create();
        $product = Product::factory()->create(['base_unit_id' => $unit->id]);
        $locationId = DB::table('locations')->insertGetId([
            'name' => 'Stock Loc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_movements')->insert([
            'product_id' => $product->id,
            'location_id' => $locationId,
            'type' => 'receive',
            'quantity' => '10.000',
            'user_id' => $userId,
            'moved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }],
    'pos session activity' => [static function (int $userId): void {
        $locationId = DB::table('locations')->insertGetId([
            'name' => 'POS Loc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('pos_sessions')->insert([
            'user_id' => $userId,
            'location_id' => $locationId,
            'opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }],
]);

test('user with activity cannot delete their own account and is told to ask an administrator', function (Closure $seedActivity) use ($activityMessage): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $seedActivity($user->id);

    Livewire::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasErrors(['account' => $activityMessage]);

    expect($user->fresh())->not->toBeNull();
})->with('self-delete activity types');
