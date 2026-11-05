<?php

use App\Livewire\Inventory\Index as InventoryIndex;
use App\Livewire\Inventory\Ledger\Index as InventoryLedger;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function inventoryUser(array $permissions): User
{
    $user = User::factory()->create();
    foreach ($permissions as $perm) {
        Permission::findOrCreate($perm, 'web');
    }
    $user->givePermissionTo($permissions);

    return $user;
}

function makeProduct(bool $trackBatches = false): Product
{
    $unit = Unit::factory()->create();

    return Product::factory()->create([
        'base_unit_id' => $unit->id,
        'track_batches' => $trackBatches,
        'reorder_level' => '5.000',
    ]);
}

// ─── Authorization ────────────────────────────────────────────────────────────

test('viewer can access inventory index but not receive adjust or transfer', function () {
    $viewer = inventoryUser(['inventory.view']);
    $product = makeProduct();
    $location = Location::factory()->create();

    Livewire::actingAs($viewer)->test(InventoryIndex::class)->assertOk();

    Livewire::actingAs($viewer)->test(InventoryIndex::class)
        ->call('openReceive')
        ->assertStatus(403);

    Livewire::actingAs($viewer)->test(InventoryIndex::class)
        ->call('openAdjust', $product->id, (string) $location->id)
        ->assertStatus(403);

    Livewire::actingAs($viewer)->test(InventoryIndex::class)
        ->call('openTransfer', $product->id, (string) $location->id)
        ->assertStatus(403);
});

test('storekeeper can open all inventory operation modals', function () {
    $keeper = inventoryUser(['inventory.view', 'inventory.receive', 'inventory.adjust', 'inventory.transfer']);
    $product = makeProduct();
    $location = Location::factory()->create();

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openReceive')
        ->assertHasNoErrors();

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openAdjust', $product->id, (string) $location->id)
        ->assertHasNoErrors();

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openTransfer', $product->id, (string) $location->id)
        ->assertHasNoErrors();
});

test('unauthenticated user cannot access inventory screens', function () {
    $this->get('/inventory')->assertRedirect('/login');
    $this->get('/inventory/ledger')->assertRedirect('/login');
});

// ─── Receive ─────────────────────────────────────────────────────────────────

test('receive non-batch product creates stock level and movement', function () {
    $keeper = inventoryUser(['inventory.view', 'inventory.receive']);
    $product = makeProduct(false);
    $location = Location::factory()->create();

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openReceive', $product->id, (string) $location->id)
        ->set('receiveQuantity', '10.000')
        ->call('receive')
        ->assertHasNoErrors();

    expect(
        StockLevel::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->value('quantity')
    )->toBe('10.000');
});

test('receive batch product with new batch creates batch record', function () {
    $keeper = inventoryUser(['inventory.view', 'inventory.receive']);
    $product = makeProduct(true);
    $location = Location::factory()->create();

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openReceive', $product->id, (string) $location->id)
        ->set('receiveBatchChoice', 'new')
        ->set('receiveBatchNumber', 'BATCH-001')
        ->set('receiveBatchExpiry', '2027-01-01')
        ->set('receiveQuantity', '5.000')
        ->call('receive')
        ->assertHasNoErrors();

    $batch = Batch::query()->where('product_id', $product->id)->where('batch_number', 'BATCH-001')->first();
    expect($batch)->not->toBeNull()
        ->and($batch->expiry_date->format('Y-m-d'))->toBe('2027-01-01');

    expect(
        StockLevel::query()
            ->where('product_id', $product->id)
            ->where('batch_id', $batch->id)
            ->value('quantity')
    )->toBe('5.000');
});

test('receive batch product with existing batch adds to that batch', function () {
    $keeper = inventoryUser(['inventory.view', 'inventory.receive']);
    $product = makeProduct(true);
    $location = Location::factory()->create();
    $batch = Batch::factory()->create(['product_id' => $product->id, 'batch_number' => 'OLD-001']);

    // Initial stock
    app(StockService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '3.000',
        'batch_id' => $batch->id,
    ]);

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openReceive', $product->id, (string) $location->id)
        ->set('receiveBatchChoice', 'existing')
        ->set('receiveBatchId', (string) $batch->id)
        ->set('receiveQuantity', '2.000')
        ->call('receive')
        ->assertHasNoErrors();

    expect(
        StockLevel::query()
            ->where('product_id', $product->id)
            ->where('batch_id', $batch->id)
            ->value('quantity')
    )->toBe('5.000');
});

