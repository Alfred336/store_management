<?php

namespace App\Services\Quotations;

use App\Models\Quotation;
use App\Services\Invoicing\InvoiceDocumentData;
use App\Services\Settings\Settings;

/**
 * Builds the view-model array consumed by every quotation print template.
 * Mirrors InvoiceDocumentData but adapted for quotation fields.
 * No DB queries execute inside Blade — all data prepared here.
 */
class QuotationDocumentData
{
    public function __construct(
        private readonly Settings $settings,
        private readonly InvoiceDocumentData $invoiceDocumentData,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Quotation $quotation): array
    {
        return [
            'company' => $this->buildCompany(),
            'quot' => $this->buildHeader($quotation),
            'customer' => $this->buildRecipient($quotation),
            'lines' => $this->buildLines($quotation),
            'totals' => $this->buildTotals($quotation),
            'watermark' => $this->resolveWatermark($quotation),
            'notes' => $quotation->notes,
            'terms' => $quotation->terms,
        ];
    }

    // ─── Sections ─────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function buildCompany(): array
    {
        $logoPath = (string) $this->settings->get('business.logo_path', '');
        $logoData = null;
        if ($logoPath !== '' && is_file($logoPath) && is_readable($logoPath)) {
            $mime = mime_content_type($logoPath) ?: 'image/png';
            $logoData = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($logoPath));
        }

        return [
            'name' => (string) $this->settings->get('business.name', ''),
            'email' => (string) $this->settings->get('business.email', ''),
            'phone' => (string) $this->settings->get('business.phone', ''),
            'address' => (string) $this->settings->get('business.address', ''),
            'tin' => (string) $this->settings->get('business.tin', ''),
            'vrn' => (string) $this->settings->get('business.vrn', ''),
            'bank_details' => (string) $this->settings->get('business.bank_details', ''),
            'footer_terms' => (string) $this->settings->get('business.footer_terms', ''),
            'currency' => (string) $this->settings->get('business.currency', 'TZS'),
            'logo' => $logoData,
        ];
    }

    /** @return array<string, mixed> */
    private function buildHeader(Quotation $quotation): array
    {
        return [
            'id' => $quotation->id,
            'number' => $quotation->number ?? 'DRAFT',
            'status' => $quotation->displayStatusLabel(),
            'status_value' => $quotation->displayStatus(),
            'location' => $quotation->location->name,
            'quote_date' => $quotation->quote_date->format('d M Y'),
            'valid_until' => $quotation->valid_until->format('d M Y'),
            'revision' => $quotation->revision,
        ];
    }

    /**
     * Uses the customer SNAPSHOT after sending, or live prospect/customer data for drafts.
     *
     * @return array<string, mixed>
     */
    private function buildRecipient(Quotation $quotation): array
    {
        if ($quotation->customer_name !== null) {
            // Sent — use immutable snapshot.
            return [
                'name' => $quotation->customer_name,
                'phone' => $quotation->customer_phone ?? '',
                'address' => $quotation->customer_address ?? '',
                'email' => $quotation->customer->email ?? $quotation->prospect_email ?? '',
            ];
        }

        // Draft — use live data.
        if ($quotation->customer_id !== null && $quotation->customer !== null) {
            $c = $quotation->customer;

            return [
                'name' => $c->name ?? '',
                'phone' => $c->phone ?? '',
                'address' => $c->address ?? '',
                'email' => $c->email ?? '',
            ];
        }

        return [
            'name' => $quotation->prospect_name ?? '',
            'phone' => $quotation->prospect_phone ?? '',
            'address' => $quotation->prospect_address ?? '',
            'email' => $quotation->prospect_email ?? '',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildLines(Quotation $quotation): array
    {
        $lines = [];
        foreach ($quotation->items as $item) {
            $lines[] = [
                'description' => $item->description,
                'sku' => $item->sku ?? '',
                'quantity' => rtrim(rtrim((string) $item->quantity, '0'), '.'),
                'unit' => $item->unit->abbreviation,
                'unit_price' => number_format((float) $item->unit_price, 2),
                'discount' => number_format((float) $item->discount_amount, 2),
                'tax_rate' => number_format((float) $item->tax_rate, 2),
                'line_tax' => number_format((float) $item->line_tax, 2),
                'line_total' => number_format((float) $item->line_total, 2),
            ];
        }

        return $lines;
    }

    /** @return array<string, string> */
    private function buildTotals(Quotation $quotation): array
    {
        $currency = $quotation->currency;
        $total = (string) $quotation->total;

        return [
            'subtotal' => number_format((float) $quotation->subtotal, 2),
            'discount_total' => number_format((float) $quotation->discount_total, 2),
            'tax_total' => number_format((float) $quotation->tax_total, 2),
            'total' => number_format((float) $total, 2),
            'amount_in_words' => $this->invoiceDocumentData->amountInWords($total, $currency),
        ];
    }

    private function resolveWatermark(Quotation $quotation): ?string
    {
        if ($quotation->status->value === 'draft') {
            return 'DRAFT';
        }
        if ($quotation->isExpired()) {
            return 'EXPIRED';
        }

        return null;
    }
}
