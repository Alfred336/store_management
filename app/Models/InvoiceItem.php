<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One invoice line. Description, SKU, price and tax are snapshots. */
class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'product_id', 'description', 'sku', 'unit_id',
        'quantity', 'base_quantity', 'unit_price', 'discount_amount',
        'tax_type', 'tax_rate', 'line_tax', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'base_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_tax' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InvoiceItemAllocation::class);
    }
}
