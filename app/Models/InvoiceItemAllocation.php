<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records which location and batch stock came from for an invoice line, so voids return it exactly.
 *
 * @property int $id
 * @property int $invoice_item_id
 * @property int $location_id
 * @property int|null $batch_id
 * @property numeric-string $base_quantity
 * @property int|null $stock_movement_id
 * @property int|null $return_movement_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read InvoiceItem $item
 * @property-read Location $location
 * @property-read Batch|null $batch
 * @property-read StockMovement|null $stockMovement
 */
class InvoiceItemAllocation extends Model
{
    protected $fillable = [
        'invoice_item_id', 'location_id', 'batch_id', 'base_quantity',
        'stock_movement_id', 'return_movement_id',
    ];

    protected function casts(): array
    {
        return ['base_quantity' => 'decimal:3'];
    }

    /** @return BelongsTo<InvoiceItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class, 'invoice_item_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
