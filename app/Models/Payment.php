<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer payment record. Immutable after creation; use ReversePayment to undo.
 *
 * @property int $id
 * @property string $number
 * @property int $customer_id
 * @property string $method
 * @property string|null $provider
 * @property string|null $reference
 * @property numeric-string $amount
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
        'number', 'customer_id', 'method', 'provider', 'reference',
        'amount', 'received_at', 'received_by', 'status',
        'reversed_by', 'reversed_at', 'reverse_reason', 'notes',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
}
