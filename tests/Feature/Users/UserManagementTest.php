<?php

use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Livewire\Actions\Users\AnonymizeUser;
use App\Livewire\Actions\Users\CreateUser;
use App\Livewire\Actions\Users\DeactivateUser;
use App\Livewire\Actions\Users\DeleteUser;
use App\Livewire\Actions\Users\ReactivateUser;
use App\Livewire\Actions\Users\ResetTwoFactor;
use App\Livewire\Actions\Users\ResetUserPassword;
use App\Livewire\Actions\Users\UpdateUser;
use App\Livewire\Users\Index as UsersIndex;
use App\Mail\TemporaryPasswordMail;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function adminUser(): User
{
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('Admin');

    return $user;
}

function nonAdminUser(string $role = 'Cashier'): User
{
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);

    return $user;
}

// ─── Registration disabled ───────────────────────────────────────────────────

test('/register returns 404 when registration is disabled', function (): void {
    $this->get('/register')->assertStatus(404);
});

test('POST /register returns 404 when registration is disabled', function (): void {
    $this->post('/register', ['name' => 'T', 'email' => 'x@x.com', 'password' => 'password', 'password_confirmation' => 'password'])->assertStatus(404);
});

// ─── CreateUser ──────────────────────────────────────────────────────────────

test('admin can create a user with a role and must_change_password is set', function (): void {
    Mail::fake();

    $admin = adminUser();
    $this->actingAs($admin);

    $result = app(CreateUser::class)('Jane Doe', 'jane@example.com', ['Cashier']);

    expect($result)->toHaveKey('user');
    $user = $result['user'];
    expect($user->name)->toBe('Jane Doe');
    expect($user->email)->toBe('jane@example.com');
    expect($user->must_change_password)->toBeTrue();
    expect($user->hasRole('Cashier'))->toBeTrue();

    Mail::assertSent(TemporaryPasswordMail::class, fn ($m) => $m->hasTo($user->email));
});

test('create user sends exactly one welcome email with the temporary password', function (): void {
    Mail::fake();

    $admin = adminUser();
    $this->actingAs($admin);

    app(CreateUser::class)('Bob Smith', 'bob@example.com', ['Cashier']);

    Mail::assertSent(TemporaryPasswordMail::class, 1);
});

test('create user writes audit log without password', function (): void {
    Mail::fake();

    $admin = adminUser();
    $this->actingAs($admin);

    $result = app(CreateUser::class)('Bob Smith', 'bob@example.com', ['Cashier']);

    $log = AdminAuditLog::where('action', 'user.created')->where('subject_id', $result['user']->id)->first();
    expect($log)->not->toBeNull();
    expect($log->changes)->not->toHaveKey('password');
    expect($log->actor_id)->toBe($admin->id);
});

test('non-admin cannot assign Admin role', function (): void {
    // User with users.create permission but not Admin role.
    app(EnsureSystemRoles::class)();
    $actor = User::factory()->create(['is_active' => true]);
    Permission::findOrCreate('users.create', 'web');
    $actor->givePermissionTo('users.create');
    $this->actingAs($actor);

    expect(fn () => app(CreateUser::class)('X', 'x@example.com', ['Admin']))->toThrow(ValidationException::class);
});

test('create requires at least one role', function (): void {
    $admin = adminUser();
    $this->actingAs($admin);

    expect(fn () => app(CreateUser::class)('X', 'x@example.com', []))->toThrow(ValidationException::class);
});

test('create fails if email already exists', function (): void {
    Mail::fake();

    $admin = adminUser();
    $this->actingAs($admin);
    app(CreateUser::class)('First', 'dup@example.com', ['Cashier']);

    expect(fn () => app(CreateUser::class)('Second', 'dup@example.com', ['Cashier']))->toThrow(ValidationException::class);
});

test('mail failure during create rolls back user creation', function (): void {
    $admin = adminUser();
    $this->actingAs($admin);

    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP error'));

    expect(fn () => app(CreateUser::class)('Ghost', 'ghost@example.com', ['Cashier']))
        ->toThrow(RuntimeException::class);

    expect(User::where('email', 'ghost@example.com')->exists())->toBeFalse();
});

// ─── Inactive user cannot log in ─────────────────────────────────────────────

test('inactive user cannot log in and receives a helpful error', function (): void {
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create(['is_active' => false, 'password' => Hash::make('password')]);
    $user->assignRole('Cashier');

    $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $response->assertSessionHasErrors();
    $this->assertGuest();
});

