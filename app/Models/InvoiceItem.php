<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One invoice line. Description, SKU, price and tax are snapshots.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $product_id
 * @property string $description
 * @property string|null $sku
 * @property int $unit_id
 * @property numeric-string $quantity
 * @property numeric-string $base_quantity
 * @property numeric-string $unit_price
 * @property numeric-string $discount_amount
 * @property string $tax_type
 * @property numeric-string $tax_rate
 * @property numeric-string $line_tax
 * @property numeric-string $line_total
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Product $product
 * @property-read Invoice $invoice
 * @property-read Unit $unit
 */
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

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return HasMany<InvoiceItemAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(InvoiceItemAllocation::class);
    }
}
