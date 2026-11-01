<?php

use App\Exceptions\InsufficientStockException;
use App\Models\Batch;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\StockService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    $this->baseUnit = Unit::factory()->create(['name' => 'Piece']);
    $this->product = Product::factory()->create(['base_unit_id' => $this->baseUnit->id]);
    $this->location = Location::factory()->create(['name' => 'Main Store']);
    $this->service = app(StockService::class);
});

function expectStockMovementsToMatchLevels(Product $product, Location $location): void
{
    $movementTotal = StockMovement::query()
        ->where('product_id', $product->id)
        ->where('location_id', $location->id)
        ->sum('quantity');

    $stockTotal = StockLevel::query()
        ->where('product_id', $product->id)
        ->where('location_id', $location->id)
        ->sum('quantity');

    expect(bccomp((string) $movementTotal, (string) $stockTotal, 3))->toBe(0);
}

test('receiving and issuing maintain the balance and signed movement ledger', function () {
    $received = $this->service->receive([
        'product_id' => $this->product->id,
        'location_id' => $this->location->id,
        'quantity' => '10.250',
    ]);
    $issued = $this->service->issue([
        'product_id' => $this->product->id,
        'location_id' => $this->location->id,
        'quantity' => '3.125',
        'type' => 'sale',
    ]);

    expect($received->quantity)->toBe('10.250')
        ->and($issued->quantity)->toBe('-3.125')
        ->and($this->product->stockLevels()->firstOrFail()->quantity)->toBe('7.125');

    expectStockMovementsToMatchLevels($this->product, $this->location);
});

test('issuing more than available rolls back without changing stock or movements', function () {
    $this->service->receive([
        'product_id' => $this->product->id,
        'location_id' => $this->location->id,
        'quantity' => '2.000',
    ]);

    expect(fn () => $this->service->issue([
        'product_id' => $this->product->id,
        'location_id' => $this->location->id,
        'quantity' => '3.000',
    ]))->toThrow(InsufficientStockException::class, 'Available quantity: 2.000');

    expect($this->product->stockLevels()->firstOrFail()->quantity)->toBe('2.000')
        ->and(StockMovement::query()->count())->toBe(1);

    expectStockMovementsToMatchLevels($this->product, $this->location);
});

test('unit conversion receives exact base quantities and rejects undefined units', function () {
    $carton = Unit::factory()->create(['name' => 'Carton']);
    ProductUnit::factory()->create([
        'product_id' => $this->product->id,
        'unit_id' => $carton->id,
        'factor' => '24.000',
    ]);

    $movement = $this->service->receive([
        'product_id' => $this->product->id,
        'location_id' => $this->location->id,
        'unit_id' => $carton->id,
        'quantity' => '2.000',
    ]);

    expect($movement->quantity)->toBe('48.000')
        ->and($this->product->stockLevels()->firstOrFail()->quantity)->toBe('48.000');

    $undefinedUnit = Unit::factory()->create(['name' => 'Litre']);

    expect(fn () => $this->service->convertToBase($this->product, $undefinedUnit->id, '1.000'))
        ->toThrow(InvalidArgumentException::class, 'is not defined for product');
});

test('batch-tracked products require a batch and issues affect only the selected batch', function () {
    $product = Product::factory()->create([
        'base_unit_id' => $this->baseUnit->id,
        'track_batches' => true,
    ]);
    $batchA = Batch::factory()->create(['product_id' => $product->id, 'batch_number' => 'A']);
    $batchB = Batch::factory()->create(['product_id' => $product->id, 'batch_number' => 'B']);

    expect(fn () => $this->service->receive([
        'product_id' => $product->id,
        'location_id' => $this->location->id,
        'quantity' => '1.000',
    ]))->toThrow(InvalidArgumentException::class, 'A batch is required');

    foreach ([[$batchA, '5.000'], [$batchB, '8.000']] as [$batch, $quantity]) {
        $this->service->receive([
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'batch_id' => $batch->id,
            'quantity' => $quantity,
        ]);
    }

    $this->service->issue([
        'product_id' => $product->id,
        'location_id' => $this->location->id,
        'batch_id' => $batchA->id,
        'quantity' => '2.000',
    ]);

    expect($product->stockLevels()->where('batch_id', $batchA->id)->value('quantity'))->toBe('3.000')
        ->and($product->stockLevels()->where('batch_id', $batchB->id)->value('quantity'))->toBe('8.000')
        ->and($product->stockLevels()->count())->toBe(2);

    expectStockMovementsToMatchLevels($product, $this->location);
});

