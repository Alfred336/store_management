<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockService
{
    private const SCALE = 3;

    /** @param array<string, mixed> $data */
    public function receive(array $data): StockMovement
    {
        return DB::transaction(function () use ($data): StockMovement {
            $product = $this->lockActiveProduct($data['product_id'] ?? null);
            $location = $this->lockActiveLocation($data['location_id'] ?? null);
            $batchId = $this->validatedBatchId($product, $data['batch_id'] ?? null);
            $quantity = $this->positiveQuantity($data['quantity'] ?? null);
            $baseQuantity = $this->convertToBase($product, $this->unitId($data['unit_id'] ?? null), $quantity);

            return $this->applyStockChange($product, $location->id, $batchId, $baseQuantity, 'receipt', $data);
        });
    }

    /** @param array<string, mixed> $data */
    public function issue(array $data): StockMovement
    {
        return DB::transaction(function () use ($data): StockMovement {
            $type = $data['type'] ?? 'issue';

            if (! is_string($type) || ! in_array($type, ['sale', 'issue', 'damage'], true)) {
                throw new InvalidArgumentException('Issue type must be sale, issue, or damage.');
            }

            $product = $this->lockActiveProduct($data['product_id'] ?? null);
            $location = $this->lockActiveLocation($data['location_id'] ?? null);
            $batchId = $this->validatedBatchId($product, $data['batch_id'] ?? null);
            $quantity = $this->positiveQuantity($data['quantity'] ?? null);
            $baseQuantity = $this->convertToBase($product, $this->unitId($data['unit_id'] ?? null), $quantity);

            return $this->applyStockChange(
                $product,
                $location->id,
                $batchId,
                bcsub('0', $baseQuantity, self::SCALE),
                $type,
                $data,
            );
        });
    }

    /** @param array<string, mixed> $data */
    public function adjust(array $data): StockMovement
    {
        return DB::transaction(function () use ($data): StockMovement {
            $reason = $data['reason'] ?? null;

            if (! is_string($reason) || trim($reason) === '') {
                throw new InvalidArgumentException('A reason is required for stock adjustments.');
            }

            $product = $this->lockActiveProduct($data['product_id'] ?? null);
            $location = $this->lockActiveLocation($data['location_id'] ?? null);
            $batchId = $this->validatedBatchId($product, $data['batch_id'] ?? null);
            $quantity = $this->decimalQuantity($data['quantity'] ?? null);

            if (bccomp($quantity, '0', self::SCALE) === 0) {
                throw new InvalidArgumentException('Adjustment quantity cannot be zero.');
            }

            $baseQuantity = $this->convertToBase($product, $this->unitId($data['unit_id'] ?? null), $quantity);

            return $this->applyStockChange($product, $location->id, $batchId, $baseQuantity, 'adjustment', $data);
        });
    }

    /**
     * Put stock back after a voided sale (movement type sale_return).
     *
     * Unlike receive(), this is allowed for inactive or soft-deleted products and
     * inactive locations: a void must always be able to return exactly what was sold.
     *
     * @param  array<string, mixed>  $data
     */
    public function returnSale(array $data): StockMovement
    {
        return DB::transaction(function () use ($data): StockMovement {
            $product = $this->lockProduct($data['product_id'] ?? null);
            $location = $this->lockLocation($data['location_id'] ?? null);
            $batchId = $this->validatedBatchId($product, $data['batch_id'] ?? null);
            $quantity = $this->positiveQuantity($data['quantity'] ?? null);
            $baseQuantity = $this->convertToBase($product, $this->unitId($data['unit_id'] ?? null), $quantity);

            return $this->applyStockChange($product, $location->id, $batchId, $baseQuantity, 'sale_return', $data);
        });
    }

    /** @param array<string, mixed> $data
     * @return array{0: StockMovement, 1: StockMovement}
     */
    public function transfer(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $product = $this->lockActiveProduct($data['product_id'] ?? null);
            $fromId = $this->requiredId($data['from_location_id'] ?? null, 'from_location_id');
            $toId = $this->requiredId($data['to_location_id'] ?? null, 'to_location_id');

            if ($fromId === $toId) {
                throw new InvalidArgumentException('Transfer locations must be different.');
            }

            $locations = Location::query()
                ->whereIn('id', [$fromId, $toId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $from = $this->activeLockedLocation($locations, $fromId);
            $to = $this->activeLockedLocation($locations, $toId);
            $batchId = $this->validatedBatchId($product, $data['batch_id'] ?? null);
            $quantity = $this->positiveQuantity($data['quantity'] ?? null);
            $baseQuantity = $this->convertToBase($product, $this->unitId($data['unit_id'] ?? null), $quantity);

            // Lock inventory rows in location order regardless of transfer direction.
            $levels = [];

            foreach (collect([$from, $to])->sortBy('id') as $location) {
                $levels[$location->id] = $this->lockStockLevel($product, $location->id, $batchId);
            }

            $out = $this->applyStockChange(
                $product,
                $from->id,
                $batchId,
                bcsub('0', $baseQuantity, self::SCALE),
                'transfer_out',
                $data,
                $levels[$from->id],
            );

            $in = $this->applyStockChange(
                $product,
                $to->id,
                $batchId,
                $baseQuantity,
                'transfer_in',
                $data,
                $levels[$to->id],
            );

            return [$out, $in];
        });
    }

    /** @return numeric-string */
    public function convertToBase(Product $product, ?int $unitId, string|float $quantity): string
    {
        $normalizedQuantity = $this->decimalQuantity($quantity);

        if ($unitId === null || $unitId === (int) $product->base_unit_id) {
            return bcadd($normalizedQuantity, '0', self::SCALE);
        }

        $factor = $product->productUnits()
            ->where('unit_id', $unitId)
            ->value('factor');

        if ($factor === null) {
            throw new InvalidArgumentException("Unit [{$unitId}] is not defined for product [{$product->id}].");
        }

        $factor = $this->decimalQuantity((string) $factor);

        if (bccomp($factor, '0', self::SCALE) <= 0) {
            throw new InvalidArgumentException('Unit conversion factor must be greater than zero.');
        }

        return bcmul($normalizedQuantity, $factor, self::SCALE);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  numeric-string  $signedBaseQuantity
     */
    private function applyStockChange(
        Product $product,
        int $locationId,
        ?int $batchId,
        string $signedBaseQuantity,
        string $type,
        array $data,
        ?StockLevel $lockedLevel = null,
    ): StockMovement {
        $level = $lockedLevel ?? $this->lockStockLevel($product, $locationId, $batchId);
        $newQuantity = bcadd((string) $level->quantity, $signedBaseQuantity, self::SCALE);

        if (bccomp($newQuantity, '0', self::SCALE) < 0) {
            throw new InsufficientStockException((string) $level->quantity);
        }

        $level->quantity = $newQuantity;
        $level->save();

        return StockMovement::query()->create([
            'product_id' => $product->id,
            'location_id' => $locationId,
            'batch_id' => $batchId,
            'type' => $type,
            'quantity' => $signedBaseQuantity,
            'reference' => $data['reference'] ?? null,
            'reason' => $data['reason'] ?? null,
            'person_name' => $data['person_name'] ?? null,
            'user_id' => auth()->id(),
            'moved_at' => $data['moved_at'] ?? now(),
        ]);
    }

    private function lockStockLevel(Product $product, int $locationId, ?int $batchId): StockLevel
    {
        $criteria = ['location_id' => $locationId, 'batch_id' => $batchId];

        $product->stockLevels()->firstOrCreate($criteria, ['quantity' => '0.000']);

        return $product->stockLevels()
            ->where('location_id', $locationId)
            ->when($batchId === null, fn ($query) => $query->whereNull('batch_id'), fn ($query) => $query->where('batch_id', $batchId))
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockActiveProduct(mixed $productId): Product
    {
        $id = $this->requiredId($productId, 'product_id');
        $product = Product::query()->whereKey($id)->lockForUpdate()->firstOrFail();

        if (! $product->is_active) {
            throw new InvalidArgumentException('Inactive products cannot have stock movements.');
        }

        return $product;
    }

    private function lockActiveLocation(mixed $locationId): Location
    {
        $id = $this->requiredId($locationId, 'location_id');
        $location = Location::query()->whereKey($id)->lockForUpdate()->firstOrFail();

        if (! $location->is_active) {
            throw new InvalidArgumentException('Inactive locations cannot have stock movements.');
        }

        return $location;
    }

    /** Lock a product in any state (used only for returns). */
    private function lockProduct(mixed $productId): Product
    {
        $id = $this->requiredId($productId, 'product_id');

        return Product::query()->withTrashed()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** Lock a location in any state (used only for returns). */
    private function lockLocation(mixed $locationId): Location
    {
        $id = $this->requiredId($locationId, 'location_id');

        return Location::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** @param Collection<int|string, Location> $locations */
    private function activeLockedLocation(Collection $locations, int $id): Location
    {
        $location = $locations->get($id);

        if (! $location instanceof Location) {
            throw (new ModelNotFoundException)->setModel(Location::class, [$id]);
        }

        if (! $location->is_active) {
            throw new InvalidArgumentException('Inactive locations cannot have stock movements.');
        }

        return $location;
    }

    private function validatedBatchId(Product $product, mixed $batchId): ?int
    {
        if ($product->track_batches && $batchId === null) {
            throw new InvalidArgumentException('A batch is required for this product.');
        }

        if (! $product->track_batches && $batchId !== null) {
            throw new InvalidArgumentException('A batch cannot be specified for a product that does not track batches.');
        }

        if ($batchId === null) {
            return null;
        }

        $id = $this->requiredId($batchId, 'batch_id');

        if (! $product->batches()->whereKey($id)->lockForUpdate()->exists()) {
            throw new InvalidArgumentException('The batch does not belong to this product.');
        }

        return $id;
    }

    private function positiveQuantity(mixed $value): string
    {
        $quantity = $this->decimalQuantity($value);

        if (bccomp($quantity, '0', self::SCALE) <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return $quantity;
    }

    /** @return numeric-string */
    private function decimalQuantity(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException('Quantity must be a decimal string or number.');
        }

        $quantity = (string) $value;

        if (! preg_match('/^-?\d+(?:\.\d{1,3})?$/D', $quantity) || ! is_numeric($quantity)) {
            throw new InvalidArgumentException('Quantity must be a decimal with at most three fractional digits.');
        }

        return bcadd($quantity, '0', self::SCALE);
    }

    private function unitId(mixed $unitId): ?int
    {
        return $unitId === null ? null : $this->requiredId($unitId, 'unit_id');
    }

    private function requiredId(mixed $value, string $field): int
    {
        if ((! is_int($value) && ! (is_string($value) && ctype_digit($value))) || (int) $value < 1) {
            throw new InvalidArgumentException("A valid {$field} is required.");
        }

        return (int) $value;
    }
}
