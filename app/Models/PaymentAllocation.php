<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a portion of a payment to a specific invoice.
 * Records are kept for audit even after the parent payment is reversed.
 *
 * @property int $id
 * @property int $payment_id
 * @property int $invoice_id
 * @property numeric-string $amount
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class PaymentAllocation extends Model
{
    protected $fillable = ['payment_id', 'invoice_id', 'amount'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