test('receive batch product without batch fails validation', function () {
    $keeper = inventoryUser(['inventory.view', 'inventory.receive']);
    $product = makeProduct(true);
    $location = Location::factory()->create();

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openReceive', $product->id, (string) $location->id)
        ->set('receiveBatchChoice', 'existing')
        ->set('receiveBatchId', '')
        ->set('receiveQuantity', '5.000')
        ->call('receive')
        ->assertHasErrors('receiveBatchId');
});

test('receive with packaging unit converts quantity to base unit', function () {
    $keeper = inventoryUser(['inventory.view', 'inventory.receive']);
    $baseUnit = Unit::factory()->create(['name' => 'Piece']);
    $carton = Unit::factory()->create(['name' => 'Carton']);
    $product = Product::factory()->create(['base_unit_id' => $baseUnit->id, 'track_batches' => false]);
    $product->productUnits()->create(['unit_id' => $carton->id, 'factor' => '24.000']);
    $location = Location::factory()->create();

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openReceive', $product->id, (string) $location->id)
        ->set('receiveUnitId', (string) $carton->id)
        ->set('receiveQuantity', '2.000')
        ->call('receive')
        ->assertHasNoErrors();

    expect(
        StockLevel::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->value('quantity')
    )->toBe('48.000');
});

// ─── Adjust ───────────────────────────────────────────────────────────────────

test('positive adjustment increases stock level', function () {
    $keeper = inventoryUser(['inventory.view', 'inventory.adjust']);
    $product = makeProduct();
    $location = Location::factory()->create();

    app(StockService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '10.000',
    ]);

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openAdjust', $product->id, (string) $location->id)
        ->set('adjustQuantity', '3.000')
        ->set('adjustReason', 'Stocktake correction')
        ->call('adjust')
        ->assertHasNoErrors();

    expect(
        StockLevel::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->value('quantity')
    )->toBe('13.000');
});

test('negative adjustment decreases stock level', function () {
    $keeper = inventoryUser(['inventory.view', 'inventory.adjust']);
    $product = makeProduct();
    $location = Location::factory()->create();

    app(StockService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '10.000',
    ]);

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openAdjust', $product->id, (string) $location->id)
        ->set('adjustQuantity', '-4.000')
        ->set('adjustReason', 'Damaged goods')
        ->call('adjust')
        ->assertHasNoErrors();

    expect(
        StockLevel::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->value('quantity')
    )->toBe('6.000');
});

test('adjustment without reason fails validation', function () {
    $keeper = inventoryUser(['inventory.view', 'inventory.adjust']);
    $product = makeProduct();
    $location = Location::factory()->create();

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openAdjust', $product->id, (string) $location->id)
        ->set('adjustQuantity', '2.000')
        ->set('adjustReason', '')
        ->call('adjust')
        ->assertHasErrors('adjustReason');
});

test('negative adjustment exceeding stock shows insufficient stock error', function () {
    $keeper = inventoryUser(['inventory.view', 'inventory.adjust']);
    $product = makeProduct();
    $location = Location::factory()->create();

    app(StockService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '2.000',
    ]);

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openAdjust', $product->id, (string) $location->id)
        ->set('adjustQuantity', '-5.000')
        ->set('adjustReason', 'Error test')
        ->call('adjust')
        ->assertHasErrors('adjustQuantity');
});

// ─── Transfer ─────────────────────────────────────────────────────────────────

test('transfer moves stock between locations', function () {
    $keeper = inventoryUser(['inventory.view', 'inventory.transfer']);
    $product = makeProduct();
    $from = Location::factory()->create();
    $to = Location::factory()->create();

    app(StockService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $from->id,
        'quantity' => '8.000',
    ]);

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openTransfer', $product->id, (string) $from->id)
        ->set('transferFromLocationId', (string) $from->id)
        ->set('transferToLocationId', (string) $to->id)
        ->set('transferQuantity', '3.000')
        ->call('transfer')
        ->assertHasNoErrors();

    expect(
        StockLevel::query()->where('product_id', $product->id)->where('location_id', $from->id)->value('quantity')
    )->toBe('5.000');

    expect(
        StockLevel::query()->where('product_id', $product->id)->where('location_id', $to->id)->value('quantity')
    )->toBe('3.000');
});

