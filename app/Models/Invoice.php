<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Single sales document for back-office invoices and POS receipts
 * (see the "channel" column). Written only through invoicing Actions.
 */
class Invoice extends Model
{
    protected $fillable = [
        'number', 'channel', 'status', 'customer_id', 'location_id',
        'issue_date', 'due_date', 'currency', 'prices_include_tax',
        'subtotal', 'discount_total', 'tax_total', 'total', 'amount_paid',
        'notes', 'terms', 'template_key',
        'customer_name', 'customer_phone', 'customer_address', 'customer_tin', 'customer_vrn',
        'idempotency_key', 'created_by', 'issued_by', 'issued_at',
        'voided_by', 'voided_at', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
            'prices_include_tax' => 'boolean',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** Amount still owed. Computed from stored values with bcmath, never stored. */
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
