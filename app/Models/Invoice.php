<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Single sales document for back-office invoices and POS receipts
 * (see the "channel" column). Written only through invoicing Actions.
 *
 * @property int $id
 * @property string|null $number
 * @property string $channel
 * @property InvoiceStatus $status
 * @property int $customer_id
 * @property int $location_id
 * @property int|null $pos_session_id
 * @property CarbonImmutable|null $issue_date
 * @property CarbonImmutable|null $due_date
 * @property string $currency
 * @property bool $prices_include_tax
 * @property numeric-string $subtotal
 * @property numeric-string $discount_total
 * @property numeric-string $tax_total
 * @property numeric-string $total
 * @property numeric-string $amount_paid
 * @property string|null $notes
 * @property string|null $terms
 * @property string|null $template_key
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property string|null $customer_address
 * @property string|null $customer_tin
 * @property string|null $customer_vrn
 * @property string|null $idempotency_key
 * @property int|null $created_by
 * @property int|null $issued_by
 * @property CarbonImmutable|null $issued_at
 * @property int|null $voided_by
 * @property CarbonImmutable|null $voided_at
 * @property string|null $void_reason
 * @property string|null $fiscal_number
 * @property string|null $fiscal_verification_code
 * @property string|null $fiscal_qr_payload
 * @property string|null $fiscal_status
 * @property CarbonImmutable|null $fiscal_submitted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Invoice extends Model
{
    protected $fillable = [
        'number', 'channel', 'status', 'customer_id', 'location_id', 'pos_session_id',
        'issue_date', 'due_date', 'currency', 'prices_include_tax',
        'subtotal', 'discount_total', 'tax_total', 'total', 'amount_paid',
        'notes', 'terms', 'template_key',
        'customer_name', 'customer_phone', 'customer_address', 'customer_tin', 'customer_vrn',
        'idempotency_key', 'created_by', 'issued_by', 'issued_at',
        'voided_by', 'voided_at', 'void_reason',
        'fiscal_number', 'fiscal_verification_code', 'fiscal_qr_payload',
        'fiscal_status', 'fiscal_submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
            'fiscal_submitted_at' => 'datetime',
            'prices_include_tax' => 'boolean',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
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

    /** @return BelongsTo<PosSession, $this> */
    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** @return BelongsTo<User, $this> */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /** @return HasMany<InvoiceDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(InvoiceDocument::class);
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Amount still owed. Computed from stored values with bcmath, never stored.
     *
     * @return numeric-string
     */
    public function balance(): string
    {
        return bcsub((string) $this->total, (string) $this->amount_paid, 2);
    }

    /** Overdue is derived: past the due date with a balance, and not void or draft. */
    public function isOverdue(): bool
    {
        return $this->status->isPosted()
            && $this->due_date !== null
            && $this->due_date->isPast()
            && bccomp($this->balance(), '0', 2) > 0;
    }
}
