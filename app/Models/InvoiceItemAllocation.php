<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Records which location and batch stock came from for an invoice line, so voids return it exactly. */
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

    public function item(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class, 'invoice_item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}