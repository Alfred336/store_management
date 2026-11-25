<?php

namespace App\Models;

use App\Services\Access\LocationAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id',
        'location_id',
        'batch_id',
        'type',
        'quantity',
        'reference',
        'reason',
        'person_name',
        'user_id',
        'moved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'moved_at' => 'datetime',
        ];
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<StockMovement>  $query
     * @return Builder<StockMovement>
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

        return $query->whereIn('stock_movements.location_id', $ids);
    }
}
