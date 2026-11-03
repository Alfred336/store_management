<?php

namespace App\Services\Fiscal;

use App\Models\Invoice;

/**
 * Extension point for a future TRA fiscal integration.
 * The null implementation is bound by default; a real integration
 * replaces it via AppServiceProvider without touching calling code.
 *
 * NOTE: TRA integration is pending a confirmed specification.
 * Do not fake any fiscal data — leave fiscal columns null until
 * an authorised integration populates them.
 */
interface FiscalProvider
{
    /**
     * Return fiscal data for an invoice, or null when no fiscal
     * submission has been recorded.
     *
     * @return array{
     *   number: string,
     *   verification_code: string,
     *   qr_payload: string|null,
     *   status: string,
     *   submitted_at: string,
     * }|null
     */
    public function getData(Invoice $invoice): ?array;
}
