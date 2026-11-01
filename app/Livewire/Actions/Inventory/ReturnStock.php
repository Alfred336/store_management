<?php

namespace App\Livewire\Actions\Inventory;

use App\Models\Invoice;
use App\Models\InvoiceItemAllocation;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * Return to stock everything an invoice took out, exactly as it was taken:
 * same products, locations, batches and base quantities.
 *
 * Safe to call twice: allocations that were already returned are skipped.
 * Callers (VoidInvoice) are responsible for authorization.
 */
class ReturnStock
{
    public function __construct(private readonly StockService $stock) {}

    public function __invoke(Invoice $invoice, ?string $reason = null): void
    {
        DB::transaction(function () use ($invoice, $reason): void {
            $allocations = InvoiceItemAllocation::query()
                ->whereHas('item', fn ($query) => $query->where('invoice_id', $invoice->id))
                ->whereNull('return_movement_id')
                ->with('item')
                ->lockForUpdate()
                ->get()
                // Consistent order (product, then id) lowers the chance of deadlocks between voids.
                ->sortBy(fn (InvoiceItemAllocation $allocation): string => sprintf(
                    '%012d-%012d',
                    $allocation->item->product_id,
                    $allocation->id,
                ));

            foreach ($allocations as $allocation) {
                $movement = $this->stock->returnSale([
                    'product_id' => $allocation->item->product_id,
                    'location_id' => $allocation->location_id,
                    'batch_id' => $allocation->batch_id,
                    'unit_id' => null, // allocations are stored in base units
                    'quantity' => (string) $allocation->base_quantity,
                    'reference' => $invoice->number,
                    'reason' => $reason,
                    'person_name' => auth()->user()?->name,
                ]);

                $allocation->update(['return_movement_id' => $movement->id]);
            }
        });
    }
}