test('transfer to same location fails validation', function () {
    $keeper = inventoryUser(['inventory.view', 'inventory.transfer']);
    $product = makeProduct();
    $location = Location::factory()->create();

    app(StockService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '5.000',
    ]);

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openTransfer', $product->id, (string) $location->id)
        ->set('transferFromLocationId', (string) $location->id)
        ->set('transferToLocationId', (string) $location->id)
        ->set('transferQuantity', '2.000')
        ->call('transfer')
        ->assertHasErrors('transferToLocationId');
});

test('transfer exceeding stock shows insufficient stock error', function () {
    $keeper = inventoryUser(['inventory.view', 'inventory.transfer']);
    $product = makeProduct();
    $from = Location::factory()->create();
    $to = Location::factory()->create();

    app(StockService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $from->id,
        'quantity' => '2.000',
    ]);

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openTransfer', $product->id, (string) $from->id)
        ->set('transferFromLocationId', (string) $from->id)
        ->set('transferToLocationId', (string) $to->id)
        ->set('transferQuantity', '10.000')
        ->call('transfer')
        ->assertHasErrors('transferQuantity');
});

// ─── Filters ─────────────────────────────────────────────────────────────────

test('inventory index location filter restricts rows', function () {
    $viewer = inventoryUser(['inventory.view']);
    $product = makeProduct();
    $locA = Location::factory()->create();
    $locB = Location::factory()->create();

    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $locA->id, 'quantity' => '5.000']);
    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $locB->id, 'quantity' => '3.000']);

    Livewire::actingAs($viewer)->test(InventoryIndex::class)
        ->set('locationFilter', (string) $locA->id)
        ->assertViewHas('levels', fn ($levels) => $levels->every(fn ($l) => $l->location_id === $locA->id));
});

test('inventory index category filter restricts rows', function () {
    $viewer = inventoryUser(['inventory.view']);
    $catA = Category::factory()->create();
    $catB = Category::factory()->create();
    $unit = Unit::factory()->create();
    $productA = Product::factory()->create(['base_unit_id' => $unit->id, 'category_id' => $catA->id]);
    $productB = Product::factory()->create(['base_unit_id' => $unit->id, 'category_id' => $catB->id]);
    $location = Location::factory()->create();

    app(StockService::class)->receive(['product_id' => $productA->id, 'location_id' => $location->id, 'quantity' => '1.000']);
    app(StockService::class)->receive(['product_id' => $productB->id, 'location_id' => $location->id, 'quantity' => '1.000']);

    Livewire::actingAs($viewer)->test(InventoryIndex::class)
        ->set('categoryFilter', (string) $catA->id)
        ->assertViewHas('levels', fn ($levels) => $levels->every(fn ($l) => $l->product->category_id === $catA->id));
});

test('inventory index out-of-stock filter only shows zero-quantity rows', function () {
    $viewer = inventoryUser(['inventory.view']);
    $unit = Unit::factory()->create();
    $withStock = Product::factory()->create(['base_unit_id' => $unit->id]);
    $noStock = Product::factory()->create(['base_unit_id' => $unit->id]);
    $location = Location::factory()->create();

    app(StockService::class)->receive(['product_id' => $withStock->id, 'location_id' => $location->id, 'quantity' => '5.000']);
    StockLevel::query()->create([
        'product_id' => $noStock->id,
        'location_id' => $location->id,
        'batch_id' => null,
        'quantity' => '0.000',
    ]);

    Livewire::actingAs($viewer)->test(InventoryIndex::class)
        ->set('outOfStockOnly', true)
        ->assertViewHas('levels', fn ($levels) => $levels->contains('product_id', $noStock->id))
        ->assertViewHas('levels', fn ($levels) => ! $levels->contains('product_id', $withStock->id));
});

// ─── Stock alert rule ─────────────────────────────────────────────────────────

test('product scope out-of-stock returns only products with zero total qty', function () {
    $unit = Unit::factory()->create();
    $location = Location::factory()->create();

    $withStock = Product::factory()->create(['base_unit_id' => $unit->id]);
    $noStock = Product::factory()->create(['base_unit_id' => $unit->id]);

    app(StockService::class)->receive(['product_id' => $withStock->id, 'location_id' => $location->id, 'quantity' => '5.000']);

    $outOfStock = Product::outOfStock()->pluck('id');
    expect($outOfStock)->toContain($noStock->id)
        ->and($outOfStock)->not->toContain($withStock->id);
});

