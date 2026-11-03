<?php

namespace App\Services\Fiscal;

use App\Models\Invoice;

/** No-op fiscal provider — returns data from fiscal columns when present, null otherwise. */
final class NullFiscalProvider implements FiscalProvider
{
    public function getData(Invoice $invoice): ?array
    {
        if ($invoice->fiscal_number === null) {
            return null;
        }

        return [
            'number' => $invoice->fiscal_number,
            'verification_code' => (string) $invoice->fiscal_verification_code,
            'qr_payload' => $invoice->fiscal_qr_payload,
            'status' => (string) $invoice->fiscal_status,
            'submitted_at' => $invoice->fiscal_submitted_at
                ? $invoice->fiscal_submitted_at->toDateTimeString()
                : '',
        ];
    }
}
