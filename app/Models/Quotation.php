<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A quotation / sales proposal document.
 *
 * Written only through Quotation Actions.
 * "Expired" is computed from status=sent AND valid_until < today; it is never stored.
 *
 * @property int $id
 * @property string|null $number
 * @property QuotationStatus $status
 * @property int|null $customer_id
 * @property string|null $prospect_name
 * @property string|null $prospect_phone
 * @property string|null $prospect_email
 * @property string|null $prospect_address
 * @property int $location_id
 * @property CarbonImmutable $quote_date
 * @property CarbonImmutable $valid_until
 * @property string $currency
 * @property bool $prices_include_tax
 * @property numeric-string $subtotal
 * @property numeric-string $discount_total
 * @property numeric-string $tax_total
 * @property numeric-string $total
 * @property string|null $notes
 * @property string|null $terms
 * @property int $revision
 * @property int|null $revised_from_id
 * @property int|null $converted_invoice_id
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property string|null $customer_address
 * @property int|null $created_by
 * @property int|null $sent_by
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $rejected_at
 * @property string|null $reject_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Quotation extends Model
{
    protected $fillable = [
        'number', 'status',
        'customer_id', 'prospect_name', 'prospect_phone', 'prospect_email', 'prospect_address',
        'location_id', 'quote_date', 'valid_until', 'currency', 'prices_include_tax',
        'subtotal', 'discount_total', 'tax_total', 'total',
        'notes', 'terms',
        'revision', 'revised_from_id', 'converted_invoice_id',
        'customer_name', 'customer_phone', 'customer_address',
        'created_by', 'sent_by', 'sent_at', 'accepted_at', 'rejected_at', 'reject_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'quote_date' => 'date',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'prices_include_tax' => 'boolean',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'revision' => 'integer',
        ];
    }

    // ─── Computed ─────────────────────────────────────────────────────────

    /**
     * A quotation is expired when it is in "sent" state and valid_until is in the past.
     * This is never stored; computed on demand.
     */
    public function isExpired(): bool
    {
        return $this->status === QuotationStatus::Sent
            && $this->valid_until->isPast();
    }

    /** Effective display status includes the computed "expired" state. */
    public function displayStatus(): string
    {
        if ($this->isExpired()) {
            return 'expired';
        }

        return $this->status->value;
    }

    public function displayStatusLabel(): string
    {
        if ($this->isExpired()) {
            return 'Expired';
        }

        return $this->status->label();
    }

    // ─── Relations ────────────────────────────────────────────────────────

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

    /** @return HasMany<QuotationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    /** @return BelongsTo<Quotation, $this> */
    public function revisedFrom(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'revised_from_id');
    }

    /** @return HasMany<Quotation, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(Quotation::class, 'revised_from_id');
    }

    /** @return BelongsTo<Invoice, $this> */
    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** The effective customer display name (registered or prospect). */
    public function recipientName(): string
    {
        return $this->customer->name ?? $this->prospect_name ?? '—';
    }
}
