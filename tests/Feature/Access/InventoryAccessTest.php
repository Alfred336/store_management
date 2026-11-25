<?php

use App\Livewire\Inventory\Index as InventoryIndex;
use App\Livewire\Inventory\Ledger\Index as InventoryLedger;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Unit;
use App\Models\User;
use App\Services\Access\LocationAccess;
use App\Services\StockService;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function invAccessUser(array $permissions, array $locationIds = []): User
{
    $user = User::factory()->create();

    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }

    $user->givePermissionTo($permissions);

    if (! empty($locationIds)) {
        $user->locations()->sync($locationIds);
    }

    return $user;
}

function invAccessProduct(): Product
{
    $unit = Unit::factory()->create();

    return Product::factory()->create(['base_unit_id' => $unit->id, 'track_batches' => false]);
}

// ─── Inventory index: location scoping ────────────────────────────────────────

test('stock levels from allowed locations are visible to restricted user', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);
    $product = invAccessProduct();

    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $locA->id, 'quantity' => '10.000']);
    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $locB->id, 'quantity' => '5.000']);

    $user = invAccessUser(['inventory.view'], [$locA->id]);

    Livewire::actingAs($user)->test(InventoryIndex::class)
        ->assertViewHas('levels', fn ($levels) => $levels->contains('location_id', $locA->id))
        ->assertViewHas('levels', fn ($levels) => ! $levels->contains('location_id', $locB->id));
});

test('Admin role user with no location assignments sees stock from all locations', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);
    $product = invAccessProduct();

    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $locA->id, 'quantity' => '10.000']);
    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $locB->id, 'quantity' => '5.000']);

    // Admin role bypasses location restriction — allowedLocationIds() returns null
    Permission::findOrCreate('inventory.view', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $admin->givePermissionTo('inventory.view');
    // Intentionally no location assignments

    Livewire::actingAs($admin)->test(InventoryIndex::class)
        ->assertViewHas('levels', fn ($levels) => $levels->contains('location_id', $locA->id))
        ->assertViewHas('levels', fn ($levels) => $levels->contains('location_id', $locB->id));
});

test('user with no assigned locations sees no stock levels', function (): void {
    $loc = Location::factory()->create(['is_active' => true]);
    $product = invAccessProduct();

    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $loc->id, 'quantity' => '10.000']);

    $user = invAccessUser(['inventory.view']); // no locations

    Livewire::actingAs($user)->test(InventoryIndex::class)
        ->assertViewHas('levels', fn ($levels) => $levels->isEmpty());
});

// ─── Receive: location access guard ───────────────────────────────────────────

test('receive is blocked when target location is not in allowed set', function (): void {
    $allowed = Location::factory()->create(['is_active' => true]);
    $other = Location::factory()->create(['is_active' => true]);
    $product = invAccessProduct();

    $user = invAccessUser(['inventory.view', 'inventory.receive'], [$allowed->id]);

    Livewire::actingAs($user)->test(InventoryIndex::class)
        ->call('openReceive', $product->id, (string) $other->id)
        ->set('receiveQuantity', '5.000')
        ->call('receive')
        ->assertStatus(403);
});

test('Admin role user with no location assignments can receive at any location', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);
    $product = invAccessProduct();

    Permission::findOrCreate('inventory.receive', 'web');
    Permission::findOrCreate('inventory.view', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $admin->givePermissionTo(['inventory.view', 'inventory.receive']);
    // Intentionally no location assignments

    Livewire::actingAs($admin)->test(InventoryIndex::class)
        ->call('openReceive', $product->id, (string) $locA->id)
        ->set('receiveQuantity', '8.000')
        ->call('receive')
        ->assertHasNoErrors();

    Livewire::actingAs($admin)->test(InventoryIndex::class)
        ->call('openReceive', $product->id, (string) $locB->id)
        ->set('receiveQuantity', '6.000')
        ->call('receive')
        ->assertHasNoErrors();

    expect(
        StockLevel::query()->where('product_id', $product->id)->where('location_id', $locA->id)->value('quantity')
    )->toBe('8.000');

    expect(
        StockLevel::query()->where('product_id', $product->id)->where('location_id', $locB->id)->value('quantity')
    )->toBe('6.000');
});

test('receive succeeds for location in allowed set', function (): void {
    $loc = Location::factory()->create(['is_active' => true]);
    $product = invAccessProduct();

    $user = invAccessUser(['inventory.view', 'inventory.receive'], [$loc->id]);

    Livewire::actingAs($user)->test(InventoryIndex::class)
        ->call('openReceive', $product->id, (string) $loc->id)
        ->set('receiveQuantity', '7.000')
        ->call('receive')
        ->assertHasNoErrors();

    expect(
        StockLevel::query()->where('product_id', $product->id)->where('location_id', $loc->id)->value('quantity')
    )->toBe('7.000');
});

// ─── Adjust: location access guard ────────────────────────────────────────────

test('adjust is blocked when target location is not in allowed set', function (): void {
    $allowed = Location::factory()->create(['is_active' => true]);
    $other = Location::factory()->create(['is_active' => true]);
    $product = invAccessProduct();

    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $other->id, 'quantity' => '10.000']);

    $user = invAccessUser(['inventory.view', 'inventory.adjust'], [$allowed->id]);

    Livewire::actingAs($user)->test(InventoryIndex::class)
        ->call('openAdjust', $product->id, (string) $other->id)
        ->set('adjustQuantity', '2.000')
        ->set('adjustReason', 'Test')
        ->call('adjust')
        ->assertStatus(403);
});

// ─── Transfer: location access guard ──────────────────────────────────────────

