<?php

namespace App\Enums;

/** Lifecycle states of a quotation. "Expired" is computed (sent + valid_until < today), never stored. */
enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Converted => 'Converted',
        };
    }

    /** A sent quotation whose valid_until is in the past is considered expired. */
    public function isImmutable(): bool
    {
        return $this !== self::Draft;
    }

    /** Statuses from which a revision can be created. */
    public function canRevise(): bool
    {
        return $this === self::Sent || $this === self::Rejected;
    }

    /** Statuses from which the quotation can be converted to an invoice (not expired). */
    public function canConvert(): bool
    {
        return $this === self::Sent || $this === self::Accepted;
    }
}