test('active user can log in', function (): void {
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create(['is_active' => true, 'password' => Hash::make('password')]);
    $user->assignRole('Cashier');

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

// ─── Active session ends when user is deactivated ────────────────────────────

test('deactivating a user deletes their sessions', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');

    // Simulate a session row for the target user.
    DB::table('sessions')->insert([
        'id' => 'fake-session-id',
        'user_id' => $target->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => 'x',
        'last_activity' => time(),
    ]);

    $this->actingAs($admin);
    app(DeactivateUser::class)($target);

    expect(DB::table('sessions')->where('user_id', $target->id)->exists())->toBeFalse();
    expect($target->fresh()->is_active)->toBeFalse();
});

// ─── Last active Admin protection ────────────────────────────────────────────

test('cannot deactivate the last active Admin', function (): void {
    $admin = adminUser(); // The only active Admin.
    // Use a separate actor with users.deactivate so the self-protection gate passes.
    $actor = User::factory()->create(['is_active' => true]);
    Permission::findOrCreate('users.deactivate', 'web');
    $actor->givePermissionTo('users.deactivate');
    $this->actingAs($actor);

    expect(fn () => app(DeactivateUser::class)($admin))->toThrow(ValidationException::class);
});

test('cannot delete the last active Admin', function (): void {
    $admin = adminUser();
    $this->actingAs($admin);

    expect(fn () => app(DeleteUser::class)($admin))->toThrow(AuthorizationException::class);
});

test('can deactivate an Admin when another active Admin exists', function (): void {
    $admin1 = adminUser();
    $admin2 = User::factory()->create(['is_active' => true]);
    $admin2->assignRole('Admin');

    $this->actingAs($admin1);
    app(DeactivateUser::class)($admin2);

    expect($admin2->fresh()->is_active)->toBeFalse();
});

// ─── Self-protection ─────────────────────────────────────────────────────────

test('admin cannot update themselves via UpdateUser', function (): void {
    $admin = adminUser();
    $this->actingAs($admin);

    expect(fn () => app(UpdateUser::class)($admin, 'New Name', 'new@example.com', ['Admin']))->toThrow(AuthorizationException::class);
});

test('admin cannot reset their own password via ResetUserPassword', function (): void {
    $admin = adminUser();
    $this->actingAs($admin);

    expect(fn () => app(ResetUserPassword::class)($admin))->toThrow(AuthorizationException::class);
});

test('admin cannot delete themselves via DeleteUser', function (): void {
    $admin = adminUser();
    $this->actingAs($admin);

    expect(fn () => app(DeleteUser::class)($admin))->toThrow(AuthorizationException::class);
});

// ─── Privilege escalation ─────────────────────────────────────────────────────

test('non-admin cannot assign a role with permissions they do not hold', function (): void {
    // Actor has users.update but is not Admin, so has limited permissions.
    app(EnsureSystemRoles::class)();
    $actor = User::factory()->create(['is_active' => true]);
    Permission::findOrCreate('users.update', 'web');
    $actor->givePermissionTo('users.update');
    // Also give actor Cashier-level permissions (not Storekeeper's).
    $actor->assignRole('Cashier');

    $target = nonAdminUser('Viewer');
    $this->actingAs($actor);

    // Storekeeper has inventory.receive etc. which Cashier doesn't have.
    expect(fn () => app(UpdateUser::class)($target, $target->name, $target->email, ['Storekeeper']))
        ->toThrow(ValidationException::class);
});

// ─── ResetUserPassword ───────────────────────────────────────────────────────

test('reset password sets must_change_password, deletes sessions and emails the user', function (): void {
    Mail::fake();

    $admin = adminUser();
    $target = nonAdminUser('Cashier');
    $target->forceFill(['must_change_password' => false])->save();

    DB::table('sessions')->insert([
        'id' => 'sess-123',
        'user_id' => $target->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => 'x',
        'last_activity' => time(),
    ]);

    $this->actingAs($admin);
    $result = app(ResetUserPassword::class)($target);

    expect($result)->toHaveKey('user');
    expect($result['user']->must_change_password)->toBeTrue();
    expect(DB::table('sessions')->where('user_id', $target->id)->exists())->toBeFalse();

    Mail::assertSent(TemporaryPasswordMail::class, fn ($m) => $m->hasTo($target->email));

    $log = AdminAuditLog::where('action', 'user.password_reset')->where('subject_id', $target->id)->first();
    expect($log)->not->toBeNull();
    expect($log->changes)->toBeNull(); // no password in changes
});

test('mail failure during reset rolls back the password change', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');
    $originalHash = $target->password;
    $this->actingAs($admin);

    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP error'));

    expect(fn () => app(ResetUserPassword::class)($target))->toThrow(RuntimeException::class);

    // Password must not have changed.
    expect($target->fresh()->password)->toBe($originalHash);
    expect($target->fresh()->must_change_password)->toBeFalse();
});

