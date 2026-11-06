<?php

namespace App\Exports\Reports;

use App\Enums\InvoiceStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VatSummaryExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly string $dateFrom,
        private readonly string $dateTo,
        private readonly ?int $locationId = null,
    ) {}

    /** @return Collection<int, \stdClass> */
    public function collection(): Collection
    {
        $posted = [
            InvoiceStatus::Issued->value,
            InvoiceStatus::PartiallyPaid->value,
            InvoiceStatus::Paid->value,
        ];

        return DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->selectRaw("DATE_TRUNC('day', invoices.issue_date) AS period")
            ->selectRaw("SUM(CASE WHEN invoice_items.tax_type = 'standard' THEN invoice_items.line_total - invoice_items.line_tax ELSE 0 END) AS taxable_amount")
            ->selectRaw("SUM(CASE WHEN invoice_items.tax_type != 'standard' THEN invoice_items.line_total ELSE 0 END) AS exempt_amount")
            ->selectRaw('SUM(invoice_items.line_tax) AS vat_amount')
            ->selectRaw('SUM(invoice_items.line_total) AS gross_total')
            ->whereIn('invoices.status', $posted)
            ->whereNotNull('invoices.issue_date')
            ->whereBetween('invoices.issue_date', [$this->dateFrom, $this->dateTo])
            ->when($this->locationId !== null, fn ($q) => $q->where('invoices.location_id', $this->locationId))
            ->groupByRaw("DATE_TRUNC('day', invoices.issue_date)")
            ->orderByRaw("DATE_TRUNC('day', invoices.issue_date)")
            ->get();
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['date', 'taxable_amount', 'exempt_amount', 'vat_amount', 'gross_total'];
    }

    /** @param \stdClass $row */
    public function map($row): array
    {
        return [
            Carbon::parse($row->period)->toDateString(),
            number_format((float) $row->taxable_amount, 2, '.', ''),
            number_format((float) $row->exempt_amount, 2, '.', ''),
            number_format((float) $row->vat_amount, 2, '.', ''),
            number_format((float) $row->gross_total, 2, '.', ''),
        ];
    }
}