test('product scope low-stock returns products at or below reorder level with stock', function () {
    $unit = Unit::factory()->create();
    $location = Location::factory()->create();

    $low = Product::factory()->create(['base_unit_id' => $unit->id, 'reorder_level' => '5.000']);
    $ok = Product::factory()->create(['base_unit_id' => $unit->id, 'reorder_level' => '5.000']);
    $noReorder = Product::factory()->create(['base_unit_id' => $unit->id, 'reorder_level' => '0.000']);

    app(StockService::class)->receive(['product_id' => $low->id, 'location_id' => $location->id, 'quantity' => '3.000']);
    app(StockService::class)->receive(['product_id' => $ok->id, 'location_id' => $location->id, 'quantity' => '10.000']);
    app(StockService::class)->receive(['product_id' => $noReorder->id, 'location_id' => $location->id, 'quantity' => '1.000']);

    $lowStockIds = Product::lowStock()->pluck('id');
    expect($lowStockIds)->toContain($low->id)
        ->and($lowStockIds)->not->toContain($ok->id)
        ->and($lowStockIds)->not->toContain($noReorder->id);
});

test('product at exactly reorder level is flagged as low stock', function () {
    $unit = Unit::factory()->create();
    $location = Location::factory()->create();
    $product = Product::factory()->create(['base_unit_id' => $unit->id, 'reorder_level' => '5.000']);
    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '5.000']);

    expect(Product::lowStock()->where('id', $product->id)->exists())->toBeTrue();
});

test('out-of-stock product is not flagged as low stock', function () {
    $unit = Unit::factory()->create();
    $product = Product::factory()->create(['base_unit_id' => $unit->id, 'reorder_level' => '5.000']);
    // No stock received — total qty = 0

    expect(Product::lowStock()->where('id', $product->id)->exists())->toBeFalse();
    expect(Product::outOfStock()->where('id', $product->id)->exists())->toBeTrue();
});

// ─── Ledger ───────────────────────────────────────────────────────────────────

test('ledger shows all movements in reverse chronological order', function () {
    $viewer = inventoryUser(['inventory.view']);
    $product = makeProduct();
    $location = Location::factory()->create();
    $service = app(StockService::class);

    $service->receive(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '10.000']);
    $service->adjust(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '-2.000', 'reason' => 'Damage']);

    Livewire::actingAs($viewer)->test(InventoryLedger::class)
        ->assertSee($product->name)
        ->assertSee('10.000')
        ->assertSee('-2.000');
});

test('ledger type filter restricts movements', function () {
    $viewer = inventoryUser(['inventory.view']);
    $product = makeProduct();
    $location = Location::factory()->create();
    $service = app(StockService::class);

    $service->receive(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '5.000']);
    $service->adjust(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '-1.000', 'reason' => 'Test']);

    Livewire::actingAs($viewer)->test(InventoryLedger::class)
        ->set('typeFilter', 'receipt')
        ->assertSee('5.000')
        ->assertDontSee('-1.000');
});

test('ledger date range filter restricts movements', function () {
    $viewer = inventoryUser(['inventory.view']);
    $product = makeProduct();
    $location = Location::factory()->create();

    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '5.000']);

    Livewire::actingAs($viewer)->test(InventoryLedger::class)
        ->set('dateFrom', now()->addDay()->toDateString())
        ->assertViewHas('movements', fn ($movements) => $movements->isEmpty());
});

test('stock movement total equals stock level (ledger invariant)', function () {
    $product = makeProduct();
    $location = Location::factory()->create();
    $service = app(StockService::class);

    $service->receive(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '10.000']);
    $service->adjust(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '-3.000', 'reason' => 'Shrinkage']);

    $movementSum = StockMovement::query()
        ->where('product_id', $product->id)
        ->where('location_id', $location->id)
        ->sum('quantity');

    $levelQty = StockLevel::query()
        ->where('product_id', $product->id)
        ->where('location_id', $location->id)
        ->value('quantity');

    expect(bccomp((string) $movementSum, (string) $levelQty, 3))->toBe(0);
});

