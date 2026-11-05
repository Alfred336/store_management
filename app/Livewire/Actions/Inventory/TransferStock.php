<?php

namespace App\Livewire\Actions\Inventory;

use App\Models\StockMovement;
use App\Services\StockService;

/** Transfer stock between two different locations. */
class TransferStock
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @param  array{
     *   product_id: int,
     *   from_location_id: int,
     *   to_location_id: int,
     *   batch_id?: int|null,
     *   unit_id?: int|null,
     *   quantity: string,
     *   reference?: string|null,
     *   person_name?: string|null,
     * }  $data
     * @return array{0: StockMovement, 1: StockMovement}
     */
    public function __invoke(array $data): array
    {
        abort_unless(auth()->user()?->can('inventory.transfer'), 403);

        return $this->stock->transfer([
            'product_id' => $data['product_id'],
            'from_location_id' => $data['from_location_id'],
            'to_location_id' => $data['to_location_id'],
            'batch_id' => $data['batch_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'quantity' => $data['quantity'],
            'reference' => $data['reference'] ?? null,
            'person_name' => $data['person_name'] ?? null,
        ]);
    }
}
