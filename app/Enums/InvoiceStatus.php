<?php

namespace App\Enums;

/** Lifecycle states of an invoice. "Overdue" is computed, never stored. */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Void => 'Void',
        };
    }

    /** Issued, partially paid and paid invoices can no longer be edited. */
    public function isPosted(): bool
    {
        return $this !== self::Draft && $this !== self::Void;
    }
}
