<?php

namespace App\Livewire\Actions\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Take stock out for a sale, one StockService movement per batch used.
 *
 * Batch-tracked products are sold first-expiry-first-out (FEFO): the batch that
 * expires soonest goes first, a line may be split across batches, batches with
 * no expiry go last, and expired batches are never sold (a batch is still
 * sellable on its expiry date). The caller cannot choose a batch.
 *
 * Callers (IssueInvoice, POS) are responsible for authorization.
 */
class IssueStock
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @param  array<string, mixed>  $data  product_id, location_id, quantity, optional unit_id, type (default sale), reference, reason, person_name
     * @return list<array{batch_id: int|null, base_quantity: string, movement: StockMovement}>
     */
    public function __invoke(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $locationId = (int) ($data['location_id'] ?? 0);

            // Lock the product first so concurrent sales of the same product queue up
            // and the FEFO plan below cannot be invalidated by another transaction.
            $product = Product::query()
                ->whereKey((int) ($data['product_id'] ?? 0))
                ->lockForUpdate()
                ->firstOrFail();

            if (! $product->is_active) {
                throw new InvalidArgumentException('Inactive products cannot have stock movements.');
            }

            $unitId = isset($data['unit_id']) ? (int) $data['unit_id'] : null;
            $baseQuantity = $this->stock->convertToBase($product, $unitId, $data['quantity'] ?? '0');

            if (bccomp($baseQuantity, '0', 3) <= 0) {
                throw new InvalidArgumentException('Quantity must be greater than zero.');
            }

            $parts = $product->track_batches
                ? $this->planByExpiry($product, $locationId, $baseQuantity)
                : [['batch_id' => null, 'base_quantity' => $baseQuantity]];

            $results = [];

            foreach ($parts as $part) {
                $movement = $this->stock->issue([
                    'product_id' => $product->id,
                    'location_id' => $locationId,
                    'batch_id' => $part['batch_id'],
                    'unit_id' => null, // already converted to base units
                    'quantity' => $part['base_quantity'],
                    'type' => $data['type'] ?? 'sale',
                    'reference' => $data['reference'] ?? null,
                    'reason' => $data['reason'] ?? null,
                    'person_name' => $data['person_name'] ?? null,
                ]);

                $results[] = [
                    'batch_id' => $part['batch_id'],
                    'base_quantity' => $part['base_quantity'],
                    'movement' => $movement,
                ];
            }

            return $results;
        });
    }

    /**
     * Decide which batches supply the quantity, earliest expiry first.
     *
     * @return list<array{batch_id: int, base_quantity: string}>
     */
    private function planByExpiry(Product $product, int $locationId, string $baseQuantity): array
    {
        $levels = StockLevel::query()
            ->select('stock_levels.*')
            ->join('batches', 'batches.id', '=', 'stock_levels.batch_id')
            ->where('stock_levels.product_id', $product->id)
            ->where('stock_levels.location_id', $locationId)
            ->where('stock_levels.quantity', '>', 0)
            ->where(function ($query): void {
                $query->whereNull('batches.expiry_date')
                    ->orWhere('batches.expiry_date', '>=', now()->toDateString());
            })
            ->orderByRaw('batches.expiry_date ASC NULLS LAST')
            ->orderBy('batches.id')
            ->get();

        $remaining = $baseQuantity;
        $available = '0.000';
        $plan = [];

        foreach ($levels as $level) {
            $levelQuantity = bcadd((string) $level->quantity, '0', 3);
            $available = bcadd($available, $levelQuantity, 3);

            if (bccomp($remaining, '0', 3) === 0) {
                continue;
            }

            $take = bccomp($levelQuantity, $remaining, 3) >= 0 ? $remaining : $levelQuantity;
            $plan[] = ['batch_id' => (int) $level->batch_id, 'base_quantity' => bcadd($take, '0', 3)];
            $remaining = bcsub($remaining, $take, 3);
        }

        if (bccomp($remaining, '0', 3) > 0) {
            // Only non-expired stock counts as available.
            throw new InsufficientStockException($available);
        }

        return $plan;
    }
}
