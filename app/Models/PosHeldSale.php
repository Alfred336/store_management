<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A held (paused) POS cart. Does not affect stock, numbering, or payments.
 *
 * @property int $id
 * @property int $user_id
 * @property int $location_id
 * @property int|null $pos_session_id
 * @property int|null $customer_id
 * @property string $label
 * @property array<int, mixed> $cart
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Location $location
 * @property-read PosSession|null $session
 * @property-read Customer|null $customer
 */
class PosHeldSale extends Model
{
    protected $fillable = [
        'user_id', 'location_id', 'pos_session_id', 'customer_id', 'label', 'cart',
    ];

    protected function casts(): array
    {
        return [
            'cart' => 'array',
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

    /** @return BelongsTo<PosSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
