<?php

use App\Exceptions\InsufficientStockException;
use App\Livewire\Actions\Inventory\IssueStock;
use App\Livewire\Actions\Inventory\ReturnStock;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemAllocation;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** @return array{unit: Unit, product: Product, location: Location} */
function saleStockSetup(bool $trackBatches = false): array
{
    $unit = Unit::forceCreate(['name' => 'Piece '.Str::random(6), 'abbreviation' => 'pc']);
    $product = Product::forceCreate([
        'sku' => 'SKU-'.Str::upper(Str::random(8)),
        'name' => 'Test Product',
        'base_unit_id' => $unit->id,
        'reorder_level' => 0,
        'track_batches' => $trackBatches,
        'is_active' => true,
    ]);
    $location = Location::forceCreate([
        'name' => 'Store '.Str::random(6),
        'type' => 'store',
        'is_active' => true,
    ]);

    return ['unit' => $unit, 'product' => $product, 'location' => $location];
}

function saleStockBatch(Product $product, string $number, ?string $expiry): Batch
{
    return Batch::forceCreate([
        'product_id' => $product->id,
        'batch_number' => $number,
        'expiry_date' => $expiry,
    ]);
}

function saleStockReceive(Product $product, Location $location, string $quantity, ?Batch $batch = null): void
{
    app(StockService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'batch_id' => $batch?->id,
        'quantity' => $quantity,
    ]);
}

function saleStockLevel(Product $product, Location $location, ?Batch $batch = null): string
{
    return (string) StockLevel::query()
        ->where('product_id', $product->id)
        ->where('location_id', $location->id)
        ->when(
            $batch === null,
            fn ($query) => $query->whereNull('batch_id'),
            fn ($query) => $query->where('batch_id', $batch->id),
        )
        ->value('quantity');
}

/** @return list<array{batch_id: int|null, base_quantity: string, movement: StockMovement}> */
function saleStockSell(Product $product, Location $location, string $quantity, ?int $unitId = null): array
{
    return app(IssueStock::class)([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'unit_id' => $unitId,
        'quantity' => $quantity,
        'reference' => 'INV-TEST',
    ]);
}

/** Every stock level must equal the sum of its movements. */
function saleStockAssertLedgerBalanced(): void
{
    $rows = DB::select(<<<'SQL'
        SELECT l.quantity AS level,
               COALESCE((SELECT SUM(m.quantity) FROM stock_movements m
                         WHERE m.product_id = l.product_id
                           AND m.location_id = l.location_id
                           AND m.batch_id IS NOT DISTINCT FROM l.batch_id), 0) AS ledger
        FROM stock_levels l
    SQL);

    foreach ($rows as $row) {
        expect(bccomp((string) $row->level, (string) $row->ledger, 3))->toBe(0);
    }
}

/**
 * Build an issued invoice whose allocations mirror a sale result.
 *
 * @param  list<array{batch_id: int|null, base_quantity: string, movement: StockMovement}>  $sale
 */
function saleStockInvoice(Product $product, Location $location, Unit $unit, array $sale): Invoice
{
    $customer = Customer::forceCreate([
        'code' => 'T-'.Str::random(6),
        'name' => 'Test Customer',
        'type' => 'individual',
    ]);
    $invoice = Invoice::forceCreate([
        'number' => 'INV-T-'.Str::random(6),
        'channel' => 'invoice',
        'status' => 'issued',
        'customer_id' => $customer->id,
        'location_id' => $location->id,
    ]);
    $item = InvoiceItem::forceCreate([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'description' => 'Test Product',
        'unit_id' => $unit->id,
        'quantity' => collect($sale)->reduce(fn (string $carry, array $part): string => bcadd($carry, $part['base_quantity'], 3), '0.000'),
        'base_quantity' => collect($sale)->reduce(fn (string $carry, array $part): string => bcadd($carry, $part['base_quantity'], 3), '0.000'),
        'unit_price' => '10.00',
    ]);

    foreach ($sale as $part) {
        InvoiceItemAllocation::forceCreate([
            'invoice_item_id' => $item->id,
            'location_id' => $location->id,
            'batch_id' => $part['batch_id'],
            'base_quantity' => $part['base_quantity'],
            'stock_movement_id' => $part['movement']->id,
        ]);
    }

    return $invoice->fresh();
}