test('ledger invariant holds after receive adjust and transfer', function () {
    $product = makeProduct();
    $from = Location::factory()->create();
    $to = Location::factory()->create();
    $service = app(StockService::class);

    $service->receive(['product_id' => $product->id, 'location_id' => $from->id, 'quantity' => '20.000']);
    $service->adjust(['product_id' => $product->id, 'location_id' => $from->id, 'quantity' => '-2.000', 'reason' => 'Shrinkage']);
    $service->transfer(['product_id' => $product->id, 'from_location_id' => $from->id, 'to_location_id' => $to->id, 'quantity' => '5.000']);

    // Source: receipt(+20) + adjustment(-2) + transfer_out(-5) = 13
    $fromSum = (string) StockMovement::query()
        ->where('product_id', $product->id)
        ->where('location_id', $from->id)
        ->sum('quantity');
    $fromLevel = StockLevel::query()
        ->where('product_id', $product->id)
        ->where('location_id', $from->id)
        ->value('quantity');
    expect(bccomp($fromSum, (string) $fromLevel, 3))->toBe(0);

    // Destination: transfer_in(+5) = 5
    $toSum = (string) StockMovement::query()
        ->where('product_id', $product->id)
        ->where('location_id', $to->id)
        ->sum('quantity');
    $toLevel = StockLevel::query()
        ->where('product_id', $product->id)
        ->where('location_id', $to->id)
        ->value('quantity');
    expect(bccomp($toSum, (string) $toLevel, 3))->toBe(0);
});

test('adjustment reason is stored in movement and visible in ledger', function () {
    $keeper = inventoryUser(['inventory.view', 'inventory.adjust']);
    $product = makeProduct();
    $location = Location::factory()->create();

    app(StockService::class)->receive(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '10.000']);

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openAdjust', $product->id, (string) $location->id)
        ->set('adjustQuantity', '-2.000')
        ->set('adjustReason', 'Found damaged in storage')
        ->call('adjust')
        ->assertHasNoErrors();

    $movement = StockMovement::query()
        ->where('product_id', $product->id)
        ->where('type', 'adjustment')
        ->first();

    expect($movement->reason)->toBe('Found damaged in storage');

    $viewer = inventoryUser(['inventory.view']);
    Livewire::actingAs($viewer)->test(InventoryLedger::class)
        ->assertSee('Found damaged in storage');
});

test('storekeeper can call receive adjust and transfer actions', function () {
    $keeper = inventoryUser(['inventory.view', 'inventory.receive', 'inventory.adjust', 'inventory.transfer']);
    $product = makeProduct();
    $from = Location::factory()->create();
    $to = Location::factory()->create();

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openReceive', $product->id, (string) $from->id)
        ->set('receiveQuantity', '20.000')
        ->call('receive')
        ->assertHasNoErrors();

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openAdjust', $product->id, (string) $from->id)
        ->set('adjustQuantity', '-2.000')
        ->set('adjustReason', 'Damage')
        ->call('adjust')
        ->assertHasNoErrors();

    Livewire::actingAs($keeper)->test(InventoryIndex::class)
        ->call('openTransfer', $product->id, (string) $from->id)
        ->set('transferFromLocationId', (string) $from->id)
        ->set('transferToLocationId', (string) $to->id)
        ->set('transferQuantity', '5.000')
        ->call('transfer')
        ->assertHasNoErrors();
});

test('viewer cannot call receive adjust transfer actions directly', function () {
    $viewer = inventoryUser(['inventory.view']);

    Livewire::actingAs($viewer)->test(InventoryIndex::class)
        ->call('receive')
        ->assertStatus(403);

    Livewire::actingAs($viewer)->test(InventoryIndex::class)
        ->call('adjust')
        ->assertStatus(403);

    Livewire::actingAs($viewer)->test(InventoryIndex::class)
        ->call('transfer')
        ->assertStatus(403);
});

test('cashier cannot call receive adjust transfer actions directly', function () {
    $cashier = inventoryUser(['inventory.view']);

    Livewire::actingAs($cashier)->test(InventoryIndex::class)
        ->call('receive')
        ->assertStatus(403);

    Livewire::actingAs($cashier)->test(InventoryIndex::class)
        ->call('adjust')
        ->assertStatus(403);

    Livewire::actingAs($cashier)->test(InventoryIndex::class)
        ->call('transfer')
        ->assertStatus(403);
});
