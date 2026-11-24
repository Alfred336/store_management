<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property numeric-string $reorder_level
 * @property numeric-string $selling_price
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'sku',
        'barcode',
        'tax_type',
        'name',
        'category_id',
        'base_unit_id',
        'reorder_level',
        'selling_price',
        'track_batches',
        'description',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reorder_level' => 'decimal:3',
            'selling_price' => 'decimal:2',
            'barcode' => 'string',
            'track_batches' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    /** @return HasMany<ProductUnit, $this> */
    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    /** @return BelongsToMany<Unit, $this> */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'product_units')->withPivot('factor')->withTimestamps();
    }

    /** @return HasMany<Batch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    /** @return HasMany<StockLevel, $this> */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeWithTotalQuantity(Builder $query): Builder
    {
        return $query->withSum('stockLevels as total_qty', 'quantity');
    }

    /**
     * "Out of stock": product has zero total quantity across the given locations.
     * Pass null (default) to aggregate across ALL locations (Admin / global view).
     * Pass an int[] to restrict to those location IDs; an empty array means 0 qty everywhere.
     *
     * @param  Builder<Product>  $query
     * @param  list<int>|null  $locationIds
     * @return Builder<Product>
     */
    public function scopeOutOfStock(Builder $query, ?array $locationIds = null): Builder
    {
        if ($locationIds !== null) {
            if (empty($locationIds)) {
                return $query->whereRaw('0 = 1'); // no locations → no out-of-stock badges
            }
            $placeholders = implode(',', array_fill(0, count($locationIds), '?'));

            return $query->whereRaw(
                "(SELECT COALESCE(SUM(sl.quantity), 0) FROM stock_levels sl WHERE sl.product_id = products.id AND sl.location_id IN ({$placeholders})) = 0",
                $locationIds
            );
        }

        return $query->whereRaw(
            '(SELECT COALESCE(SUM(quantity), 0) FROM stock_levels WHERE stock_levels.product_id = products.id) = 0'
        );
    }

    /**
     * "Low stock": total qty > 0, reorder_level > 0, qty <= reorder_level.
     * Products with reorder_level = 0 are never "low stock".
     * Pass null to aggregate across ALL locations (Admin); pass int[] to restrict.
     *
     * @param  Builder<Product>  $query
     * @param  list<int>|null  $locationIds
     * @return Builder<Product>
     */
    public function scopeLowStock(Builder $query, ?array $locationIds = null): Builder
    {
        if ($locationIds !== null) {
            if (empty($locationIds)) {
                return $query->whereRaw('0 = 1');
            }
            $ph = implode(',', array_fill(0, count($locationIds), '?'));
            $sum = "(SELECT COALESCE(SUM(sl.quantity),0) FROM stock_levels sl WHERE sl.product_id=products.id AND sl.location_id IN ({$ph}))";

            return $query
                ->whereRaw("{$sum}>0", $locationIds)
                ->whereRaw('products.reorder_level>0')
                ->whereRaw("{$sum}<=products.reorder_level", $locationIds);
        }

        return $query
            ->whereRaw('(SELECT COALESCE(SUM(quantity),0) FROM stock_levels WHERE stock_levels.product_id=products.id)>0')
            ->whereRaw('products.reorder_level>0')
            ->whereRaw('(SELECT COALESCE(SUM(quantity),0) FROM stock_levels WHERE stock_levels.product_id=products.id)<=products.reorder_level');
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $pattern = '%'.$term.'%';

        return $query->where(function (Builder $query) use ($pattern): void {
            $query->where('name', 'ILIKE', $pattern)
                ->orWhere('sku', 'ILIKE', $pattern);
        });
    }
}
