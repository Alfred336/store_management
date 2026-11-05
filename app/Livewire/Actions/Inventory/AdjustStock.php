<?php

namespace App\Livewire\Actions\Inventory;

use App\Models\StockMovement;
use App\Services\StockService;

/** Adjust stock at a location by a signed quantity. Reason is required. */
class AdjustStock
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @param  array{
     *   product_id: int,
     *   location_id: int,
     *   batch_id?: int|null,
     *   unit_id?: int|null,
     *   quantity: string,
     *   reason: string,
     *   reference?: string|null,
     *   person_name?: string|null,
     * }  $data
     */
    public function __invoke(array $data): StockMovement
    {
        abort_unless(auth()->user()?->can('inventory.adjust'), 403);

        return $this->stock->adjust([
            'product_id' => $data['product_id'],
            'location_id' => $data['location_id'],
            'batch_id' => $data['batch_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'quantity' => $data['quantity'],
            'reason' => $data['reason'],
            'reference' => $data['reference'] ?? null,
            'person_name' => $data['person_name'] ?? null,
        ]);
    }
}
