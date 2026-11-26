<?php

use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use App\Livewire\Actions\Sales\ClosePosSession;
use App\Livewire\Actions\Sales\CompletePosSale;
use App\Livewire\Actions\Sales\HoldSale;
use App\Livewire\Actions\Sales\OpenPosSession;
use App\Livewire\Actions\Sales\ResumeSale;
use App\Livewire\Reports\PosSessions\Index as PosSessionsIndex;
use App\Livewire\Sales\Pos\Index as PosIndex;
use App\Models\Location;
use App\Models\PosHeldSale;
use App\Models\PosSession;
use App\Models\User;
use App\Services\Access\LocationAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function posAccessUser(array $permissions, array $locationIds = []): User
{
    $user = User::factory()->create();

    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }
    if (! empty($permissions)) {
        $user->givePermissionTo($permissions);
    }

    if (! empty($locationIds)) {
        $user->locations()->sync($locationIds);
    }

    return $user;
}

function posAccessAdmin(array $permissions = []): User
{
    app(EnsureSystemRoles::class)();
    $user = User::factory()->create();
    $user->assignRole('Admin');

    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }
    if (! empty($permissions)) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function posAccessLocation(): Location
{
    return Location::forceCreate([
        'name' => 'PosAcc-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'type' => 'store',
        'is_active' => true,
    ]);
}

function posAccessSession(User $user, Location $location): PosSession
{
    return PosSession::forceCreate([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'opened_at' => now(),
        'opening_cash' => '0.00',
        'status' => 'open',
    ]);
}

function posAccessHeldSale(User $user, Location $location): PosHeldSale
{
    return PosHeldSale::forceCreate([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'cart' => [],
        'label' => '',
    ]);
}

// ─── 1. Open session at allowed location works ────────────────────────────────

test('open_session_at_allowed_location_works', function (): void {
    $locA = posAccessLocation();
    $user = posAccessUser(['pos.open_session'], [$locA->id]);
    $this->actingAs($user);

    $session = app(OpenPosSession::class)(['location_id' => $locA->id]);

    expect($session->location_id)->toBe($locA->id)
        ->and($session->status)->toBe('open');
});

// ─── 2. Open session at forbidden location refused ────────────────────────────

test('open_session_at_forbidden_location_refused', function (): void {
    $locA = posAccessLocation();
    $locB = posAccessLocation();
    $user = posAccessUser(['pos.open_session'], [$locA->id]);
    $this->actingAs($user);

    expect(fn () => app(OpenPosSession::class)(['location_id' => $locB->id]))
        ->toThrow(AuthorizationException::class);
});

// ─── 3. Multi-location user without selection sees error in POS component ─────

test('multi_location_user_without_selection_sees_error_in_pos_component', function (): void {
    $locA = posAccessLocation();
    $locB = posAccessLocation();
    $user = posAccessUser(['pos.use', 'pos.open_session'], [$locA->id, $locB->id]);

    Livewire::actingAs($user)
        ->test(PosIndex::class)
        ->set('openLocationId', 0)
        ->call('openSession')
        ->assertHasErrors('session');
});

// ─── 4. CompletePosSale refused for forbidden session location ────────────────

test('complete_sale_at_forbidden_session_location_refused', function (): void {
    $locA = posAccessLocation();
    $locB = posAccessLocation();
    $user = posAccessUser(['pos.use', 'invoices.issue', 'invoices.create', 'payments.create'], [$locA->id]);
    $this->actingAs($user);

    // Session belongs to another user at locB; user only has access to locA.
    $other = posAccessUser([], [$locB->id]);
    $sessionB = posAccessSession($other, $locB);

    expect(fn () => app(CompletePosSale::class)(
        items: [['product_id' => 999, 'quantity' => '1.000', 'unit_price' => '100.00', 'discount_amount' => '0.00']],
        customerId: 1,
        tenders: [['method' => 'cash', 'amount' => '100.00']],
        sessionId: $sessionB->id,
        idempotencyKey: Str::uuid()->toString(),
    ))->toThrow(AuthorizationException::class);
});

// ─── 5. HoldSale refused for forbidden location ───────────────────────────────

test('hold_sale_at_forbidden_location_refused', function (): void {
    $locA = posAccessLocation();
    $locB = posAccessLocation();
    $user = posAccessUser(['pos.use'], [$locA->id]);
    $this->actingAs($user);

    expect(fn () => app(HoldSale::class)([
        'location_id' => $locB->id,
        'cart' => [],
        'label' => '',
    ]))->toThrow(AuthorizationException::class);
});

// ─── 6. ResumeSale refused for held sale at forbidden location ────────────────

test('resume_held_sale_at_forbidden_location_refused', function (): void {
    $locA = posAccessLocation();
    $locB = posAccessLocation();
    $user = posAccessUser(['pos.use'], [$locA->id]);
    $this->actingAs($user);

    // The held sale is at locB (created by this user, e.g. from a previous session there).
    $held = posAccessHeldSale($user, $locB);

    expect(fn () => app(ResumeSale::class)($held->id))
        ->toThrow(AuthorizationException::class);
});

// ─── 7. close_any_session refused for forbidden location ─────────────────────