// ─── ResetTwoFactor ──────────────────────────────────────────────────────────

test('reset 2FA clears two factor columns and deletes sessions', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');
    $target->forceFill([
        'two_factor_secret' => 'secret',
        'two_factor_recovery_codes' => 'codes',
        'two_factor_confirmed_at' => now(),
    ])->save();

    DB::table('sessions')->insert([
        'id' => 'sess-2fa',
        'user_id' => $target->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => 'x',
        'last_activity' => time(),
    ]);

    $this->actingAs($admin);
    $updated = app(ResetTwoFactor::class)($target);

    expect($updated->two_factor_secret)->toBeNull();
    expect($updated->two_factor_recovery_codes)->toBeNull();
    expect($updated->two_factor_confirmed_at)->toBeNull();
    expect(DB::table('sessions')->where('user_id', $target->id)->exists())->toBeFalse();

    $log = AdminAuditLog::where('action', 'user.two_factor_reset')->where('subject_id', $target->id)->first();
    expect($log)->not->toBeNull();
});

// ─── DeleteUser with activity ────────────────────────────────────────────────

test('delete is refused when user has invoice activity', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');

    $customerId = DB::table('customers')->insertGetId([
        'code' => 'TST-001',
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
        'created_by' => $target->id,
        'customer_id' => $customerId,
        'location_id' => $locationId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin);

    expect(fn () => app(DeleteUser::class)($target))->toThrow(ValidationException::class);
});

test('delete works when user has no activity', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');

    $this->actingAs($admin);
    app(DeleteUser::class)($target);

    expect(User::find($target->id))->toBeNull();

    $log = AdminAuditLog::where('action', 'user.deleted')->where('subject_id', $target->id)->first();
    expect($log)->not->toBeNull();
});

// ─── Reactivate ──────────────────────────────────────────────────────────────

test('admin can reactivate an inactive user', function (): void {
    $admin = adminUser();
    $target = User::factory()->create(['is_active' => false]);
    $target->assignRole('Cashier');

    $this->actingAs($admin);
    $updated = app(ReactivateUser::class)($target);

    expect($updated->is_active)->toBeTrue();

    $log = AdminAuditLog::where('action', 'user.reactivated')->where('subject_id', $target->id)->first();
    expect($log)->not->toBeNull();
});

// ─── last_login_at recorded on login ────────────────────────────────────────

