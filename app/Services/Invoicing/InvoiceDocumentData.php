<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use App\Services\Fiscal\FiscalProvider;
use App\Services\Settings\Settings;

/**
 * Builds the view-model array consumed by every invoice print template.
 * No queries are executed in Blade — all data is prepared here.
 */
class InvoiceDocumentData
{
    public function __construct(
        private readonly Settings $settings,
        private readonly FiscalProvider $fiscal,
    ) {}

    /**
     * Build the complete view-model for a template render.
     *
     * @return array<string, mixed>
     */
    public function build(Invoice $invoice, bool $isDuplicate = false): array
    {
        return [
            'company' => $this->buildCompany(),
            'inv' => $this->buildHeader($invoice),
            'customer' => $this->buildCustomer($invoice),
            'lines' => $this->buildLines($invoice),
            'totals' => $this->buildTotals($invoice),
            'fiscal' => $this->fiscal->getData($invoice),
            'watermark' => $this->resolveWatermark($invoice, $isDuplicate),
            'notes' => $invoice->notes,
            'terms' => $invoice->terms,
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
    private function buildHeader(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number ?? 'DRAFT',
            'channel' => $invoice->channel,
            'status' => $invoice->status->label(),
            'status_value' => $invoice->status->value,
            'location' => $invoice->location->name,
            'issue_date' => $invoice->issue_date?->format('d M Y') ?? '',
            'due_date' => $invoice->due_date?->format('d M Y') ?? '',
            'issued_at' => $invoice->issued_at?->format('d M Y H:i') ?? '',
            'void_reason' => $invoice->void_reason,
        ];
    }

    /**
     * Always uses the customer SNAPSHOT stored on the invoice (never live customer data)
     * so that issued invoice prints are immutable.
     * For drafts the snapshot is empty; use live data from the invoice's customer relation.
     *
     * @return array<string, mixed>
     */
    private function buildCustomer(Invoice $invoice): array
    {
        if ($invoice->customer_name !== null) {
            // Issued invoice — use immutable snapshot
            return [
                'name' => $invoice->customer_name,
                'phone' => $invoice->customer_phone ?? '',
                'address' => $invoice->customer_address ?? '',
                'tin' => $invoice->customer_tin ?? '',
                'vrn' => $invoice->customer_vrn ?? '',
            ];
        }

        // Draft — use live customer relation (snapshot not yet written)
        $c = $invoice->customer;

        return [
            'name' => $c->name ?? '',
            'phone' => $c->phone ?? '',
            'address' => $c->address ?? '',
            'tin' => $c->tin ?? '',
            'vrn' => $c->vrn ?? '',
        ];
    }

    /**
     * @return list<array{
     *   description: string,
     *   sku: string,
     *   quantity: string,
     *   unit: string,
     *   unit_price: string,
     *   discount: string,
     *   tax_rate: string,
     *   line_tax: string,
     *   line_total: string,
     * }>
     */
    private function buildLines(Invoice $invoice): array
    {
        $lines = [];
        foreach ($invoice->items as $item) {
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
    private function buildTotals(Invoice $invoice): array
    {
        $currency = $invoice->currency;
        $total = (string) $invoice->total;

        return [
            'subtotal' => number_format((float) $invoice->subtotal, 2),
            'discount_total' => number_format((float) $invoice->discount_total, 2),
            'tax_total' => number_format((float) $invoice->tax_total, 2),
            'total' => number_format((float) $total, 2),
            'amount_paid' => number_format((float) $invoice->amount_paid, 2),
            'balance' => number_format((float) $invoice->balance(), 2),
            'amount_in_words' => $this->amountInWords($total, $currency),
        ];
    }

    private function resolveWatermark(Invoice $invoice, bool $isDuplicate): ?string
    {
        if ($invoice->status->value === 'draft') {
            return 'DRAFT';
        }
        if ($invoice->status->value === 'void') {
            return 'VOID';
        }
        if ($isDuplicate) {
            return 'DUPLICATE';
        }

        return null;
    }

    // ─── Amount in words ──────────────────────────────────────────────────

    public function amountInWords(string $amount, string $currency = 'TZS'): string
    {
        $parts = explode('.', number_format((float) $amount, 2, '.', ''));
        $whole = (int) $parts[0];
        $cents = (int) $parts[1];

        $wholeWords = $this->intToWords($whole);

        [$unit, $subunit] = match ($currency) {
            'TZS' => ['Shilling', 'Cent'],
            'USD' => ['Dollar', 'Cent'],
            'EUR' => ['Euro', 'Cent'],
            'GBP' => ['Pound', 'Penny'],
            default => ['Unit', 'Subunit'],
        };

        $plural = static fn (int $n, string $w): string => $n === 1 ? $w : $w.'s';

        $result = ucfirst($wholeWords).' '.$plural($whole, $unit);
        if ($cents > 0) {
            $result .= ' and '.ucfirst($this->intToWords($cents)).' '.$plural($cents, $subunit);
        } else {
            $result .= ' Only';
        }

        return $result;
    }

    private function intToWords(int $n): string
    {
        if ($n <= 0) {
            return 'zero';
        }

        $ones = [
            '', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
            'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
            'seventeen', 'eighteen', 'nineteen',
        ];
        $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

        if ($n < 20) {
            return $ones[$n];
        }

        if ($n < 100) {
            return $tens[intdiv($n, 10)].($n % 10 !== 0 ? '-'.$ones[$n % 10] : '');
        }

        if ($n < 1_000) {
            $rem = $n % 100;

            return $ones[intdiv($n, 100)].' hundred'.($rem !== 0 ? ' and '.$this->intToWords($rem) : '');
        }

        if ($n < 1_000_000) {
            $rem = $n % 1_000;

            return $this->intToWords(intdiv($n, 1_000)).' thousand'.($rem !== 0 ? ' '.$this->intToWords($rem) : '');
        }

        if ($n < 1_000_000_000) {
            $rem = $n % 1_000_000;

            return $this->intToWords(intdiv($n, 1_000_000)).' million'.($rem !== 0 ? ' '.$this->intToWords($rem) : '');
        }

        $rem = $n % 1_000_000_000;

        return $this->intToWords(intdiv($n, 1_000_000_000)).' billion'.($rem !== 0 ? ' '.$this->intToWords($rem) : '');
    }
}
