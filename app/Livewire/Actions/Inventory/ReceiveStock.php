<?php

namespace App\Livewire\Actions\Inventory;

use App\Models\Batch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Receive stock into a location, optionally creating or reusing a batch.
 *
 * For batch-tracked products the caller must supply either an existing
 * `batch_id` OR a `batch_number` string (optionally with `batch_expiry`).
 * Batch numbers are unique per product and are created inside the same
 * transaction as the stock receipt.
 */
class ReceiveStock
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @param  array{
     *   product_id: int,
     *   location_id: int,
     *   unit_id?: int|null,
     *   quantity: string,
     *   batch_id?: int|null,
     *   batch_number?: string|null,
     *   batch_expiry?: string|null,
     *   reference?: string|null,
     *   person_name?: string|null,
     *   notes?: string|null,
     * }  $data
     */
    public function __invoke(array $data): StockMovement
    {
        abort_unless(auth()->user()?->can('inventory.receive'), 403);

        return DB::transaction(function () use ($data): StockMovement {
            $product = Product::query()
                ->whereKey((int) $data['product_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $batchId = $this->resolveBatch($product, $data);

            return $this->stock->receive([
                'product_id' => $product->id,
                'location_id' => $data['location_id'],
                'unit_id' => $data['unit_id'] ?? null,
                'quantity' => $data['quantity'],
                'batch_id' => $batchId,
                'reference' => $data['reference'] ?? null,
                'person_name' => $data['person_name'] ?? null,
                'reason' => $data['notes'] ?? null,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    private function resolveBatch(Product $product, array $data): ?int
    {
        if (! $product->track_batches) {
            return null;
        }

        $batchId = $data['batch_id'] ?? null;
        $batchNumber = trim((string) ($data['batch_number'] ?? ''));

        // Existing batch chosen
        if ($batchId !== null && (int) $batchId > 0) {
            return (int) $batchId;
        }

        // New batch — batch_number required
        if ($batchNumber === '') {
            throw ValidationException::withMessages([
                'batch_number' => 'A batch number or existing batch is required for this product.',
            ]);
        }

        // Unique per product check
        if ($product->batches()->where('batch_number', $batchNumber)->exists()) {
            throw ValidationException::withMessages([
                'batch_number' => "Batch number [{$batchNumber}] already exists for this product.",
            ]);
        }

        $expiry = $data['batch_expiry'] ?? null;

        $batch = Batch::query()->create([
            'product_id' => $product->id,
            'batch_number' => $batchNumber,
            'expiry_date' => ($expiry !== null && $expiry !== '') ? $expiry : null,
        ]);

        return $batch->id;
    }
}
