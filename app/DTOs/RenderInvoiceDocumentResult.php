<?php

namespace App\DTOs;

/** Returned by RenderInvoiceDocument. */
final readonly class RenderInvoiceDocumentResult
{
    public function __construct(
        /** Raw PDF bytes. */
        public string $bytes,
        /** Whether this print is a duplicate (another document was already recorded). */
        public bool $isDuplicate,
        /** ID of the recorded invoice_documents row; null for drafts/void (not persisted). */
        public ?int $documentId,
    ) {}
}
