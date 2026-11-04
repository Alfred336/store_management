<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cashier POS session. Exactly one open session is allowed per user (enforced by partial unique index).
 *
 * @property int $id
 * @property int $user_id
 * @property int $location_id
 * @property CarbonImmutable $opened_at
 * @property numeric-string $opening_cash
 * @property CarbonImmutable|null $closed_at
 * @property numeric-string|null $closing_cash_counted
 * @property numeric-string|null $expected_cash
 * @property numeric-string|null $difference
 * @property int|null $closed_by
 * @property string|null $notes
 * @property string $status open|closed
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Location $location
 * @property-read User|null $closer
 */
class PosSession extends Model
{
    protected $fillable = [
        'user_id', 'location_id', 'opened_at', 'opening_cash',
        'closed_at', 'closing_cash_counted', 'expected_cash', 'difference',
        'closed_by', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'closing_cash_counted' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<PosHeldSale, $this> */
    public function heldSales(): HasMany
    {
        return $this->hasMany(PosHeldSale::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