test('last_login_at is recorded when Login event is fired', function (): void {
    $user = User::factory()->create(['last_login_at' => null]);

    Event::dispatch(new Login('web', $user, false));

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

// ─── /admin/users route authorisation ────────────────────────────────────────

test('admin can access /admin/users', function (): void {
    $admin = adminUser();
    $this->actingAs($admin)->get('/admin/users')->assertOk();
});

test('cashier cannot access /admin/users', function (): void {
    $cashier = nonAdminUser('Cashier');
    $this->actingAs($cashier)->get('/admin/users')->assertForbidden();
});

test('storekeeper cannot access /admin/users', function (): void {
    $storekeeper = nonAdminUser('Storekeeper');
    $this->actingAs($storekeeper)->get('/admin/users')->assertForbidden();
});

test('viewer cannot access /admin/users', function (): void {
    $viewer = nonAdminUser('Viewer');
    $this->actingAs($viewer)->get('/admin/users')->assertForbidden();
});

// ─── Audit log does not contain passwords ────────────────────────────────────

test('audit log for user.created never contains password field', function (): void {
    Mail::fake();

    $admin = adminUser();
    $this->actingAs($admin);

    $result = app(CreateUser::class)('Secret User', 'secret@example.com', ['Cashier']);

    $logs = AdminAuditLog::where('action', 'user.created')->get();
    foreach ($logs as $log) {
        $changes = $log->changes ?? [];
        expect($changes)->not->toHaveKey('password');
    }
    // Verify that the generated password (which is in the mailable) never surfaces in the audit log.
    expect(json_encode($logs->pluck('changes')->toArray()))->not->toContain('password');
});

// ─── Deactivate with open POS session ────────────────────────────────────────

test('deactivating a user with an open POS session is refused with a clear message', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');

    $locationId = DB::table('locations')->insertGetId([
        'name' => 'Open Session Loc',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('pos_sessions')->insert([
        'user_id' => $target->id,
        'location_id' => $locationId,
        'opened_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin);

    expect(fn () => app(DeactivateUser::class)($target))
        ->toThrow(ValidationException::class, 'This user has an open POS session. Close it before deactivating.');
    expect($target->fresh()->is_active)->toBeTrue();
});

// ─── Self-deactivation ────────────────────────────────────────────────────────

test('admin cannot deactivate themselves via DeactivateUser', function (): void {
    $admin = adminUser();
    $this->actingAs($admin);

    expect(fn () => app(DeactivateUser::class)($admin))
        ->toThrow(AuthorizationException::class);
    expect($admin->fresh()->is_active)->toBeTrue();
});

// ─── EnforceActiveUser without session deletion ───────────────────────────────

test('EnforceActiveUser logs out a mid-session user who is deactivated even when their session row is kept', function (): void {
    $user = User::factory()->create(['is_active' => true]);

    // Insert a session row to prove EnforceActiveUser does not rely on session deletion.
    DB::table('sessions')->insert([
        'id' => 'test-mid-session',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => 'x',
        'last_activity' => time(),
    ]);

    // Deactivate directly in DB — no call to DeactivateUser, so the session row remains.
    DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
    expect(DB::table('sessions')->where('id', 'test-mid-session')->exists())->toBeTrue();

    // Fetch the user fresh from DB (is_active = false) to simulate what the
    // session guard does via retrieveById on each real HTTP request.
    $freshUser = User::find($user->id);
    $this->actingAs($freshUser);

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('EnforceActiveUser also blocks a Livewire wire call from a deactivated user', function (): void {
    $freshUser = User::factory()->create(['is_active' => false]);
    $this->actingAs($freshUser);

    // The same HTTP middleware that handles page loads also intercepts Livewire
    // wire calls, so a deactivated user cannot interact with any Livewire component.
    $this->post(route('default-livewire.update'))->assertRedirect(route('login'));
    $this->assertGuest();
});

// ─── Users Index Livewire component: role checkboxes ─────────────────────────

test('create form renders one checkbox for every available role', function (): void {
    $admin = adminUser(); // creates Admin, Storekeeper, Cashier, Viewer roles
    $this->actingAs($admin);

    Livewire::test(UsersIndex::class)
        ->call('openCreate')
        ->assertSee('Admin')
        ->assertSee('Storekeeper')
        ->assertSee('Cashier')
        ->assertSee('Viewer');
});

test('admin creates a user via the component and user gets role and must_change_password', function (): void {
    Mail::fake();

    $admin = adminUser();
    $this->actingAs($admin);

    Livewire::test(UsersIndex::class)
        ->set('name', 'New User')
        ->set('email', 'newuser@example.com')
        ->set('roles', ['Cashier'])
        ->call('store')
        ->assertHasNoErrors();

    $user = User::where('email', 'newuser@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->must_change_password)->toBeTrue();
    expect($user->hasRole('Cashier'))->toBeTrue();

    Mail::assertSent(TemporaryPasswordMail::class, fn ($m) => $m->hasTo($user->email));
});

test('submitting create form without a role shows roles validation error', function (): void {
    $admin = adminUser();
    $this->actingAs($admin);

    Livewire::test(UsersIndex::class)
        ->set('name', 'No Role User')
        ->set('email', 'norole@example.com')
        ->set('roles', [])
        ->call('store')
        ->assertHasErrors(['roles']);
});

test('create form shows error when mail fails and does not create the user', function (): void {
    $admin = adminUser();
    $this->actingAs($admin);

    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP error'));

    Livewire::test(UsersIndex::class)
        ->set('name', 'Mail Fail User')
        ->set('email', 'mailfail@example.com')
        ->set('roles', ['Cashier'])
        ->call('store')
        ->assertHasErrors(['error']);

    expect(User::where('email', 'mailfail@example.com')->exists())->toBeFalse();
});

// ─── DeleteUser: open POS session protection ─────────────────────────────────

test('delete is refused when user has an open POS session', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');

    $locationId = DB::table('locations')->insertGetId([
        'name' => 'POS Loc Delete',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('pos_sessions')->insert([
        'user_id' => $target->id,
        'location_id' => $locationId,
        'status' => 'open',
        'opened_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin);

    expect(fn () => app(DeleteUser::class)($target))
        ->toThrow(ValidationException::class, 'This user has an open POS session. Close it before deleting.');
    expect(User::find($target->id))->not->toBeNull();
});

// ─── AnonymizeUser ────────────────────────────────────────────────────────────

test('anonymize wipes personal data and sets anonymized_at', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');
    $originalId = $target->id;

    // Give the target some activity so the anonymize path is valid.
    $customerId = DB::table('customers')->insertGetId([
        'code' => 'ANO-001', 'name' => 'Anon Customer', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $locationId = DB::table('locations')->insertGetId([
        'name' => 'Anon Location', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('invoices')->insert([
        'created_by' => $target->id, 'customer_id' => $customerId, 'location_id' => $locationId,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($admin);
    app(AnonymizeUser::class)($target);

    // The row still exists (anonymized, not deleted).
    $row = DB::table('users')->where('id', $originalId)->first();
    expect($row)->not->toBeNull();
    expect($row->name)->toBe("Deleted user #{$originalId}");
    expect($row->email)->toBe("deleted-{$originalId}@deleted.invalid");
    expect($row->is_active)->toBeFalse();
    expect($row->anonymized_at)->not->toBeNull();
    expect($row->two_factor_secret)->toBeNull();
    expect($row->two_factor_recovery_codes)->toBeNull();
});

test('anonymize keeps invoice reference pointing to the row', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');
    $targetId = $target->id;

    $customerId = DB::table('customers')->insertGetId([
        'code' => 'ANO-002', 'name' => 'Anon Customer 2', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $locationId = DB::table('locations')->insertGetId([
        'name' => 'Anon Location 2', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('invoices')->insert([
        'created_by' => $targetId, 'customer_id' => $customerId, 'location_id' => $locationId,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($admin);
    app(AnonymizeUser::class)($target);

    // Invoice still references the row.
    $invoice = DB::table('invoices')->where('created_by', $targetId)->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->created_by)->toBe($targetId);

    // The name on the row is now the anonymized sentinel.
    $userRow = DB::table('users')->where('id', $targetId)->first();
    expect($userRow->name)->toBe("Deleted user #{$targetId}");
});

test('anonymize writes audit log without PII in changes', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');

    // Add activity.
    DB::table('admin_audit_logs')->insert([
        'actor_id' => $target->id, 'action' => 'some.action',
        'subject_type' => 'App\\Models\\User', 'subject_id' => $target->id,
        'created_at' => now(),
    ]);

    $this->actingAs($admin);
    app(AnonymizeUser::class)($target);

    $log = AdminAuditLog::where('action', 'user.anonymized')->where('subject_id', $target->id)->first();
    expect($log)->not->toBeNull();
    expect($log->actor_id)->toBe($admin->id);
    expect($log->subject_id)->toBe($target->id);
    // No PII in changes — only subject_id and actor_id carry the identity.
    expect($log->changes)->toBeNull();
});

test('scrub replaces exact name/email values but not substrings, other values, or keys', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');
    // Force a short name that is a substring of other words in the payload.
    $target->forceFill(['name' => 'Ali'])->save();

    DB::table('admin_audit_logs')->insert([
        'actor_id' => $admin->id,
        'action' => 'user.updated',
        'subject_type' => 'App\\Models\\User',
        'subject_id' => $target->id,
        'changes' => json_encode([
            'Ali' => 'this_key_should_stay',  // key — must NOT change
            'name' => 'Ali',                    // exact value — MUST be replaced
            'other' => 'Alice',                  // substring match — must NOT change
            'third' => 'validation',             // contains 'ali' — must NOT change
            'unrelated' => 'some other value',
        ]),
        'created_at' => now(),
    ]);

    $this->actingAs($admin);
    app(AnonymizeUser::class)($target);

    $row = DB::table('admin_audit_logs')
        ->where('action', 'user.updated')
        ->where('subject_id', $target->id)
        ->first();
    $c = json_decode($row->changes, true);

    expect($c['name'])->toBe("Deleted user #{$target->id}");   // replaced
    expect($c['other'])->toBe('Alice');                         // untouched (substring)
    expect($c['third'])->toBe('validation');                    // untouched (substring)
    expect($c['unrelated'])->toBe('some other value');          // untouched
    expect(array_key_exists('Ali', $c))->toBeTrue();            // key preserved
    expect($c['Ali'])->toBe('this_key_should_stay');            // key's value untouched
});

test('scrub handles name containing double quote or apostrophe', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');
    $target->forceFill(['name' => 'O\'Brien "JD"'])->save();

    DB::table('admin_audit_logs')->insert([
        'actor_id' => $admin->id,
        'action' => 'user.created',
        'subject_type' => 'App\\Models\\User',
        'subject_id' => $target->id,
        'changes' => json_encode(['name' => 'O\'Brien "JD"', 'roles' => ['Cashier']]),
        'created_at' => now(),
    ]);

    $this->actingAs($admin);
    app(AnonymizeUser::class)($target);

    $row = DB::table('admin_audit_logs')
        ->where('action', 'user.created')
        ->where('subject_id', $target->id)
        ->first();
    $c = json_decode($row->changes, true);

    expect($c['name'])->toBe("Deleted user #{$target->id}");
    expect($c['roles'])->toBe(['Cashier']); // other values intact
});

test('scrub matches email case-insensitively', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');
    $originalEmail = $target->email; // e.g. "user@example.com" (always lowercase in DB)

    // Simulate an audit row that stored the email with different casing.
    DB::table('admin_audit_logs')->insert([
        'actor_id' => $admin->id,
        'action' => 'user.created',
        'subject_type' => 'App\\Models\\User',
        'subject_id' => $target->id,
        'changes' => json_encode(['email' => strtoupper($originalEmail)]),
        'created_at' => now(),
    ]);

    $this->actingAs($admin);
    app(AnonymizeUser::class)($target);

    $row = DB::table('admin_audit_logs')
        ->where('action', 'user.created')
        ->where('subject_id', $target->id)
        ->first();
    $c = json_decode($row->changes, true);

    expect($c['email'])->toBe("deleted-{$target->id}@deleted.invalid");
});

test('anonymized user cannot log in', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');

    DB::table('admin_audit_logs')->insert([
        'actor_id' => $target->id, 'action' => 'some.action',
        'subject_type' => 'App\\Models\\User', 'subject_id' => $target->id,
        'created_at' => now(),
    ]);

    $this->actingAs($admin);
    app(AnonymizeUser::class)($target);

    // Log out the admin so the login attempt runs as a guest.
    auth('web')->logout();

    $response = $this->post('/login', [
        'email' => "deleted-{$target->id}@deleted.invalid",
        'password' => 'anything',
    ]);
    $response->assertSessionHasErrors();
    $this->assertGuest();
});

test('anonymized user cannot be reactivated', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');

    DB::table('admin_audit_logs')->insert([
        'actor_id' => $target->id, 'action' => 'some.action',
        'subject_type' => 'App\\Models\\User', 'subject_id' => $target->id,
        'created_at' => now(),
    ]);

    $this->actingAs($admin);
    app(AnonymizeUser::class)($target);

    expect(fn () => app(ReactivateUser::class)($target->fresh()))
        ->toThrow(ValidationException::class, 'Anonymized users cannot be reactivated.');
});

test('original email is reusable after anonymize', function (): void {
    Mail::fake();

    $admin = adminUser();
    $target = nonAdminUser('Cashier');
    $originalEmail = $target->email;

    DB::table('admin_audit_logs')->insert([
        'actor_id' => $target->id, 'action' => 'some.action',
        'subject_type' => 'App\\Models\\User', 'subject_id' => $target->id,
        'created_at' => now(),
    ]);

    $this->actingAs($admin);
    app(AnonymizeUser::class)($target);

    // Creating a new user with the same email must succeed.
    $result = app(CreateUser::class)('New Person', $originalEmail, ['Cashier']);
    expect($result['user']->email)->toBe($originalEmail);
    expect(User::where('email', $originalEmail)->where('anonymized_at', null)->exists())->toBeTrue();
});

test('anonymize refused when actor is the target (self-delete)', function (): void {
    $admin = adminUser();
    $this->actingAs($admin);

    expect(fn () => app(AnonymizeUser::class)($admin))->toThrow(AuthorizationException::class);
});

test('anonymize refused when target is the last active Admin', function (): void {
    $admin = adminUser();
    $actor = User::factory()->create(['is_active' => true]);
    Permission::findOrCreate('users.delete', 'web');
    $actor->givePermissionTo('users.delete');
    $this->actingAs($actor);

    expect(fn () => app(AnonymizeUser::class)($admin))->toThrow(ValidationException::class);
});

test('anonymize refused when target has an open POS session', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');

    $locationId = DB::table('locations')->insertGetId([
        'name' => 'POS Loc Anon', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('pos_sessions')->insert([
        'user_id' => $target->id, 'location_id' => $locationId, 'status' => 'open',
        'opened_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($admin);

    expect(fn () => app(AnonymizeUser::class)($target))
        ->toThrow(ValidationException::class, 'This user has an open POS session. Close it before deleting.');
    expect(DB::table('users')->where('id', $target->id)->value('anonymized_at'))->toBeNull();
});

test('non-admin without users.delete permission cannot anonymize', function (): void {
    app(EnsureSystemRoles::class)();
    $actor = User::factory()->create(['is_active' => true]);
    $actor->assignRole('Cashier'); // No users.delete permission.
    $target = nonAdminUser('Cashier');

    DB::table('admin_audit_logs')->insert([
        'actor_id' => $target->id, 'action' => 'some.action',
        'subject_type' => 'App\\Models\\User', 'subject_id' => $target->id,
        'created_at' => now(),
    ]);

    $this->actingAs($actor);

    expect(fn () => app(AnonymizeUser::class)($target))->toThrow(AuthorizationException::class);
});

// ─── AnonymizeUser via Livewire component ─────────────────────────────────────

test('anonymize via Livewire requires email confirmation', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');

    DB::table('admin_audit_logs')->insert([
        'actor_id' => $target->id, 'action' => 'some.action',
        'subject_type' => 'App\\Models\\User', 'subject_id' => $target->id,
        'created_at' => now(),
    ]);

    $this->actingAs($admin);

    Livewire::test(UsersIndex::class)
        ->call('confirmDelete', $target->id)
        ->set('confirmEmail', 'wrong@email.com')
        ->call('anonymize')
        ->assertHasErrors(['confirmEmail']);

    expect(DB::table('users')->where('id', $target->id)->value('anonymized_at'))->toBeNull();
});

test('anonymize via Livewire succeeds with correct email confirmation', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');
    $targetEmail = $target->email;

    DB::table('admin_audit_logs')->insert([
        'actor_id' => $target->id, 'action' => 'some.action',
        'subject_type' => 'App\\Models\\User', 'subject_id' => $target->id,
        'created_at' => now(),
    ]);

    $this->actingAs($admin);

    Livewire::test(UsersIndex::class)
        ->call('confirmDelete', $target->id)
        ->set('confirmEmail', $targetEmail)
        ->call('anonymize')
        ->assertHasNoErrors();

    expect(DB::table('users')->where('id', $target->id)->value('anonymized_at'))->not->toBeNull();
});

test('hard delete via Livewire works for user with no activity', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');

    $this->actingAs($admin);

    Livewire::test(UsersIndex::class)
        ->call('confirmDelete', $target->id)
        ->call('delete')
        ->assertHasNoErrors();

    expect(User::find($target->id))->toBeNull();
});

test('anonymized users are hidden from active and all filters but visible in deleted filter', function (): void {
    $admin = adminUser();
    $target = nonAdminUser('Cashier');
    $targetId = $target->id;

    DB::table('admin_audit_logs')->insert([
        'actor_id' => $target->id, 'action' => 'some.action',
        'subject_type' => 'App\\Models\\User', 'subject_id' => $target->id,
        'created_at' => now(),
    ]);

    $this->actingAs($admin);
    app(AnonymizeUser::class)($target);

    // Hidden from 'active' filter.
    Livewire::test(UsersIndex::class)
        ->set('statusFilter', 'active')
        ->assertDontSee("Deleted user #{$targetId}");

    // Hidden from 'all' filter.
    Livewire::test(UsersIndex::class)
        ->set('statusFilter', 'all')
        ->assertDontSee("Deleted user #{$targetId}");

    // Visible in 'deleted' filter.
    Livewire::test(UsersIndex::class)
        ->set('statusFilter', 'deleted')
        ->assertSee("Deleted user #{$targetId}");
});
