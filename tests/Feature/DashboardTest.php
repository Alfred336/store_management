<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('products.view', 'web');
    $user->givePermissionTo($permission);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