test('a sale of a non-batch product reduces stock and records a signed sale movement', function () {
    ['product' => $product, 'location' => $location] = saleStockSetup();
    saleStockReceive($product, $location, '100');

    $result = saleStockSell($product, $location, '30');

    expect($result)->toHaveCount(1)
        ->and($result[0]['batch_id'])->toBeNull()
        ->and($result[0]['base_quantity'])->toBe('30.000')
        ->and(saleStockLevel($product, $location))->toBe('70.000');

    $movement = StockMovement::query()->where('type', 'sale')->sole();
    expect((string) $movement->quantity)->toBe('-30.000')
        ->and($movement->reference)->toBe('INV-TEST');

    saleStockAssertLedgerBalanced();
});

test('batch-tracked sales take the earliest-expiring batch first and split across batches', function () {
    ['product' => $product, 'location' => $location] = saleStockSetup(true);
    $early = saleStockBatch($product, 'EARLY', now()->addDays(10)->toDateString());
    $late = saleStockBatch($product, 'LATE', now()->addDays(30)->toDateString());
    $none = saleStockBatch($product, 'NOEXP', null);
    saleStockReceive($product, $location, '5', $early);
    saleStockReceive($product, $location, '10', $late);
    saleStockReceive($product, $location, '10', $none);

    $result = saleStockSell($product, $location, '8');

    expect(collect($result)->map(fn (array $part): array => [$part['batch_id'], $part['base_quantity']])->all())
        ->toBe([[$early->id, '5.000'], [$late->id, '3.000']])
        ->and(saleStockLevel($product, $location, $early))->toBe('0.000')
        ->and(saleStockLevel($product, $location, $late))->toBe('7.000')
        ->and(saleStockLevel($product, $location, $none))->toBe('10.000');

    saleStockAssertLedgerBalanced();
});

test('expired batches are never sold', function () {
    ['product' => $product, 'location' => $location] = saleStockSetup(true);
    $expired = saleStockBatch($product, 'OLD', now()->subDay()->toDateString());
    $valid = saleStockBatch($product, 'OK', now()->addDays(20)->toDateString());
    saleStockReceive($product, $location, '100', $expired);
    saleStockReceive($product, $location, '5', $valid);

    expect(fn () => saleStockSell($product, $location, '6'))->toThrow(InsufficientStockException::class);
    expect(saleStockLevel($product, $location, $expired))->toBe('100.000')
        ->and(saleStockLevel($product, $location, $valid))->toBe('5.000')
        ->and(StockMovement::query()->where('type', 'sale')->count())->toBe(0);

    $result = saleStockSell($product, $location, '5');

    expect($result)->toHaveCount(1)
        ->and($result[0]['batch_id'])->toBe($valid->id)
        ->and(saleStockLevel($product, $location, $expired))->toBe('100.000');
});

test('an oversized sale changes nothing', function () {
    ['product' => $product, 'location' => $location] = saleStockSetup(true);
    $a = saleStockBatch($product, 'A', now()->addDays(10)->toDateString());
    $b = saleStockBatch($product, 'B', now()->addDays(20)->toDateString());
    saleStockReceive($product, $location, '5', $a);
    saleStockReceive($product, $location, '3', $b);

    expect(fn () => saleStockSell($product, $location, '9'))->toThrow(InsufficientStockException::class);

    expect(saleStockLevel($product, $location, $a))->toBe('5.000')
        ->and(saleStockLevel($product, $location, $b))->toBe('3.000')
        ->and(StockMovement::query()->where('type', 'sale')->count())->toBe(0);
});