test('close_any_session_refused_for_forbidden_location', function (): void {
    $locA = posAccessLocation();
    $locB = posAccessLocation();
    $manager = posAccessUser(['pos.close_any_session'], [$locA->id]);
    $this->actingAs($manager);

    $other = posAccessUser([], [$locB->id]);
    $sessionB = posAccessSession($other, $locB);

    expect(fn () => app(ClosePosSession::class)($sessionB, ['closing_cash_counted' => '0.00']))
        ->toThrow(AuthorizationException::class);
});

// ─── 8. close_any_session works for allowed location ─────────────────────────

test('close_any_session_works_for_allowed_location', function (): void {
    $locA = posAccessLocation();
    $manager = posAccessUser(['pos.close_any_session'], [$locA->id]);
    $this->actingAs($manager);

    $other = posAccessUser([], [$locA->id]);
    $sessionA = posAccessSession($other, $locA);

    $closed = app(ClosePosSession::class)($sessionA, ['closing_cash_counted' => '0.00']);

    expect($closed->status)->toBe('closed');
});

// ─── 9. Admin can open session at any location (unrestricted) ─────────────────

test('admin_can_open_session_at_any_location', function (): void {
    $locB = posAccessLocation();
    $admin = posAccessAdmin(['pos.open_session']);
    $this->actingAs($admin);

    $session = app(OpenPosSession::class)(['location_id' => $locB->id]);

    expect($session->location_id)->toBe($locB->id);
});

// ─── 10. Admin can close any session at any location ─────────────────────────

test('admin_can_close_any_session_at_any_location', function (): void {
    $locB = posAccessLocation();
    $admin = posAccessAdmin(['pos.close_any_session']);
    $this->actingAs($admin);

    $other = posAccessUser([], [$locB->id]);
    $sessionB = posAccessSession($other, $locB);

    $closed = app(ClosePosSession::class)($sessionB, ['closing_cash_counted' => '0.00']);

    expect($closed->status)->toBe('closed');
});

// ─── 11. POS sessions report shows only allowed locations ─────────────────────

test('pos_sessions_report_shows_only_allowed_locations', function (): void {
    $locA = posAccessLocation();
    $locB = posAccessLocation();

    $ownerA = posAccessUser([], [$locA->id]);
    $ownerB = posAccessUser([], [$locB->id]);
    posAccessSession($ownerA, $locA);
    posAccessSession($ownerB, $locB);

    $viewer = posAccessUser(['reports.view'], [$locA->id]);

    Livewire::actingAs($viewer)
        ->test(PosSessionsIndex::class)
        ->assertSee($locA->name)
        ->assertDontSee($locB->name);
});

// ─── 12. Admin sees all sessions in the report ────────────────────────────────

test('admin_sees_all_sessions_in_pos_sessions_report', function (): void {
    $locA = posAccessLocation();
    $locB = posAccessLocation();

    $ownerA = posAccessUser([], [$locA->id]);
    $ownerB = posAccessUser([], [$locB->id]);
    posAccessSession($ownerA, $locA);
    posAccessSession($ownerB, $locB);

    $admin = posAccessAdmin(['reports.view']);

    Livewire::actingAs($admin)
        ->test(PosSessionsIndex::class)
        ->assertSee($locA->name)
        ->assertSee($locB->name);
});

// ─── 13. Z-report PDF for specific forbidden location returns 403 ─────────────

test('pos_sessions_pdf_for_forbidden_location_returns_403', function (): void {
    $locA = posAccessLocation();
    $locB = posAccessLocation();
    $user = posAccessUser(['reports.view'], [$locA->id]);

    $this->actingAs($user)
        ->get(route('reports.pdf', ['report' => 'pos-sessions', 'locationId' => $locB->id]))
        ->assertForbidden();
});

// ─── 14. User losing location access can still close their own session ─────────

test('user_losing_location_access_can_still_close_own_session', function (): void {
    $locA = posAccessLocation();
    $user = posAccessUser(['pos.close_session'], [$locA->id]);
    $this->actingAs($user);

    $session = posAccessSession($user, $locA);

    // Simulate losing location access after the session was opened.
    $user->locations()->detach($locA->id);
    app(LocationAccess::class)->flush($user->id);

    // Own session: no ensure() check — user may still close it (requirement 6).
    $closed = app(ClosePosSession::class)($session, ['closing_cash_counted' => '0.00']);

    expect($closed->status)->toBe('closed');
});

// ─── 15. User losing location access cannot complete a sale ───────────────────

test('user_losing_location_access_cannot_complete_sale', function (): void {
    $locA = posAccessLocation();
    $user = posAccessUser(['pos.use', 'invoices.issue', 'invoices.create', 'payments.create'], [$locA->id]);
    $this->actingAs($user);

    $session = posAccessSession($user, $locA);

    // Simulate losing location access mid-session.
    $user->locations()->detach($locA->id);
    app(LocationAccess::class)->flush($user->id);

    expect(fn () => app(CompletePosSale::class)(
        items: [['product_id' => 999, 'quantity' => '1.000', 'unit_price' => '100.00', 'discount_amount' => '0.00']],
        customerId: 1,
        tenders: [['method' => 'cash', 'amount' => '100.00']],
        sessionId: $session->id,
        idempotencyKey: Str::uuid()->toString(),
    ))->toThrow(AuthorizationException::class);
});
