<?php

namespace App\Models;

use App\Services\Access\LocationAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property numeric-string $quantity
 */
class StockLevel extends Model
{
    protected $fillable = ['product_id', 'location_id', 'batch_id', 'quantity'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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

    /**
     * @param  Builder<StockLevel>  $query
     * @return Builder<StockLevel>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $ids = app(LocationAccess::class)->allowedLocationIds($user);
        if ($ids === null) {
            return $query;
        }
        if (empty($ids)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('stock_levels.location_id', $ids);
    }
}