test('a sale in packaging units is converted to base units', function () {
    ['product' => $product, 'location' => $location] = saleStockSetup();
    $carton = Unit::forceCreate(['name' => 'Carton '.Str::random(6), 'abbreviation' => 'ctn']);
    ProductUnit::forceCreate(['product_id' => $product->id, 'unit_id' => $carton->id, 'factor' => '24']);
    saleStockReceive($product, $location, '100');

    $result = saleStockSell($product, $location, '2', $carton->id);

    expect($result[0]['base_quantity'])->toBe('48.000')
        ->and(saleStockLevel($product, $location))->toBe('52.000');

    saleStockAssertLedgerBalanced();
});

test('zero and negative sale quantities are rejected', function () {
    ['product' => $product, 'location' => $location] = saleStockSetup();
    saleStockReceive($product, $location, '10');

    expect(fn () => saleStockSell($product, $location, '0'))->toThrow(InvalidArgumentException::class);
    expect(fn () => saleStockSell($product, $location, '-1'))->toThrow(InvalidArgumentException::class);
    expect(saleStockLevel($product, $location))->toBe('10.000');
});

test('inactive products cannot be sold', function () {
    ['product' => $product, 'location' => $location] = saleStockSetup();
    saleStockReceive($product, $location, '10');
    $product->forceFill(['is_active' => false])->save();

    expect(fn () => saleStockSell($product, $location, '1'))->toThrow(InvalidArgumentException::class);
    expect(saleStockLevel($product, $location))->toBe('10.000');
});

test('returnSale puts stock back as a sale_return movement', function () {
    ['product' => $product, 'location' => $location] = saleStockSetup();
    saleStockReceive($product, $location, '10');
    saleStockSell($product, $location, '4');

    $movement = app(StockService::class)->returnSale([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '4',
    ]);

    expect($movement->type)->toBe('sale_return')
        ->and((string) $movement->quantity)->toBe('4.000')
        ->and(saleStockLevel($product, $location))->toBe('10.000');

    saleStockAssertLedgerBalanced();
});

test('voiding returns the exact quantities to the exact batches, once only', function () {
    ['unit' => $unit, 'product' => $product, 'location' => $location] = saleStockSetup(true);
    $a = saleStockBatch($product, 'A', now()->addDays(10)->toDateString());
    $b = saleStockBatch($product, 'B', now()->addDays(20)->toDateString());
    saleStockReceive($product, $location, '5', $a);
    saleStockReceive($product, $location, '10', $b);

    $sale = saleStockSell($product, $location, '8');
    $invoice = saleStockInvoice($product, $location, $unit, $sale);

    expect(saleStockLevel($product, $location, $a))->toBe('0.000')
        ->and(saleStockLevel($product, $location, $b))->toBe('7.000');

    app(ReturnStock::class)($invoice, 'Customer cancelled');

    expect(saleStockLevel($product, $location, $a))->toBe('5.000')
        ->and(saleStockLevel($product, $location, $b))->toBe('10.000')
        ->and(StockMovement::query()->where('type', 'sale_return')->count())->toBe(2)
        ->and(InvoiceItemAllocation::query()->whereNull('return_movement_id')->count())->toBe(0);

    // A second call must not return anything again.
    app(ReturnStock::class)($invoice);

    expect(StockMovement::query()->where('type', 'sale_return')->count())->toBe(2)
        ->and(saleStockLevel($product, $location, $a))->toBe('5.000')
        ->and(saleStockLevel($product, $location, $b))->toBe('10.000');

    saleStockAssertLedgerBalanced();
});

test('returns still work when the product or location was deactivated after the sale', function () {
    ['unit' => $unit, 'product' => $product, 'location' => $location] = saleStockSetup();
    saleStockReceive($product, $location, '10');
    $sale = saleStockSell($product, $location, '4');
    $invoice = saleStockInvoice($product, $location, $unit, $sale);

    $product->forceFill(['is_active' => false])->save();
    $location->forceFill(['is_active' => false])->save();

    app(ReturnStock::class)($invoice);

    expect(saleStockLevel($product, $location))->toBe('10.000');

    saleStockAssertLedgerBalanced();
});