test('transfer is blocked when from-location is not in allowed set', function (): void {
    $allowed = Location::factory()->create(['is_active' => true]);
    $other = Location::factory()->create(['is_active' => true]);
    $dest = Location::factory()->create(['is_active' => true]);
    $product = invAccessProduct();

    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $other->id, 'quantity' => '10.000']);

    $user = invAccessUser(['inventory.view', 'inventory.transfer'], [$allowed->id]);

    Livewire::actingAs($user)->test(InventoryIndex::class)
        ->call('openTransfer', $product->id, (string) $other->id)
        ->set('transferFromLocationId', (string) $other->id)
        ->set('transferToLocationId', (string) $dest->id)
        ->set('transferQuantity', '3.000')
        ->call('transfer')
        ->assertStatus(403);
});

test('transfer to inaccessible destination is allowed (only from is restricted)', function (): void {
    $from = Location::factory()->create(['is_active' => true]);
    $to = Location::factory()->create(['is_active' => true]);
    $product = invAccessProduct();

    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $from->id, 'quantity' => '10.000']);

    // User only has access to $from, not $to — transfer should still succeed
    $user = invAccessUser(['inventory.view', 'inventory.transfer'], [$from->id]);

    Livewire::actingAs($user)->test(InventoryIndex::class)
        ->call('openTransfer', $product->id, (string) $from->id)
        ->set('transferFromLocationId', (string) $from->id)
        ->set('transferToLocationId', (string) $to->id)
        ->set('transferQuantity', '4.000')
        ->call('transfer')
        ->assertHasNoErrors();

    expect(
        StockLevel::query()->where('product_id', $product->id)->where('location_id', $to->id)->value('quantity')
    )->toBe('4.000');
});

// ─── Inventory ledger: location scoping ───────────────────────────────────────

test('ledger only shows movements for allowed locations', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);
    $product = invAccessProduct();

    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $locA->id, 'quantity' => '10.000']);
    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $locB->id, 'quantity' => '5.000']);

    $user = invAccessUser(['inventory.view'], [$locA->id]);

    Livewire::actingAs($user)->test(InventoryLedger::class)
        ->assertViewHas('movements', fn ($movements) => $movements->every(fn ($m) => $m->location_id === $locA->id));
});

test('ledger is empty for user with no assigned locations', function (): void {
    $loc = Location::factory()->create(['is_active' => true]);
    $product = invAccessProduct();

    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $loc->id, 'quantity' => '10.000']);

    $user = invAccessUser(['inventory.view']);

    Livewire::actingAs($user)->test(InventoryLedger::class)
        ->assertViewHas('movements', fn ($movements) => $movements->isEmpty());
});

// ─── Locations management: location scoping ───────────────────────────────────

test('locations list dropdown is scoped to allowed locations in inventory forms', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $user = invAccessUser(['inventory.view'], [$locA->id]);

    Livewire::actingAs($user)->test(InventoryIndex::class)
        ->assertViewHas('locations', fn ($locations) => $locations->contains('id', $locA->id))
        ->assertViewHas('locations', fn ($locations) => ! $locations->contains('id', $locB->id));
});

test('all active locations appear in transfer-to dropdown regardless of user access', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $locB = Location::factory()->create(['is_active' => true]);

    $user = invAccessUser(['inventory.view'], [$locA->id]);

    Livewire::actingAs($user)->test(InventoryIndex::class)
        ->assertViewHas('allActiveLocations', fn ($locs) => $locs->contains('id', $locA->id))
        ->assertViewHas('allActiveLocations', fn ($locs) => $locs->contains('id', $locB->id));
});

// ─── Product badge scoping ─────────────────────────────────────────────────────

test('out-of-stock badge is not shown to Rufiji user for product only out of stock at Main Store', function (): void {
    $mainStore = Location::factory()->create(['is_active' => true]);
    $rufiji = Location::factory()->create(['is_active' => true]);
    $product = invAccessProduct();

    // Product has 0 qty at Main Store; Rufiji has stock — not out-of-stock from Rufiji's view
    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $rufiji->id, 'quantity' => '10.000']);
    // No stock received at Main Store → out of stock there

    $rufijiUser = invAccessUser(['inventory.view'], [$rufiji->id]);

    $allowedIds = app(LocationAccess::class)->allowedLocationIds($rufijiUser);
    $outOfStock = Product::query()->whereKey($product->id)->outOfStock($allowedIds)->exists();

    expect($outOfStock)->toBeFalse();
});

test('out-of-stock badge IS shown to Rufiji user when product is out of stock at Rufiji', function (): void {
    $mainStore = Location::factory()->create(['is_active' => true]);
    $rufiji = Location::factory()->create(['is_active' => true]);
    $product = invAccessProduct();

    // Stock only at Main Store; Rufiji has none
    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $mainStore->id, 'quantity' => '10.000']);

    $rufijiUser = invAccessUser(['inventory.view'], [$rufiji->id]);

    $allowedIds = app(LocationAccess::class)->allowedLocationIds($rufijiUser);
    $outOfStock = Product::query()->whereKey($product->id)->outOfStock($allowedIds)->exists();

    expect($outOfStock)->toBeTrue();
});

test('Admin role user sees global out-of-stock badge regardless of location assignments', function (): void {
    $locA = Location::factory()->create(['is_active' => true]);
    $product = invAccessProduct();
    // No stock anywhere — globally out of stock

    Permission::findOrCreate('inventory.view', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $admin->givePermissionTo('inventory.view');

    $allowedIds = app(LocationAccess::class)->allowedLocationIds($admin);
    expect($allowedIds)->toBeNull(); // confirms Admin path

    $outOfStock = Product::query()->whereKey($product->id)->outOfStock($allowedIds)->exists();
    expect($outOfStock)->toBeTrue();
});