test('repeated non-batch receipts use exactly one stock level row', function () {
    foreach (['1.000', '2.500', '0.125', '4.000'] as $quantity) {
        $this->service->receive([
            'product_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity' => $quantity,
        ]);
    }

    expect($this->product->stockLevels()->count())->toBe(1)
        ->and($this->product->stockLevels()->firstOrFail()->quantity)->toBe('7.625');

    expectStockMovementsToMatchLevels($this->product, $this->location);
});

test('two batches for one product can coexist at one location', function () {
    $product = Product::factory()->create([
        'base_unit_id' => $this->baseUnit->id,
        'track_batches' => true,
    ]);
    $batchA = Batch::factory()->create(['product_id' => $product->id, 'batch_number' => 'LOT-A']);
    $batchB = Batch::factory()->create(['product_id' => $product->id, 'batch_number' => 'LOT-B']);

    foreach ([[$batchA, '2.000'], [$batchB, '3.000']] as [$batch, $quantity]) {
        $this->service->receive([
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'batch_id' => $batch->id,
            'quantity' => $quantity,
        ]);
    }

    expect($product->stockLevels()->count())->toBe(2)
        ->and($product->stockLevels()->pluck('batch_id')->sort()->values()->all())->toBe([$batchA->id, $batchB->id]);
});

test('transfers move stock atomically and leave both locations unchanged on failure', function () {
    $destination = Location::factory()->create(['name' => 'Back Room']);

    $this->service->receive([
        'product_id' => $this->product->id,
        'location_id' => $this->location->id,
        'quantity' => '10.000',
    ]);

    [$out, $in] = $this->service->transfer([
        'product_id' => $this->product->id,
        'from_location_id' => $this->location->id,
        'to_location_id' => $destination->id,
        'quantity' => '4.000',
    ]);

    expect($out->type)->toBe('transfer_out')
        ->and($out->quantity)->toBe('-4.000')
        ->and($in->type)->toBe('transfer_in')
        ->and($in->quantity)->toBe('4.000')
        ->and($this->product->stockLevels()->where('location_id', $this->location->id)->value('quantity'))->toBe('6.000')
        ->and($this->product->stockLevels()->where('location_id', $destination->id)->value('quantity'))->toBe('4.000');

    expect(fn () => $this->service->transfer([
        'product_id' => $this->product->id,
        'from_location_id' => $this->location->id,
        'to_location_id' => $destination->id,
        'quantity' => '7.000',
    ]))->toThrow(InsufficientStockException::class);

    expect($this->product->stockLevels()->where('location_id', $this->location->id)->value('quantity'))->toBe('6.000')
        ->and($this->product->stockLevels()->where('location_id', $destination->id)->value('quantity'))->toBe('4.000')
        ->and(StockMovement::query()->count())->toBe(3);

    expectStockMovementsToMatchLevels($this->product, $this->location);
    expectStockMovementsToMatchLevels($this->product, $destination);
});

test('inactive and soft-deleted products and inactive locations are rejected', function () {
    $inactiveProduct = Product::factory()->create([
        'base_unit_id' => $this->baseUnit->id,
        'is_active' => false,
    ]);

    expect(fn () => $this->service->receive([
        'product_id' => $inactiveProduct->id,
        'location_id' => $this->location->id,
        'quantity' => '1.000',
    ]))->toThrow(InvalidArgumentException::class, 'Inactive products');

    $deletedProduct = Product::factory()->create(['base_unit_id' => $this->baseUnit->id]);
    $deletedProduct->delete();

    expect(fn () => $this->service->receive([
        'product_id' => $deletedProduct->id,
        'location_id' => $this->location->id,
        'quantity' => '1.000',
    ]))->toThrow(ModelNotFoundException::class);

    $inactiveLocation = Location::factory()->create(['name' => 'Closed Store', 'is_active' => false]);

    expect(fn () => $this->service->receive([
        'product_id' => $this->product->id,
        'location_id' => $inactiveLocation->id,
        'quantity' => '1.000',
    ]))->toThrow(InvalidArgumentException::class, 'Inactive locations');

    expect(StockMovement::query()->count())->toBe(0);
});

