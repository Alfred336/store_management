<?php

namespace App\DTOs;

use App\Models\Invoice;

final readonly class IssueInvoiceResult
{
    public function __construct(
        public Invoice $invoice,
        public ?string $creditWarning = null,
    ) {}
}
