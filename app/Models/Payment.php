<?php

namespace App\Models;

use App\Services\Access\LocationAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer payment record. Immutable after creation; use ReversePayment to undo.
 *
 * @property int $id
 * @property string $number
 * @property int $customer_id
 * @property int|null $location_id
 * @property string $method
 * @property string|null $provider
 * @property string|null $reference
 * @property numeric-string $amount
 * @property numeric-string|null $amount_tendered
 * @property numeric-string|null $change_given
 * @property int|null $pos_session_id
 * @property CarbonImmutable $received_at
 * @property int $received_by
 * @property string $status active|reversed
 * @property int|null $reversed_by
 * @property CarbonImmutable|null $reversed_at
 * @property string|null $reverse_reason
 * @property string|null $notes
 * @property string $idempotency_key
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Payment extends Model
{
    protected $fillable = [
        'number', 'customer_id', 'location_id', 'method', 'provider', 'reference',
        'amount', 'amount_tendered', 'change_given',
        'received_at', 'received_by', 'status',
        'reversed_by', 'reversed_at', 'reverse_reason', 'notes',
        'idempotency_key', 'pos_session_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_tendered' => 'decimal:2',
            'change_given' => 'decimal:2',
            'received_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** @return BelongsTo<PosSession, $this> */
    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    /**
     * Amount not yet applied to any invoice.
     * Computed from live allocation records; never stored.
     *
     * @return numeric-string
     */
    public function unallocatedCredit(): string
    {
        $allocated = $this->allocations()->sum('amount');

        return bcsub((string) $this->amount, number_format((float) $allocated, 2, '.', ''), 2);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Restrict results to payments the given user may see.
     *
     * - Admin (allowedLocationIds === null): all payments, including null-location ones.
     * - Restricted user: only payments with a location_id in their allowed set.
     *   Null-location (unallocated/multi-location) payments are excluded.
     *
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
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

        return $query->whereIn('payments.location_id', $ids);
    }
}