test('adjustments support signed exact decimals and require a nonzero amount and reason', function () {
    $increased = $this->service->adjust([
        'product_id' => $this->product->id,
        'location_id' => $this->location->id,
        'quantity' => '1.125',
        'reason' => 'Physical count increase',
    ]);
    $decreased = $this->service->adjust([
        'product_id' => $this->product->id,
        'location_id' => $this->location->id,
        'quantity' => '-0.125',
        'reason' => 'Physical count correction',
    ]);

    expect($increased->quantity)->toBe('1.125')
        ->and($decreased->quantity)->toBe('-0.125')
        ->and($this->product->stockLevels()->firstOrFail()->quantity)->toBe('1.000');

    expect(fn () => $this->service->adjust([
        'product_id' => $this->product->id,
        'location_id' => $this->location->id,
        'quantity' => '0.000',
        'reason' => 'No change',
    ]))->toThrow(InvalidArgumentException::class, 'cannot be zero');

    expect(fn () => $this->service->adjust([
        'product_id' => $this->product->id,
        'location_id' => $this->location->id,
        'quantity' => '1.000',
    ]))->toThrow(InvalidArgumentException::class, 'reason is required');

    expect(StockMovement::query()->count())->toBe(2);
    expectStockMovementsToMatchLevels($this->product, $this->location);
});

test('decimal quantities such as 0.125 stay exact end to end', function () {
    $movement = $this->service->receive([
        'product_id' => $this->product->id,
        'location_id' => $this->location->id,
        'quantity' => '0.125',
    ]);

    expect($movement->quantity)->toBe('0.125')
        ->and($this->product->stockLevels()->firstOrFail()->quantity)->toBe('0.125');

    expectStockMovementsToMatchLevels($this->product, $this->location);
});

test('mixed stock operations reconcile movements to levels per product location and batch', function () {
    $product = Product::factory()->create([
        'base_unit_id' => $this->baseUnit->id,
        'track_batches' => true,
    ]);
    $batchA = Batch::factory()->create(['product_id' => $product->id, 'batch_number' => 'MIX-A']);
    $batchB = Batch::factory()->create(['product_id' => $product->id, 'batch_number' => 'MIX-B']);
    $destination = Location::factory()->create(['name' => 'Transfer Destination']);

    $this->service->receive([
        'product_id' => $product->id,
        'location_id' => $this->location->id,
        'batch_id' => $batchA->id,
        'quantity' => '10.250',
    ]);
    $this->service->receive([
        'product_id' => $product->id,
        'location_id' => $this->location->id,
        'batch_id' => $batchB->id,
        'quantity' => '3.500',
    ]);
    $this->service->issue([
        'product_id' => $product->id,
        'location_id' => $this->location->id,
        'batch_id' => $batchA->id,
        'quantity' => '2.125',
        'type' => 'sale',
    ]);
    $this->service->adjust([
        'product_id' => $product->id,
        'location_id' => $this->location->id,
        'batch_id' => $batchA->id,
        'quantity' => '0.375',
        'reason' => 'Count correction',
    ]);
    $this->service->transfer([
        'product_id' => $product->id,
        'from_location_id' => $this->location->id,
        'to_location_id' => $destination->id,
        'batch_id' => $batchA->id,
        'quantity' => '4.000',
    ]);

    $levelsByLocationAndBatch = $product->stockLevels->mapWithKeys(
        fn (StockLevel $level): array => [$level->location_id.':'.($level->batch_id ?? 'null') => $level->quantity],
    );

    $movementsByLocationAndBatch = StockMovement::query()
        ->where('product_id', $product->id)
        ->get()
        ->groupBy(fn (StockMovement $movement): string => $movement->location_id.':'.($movement->batch_id ?? 'null'))
        ->map(fn ($movements): string => $movements->reduce(
            fn (string $sum, StockMovement $movement): string => bcadd($sum, $movement->quantity, 3),
            '0.000',
        ));

    expect($levelsByLocationAndBatch->keys()->sort()->values()->all())
        ->toBe($movementsByLocationAndBatch->keys()->sort()->values()->all());

    foreach ($levelsByLocationAndBatch as $key => $quantity) {
        expect(bccomp((string) $quantity, $movementsByLocationAndBatch->get($key), 3))->toBe(0);
    }
});
