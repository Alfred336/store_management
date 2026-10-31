<?php

namespace App\Models;

use Database\Factories\BatchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    /** @use HasFactory<BatchFactory> */
    use HasFactory;

    protected $fillable = ['product_id', 'batch_number', 'expiry_date'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expiry_date' => 'date'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<StockLevel, $this> */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * @param  Builder<Batch>  $query
     * @return Builder<Batch>
     */
    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereBetween('expiry_date', [today(), today()->addDays($days)]);
    }
}
