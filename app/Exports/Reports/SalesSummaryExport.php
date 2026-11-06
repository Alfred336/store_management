<?php

namespace App\Exports\Reports;

use App\Enums\InvoiceStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalesSummaryExport implements FromCollection, WithHeadings, WithMapping
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

        return DB::table('invoices')
            ->selectRaw("DATE_TRUNC('day', issue_date) AS period")
            ->selectRaw('COUNT(*) AS invoice_count')
            ->selectRaw('SUM(subtotal) AS subtotal')
            ->selectRaw('SUM(discount_total) AS discount_total')
            ->selectRaw('SUM(tax_total) AS tax_total')
            ->selectRaw('SUM(total) AS total')
            ->whereIn('status', $posted)
            ->whereNotNull('issue_date')
            ->whereBetween('issue_date', [$this->dateFrom, $this->dateTo])
            ->when($this->locationId !== null, fn ($q) => $q->where('location_id', $this->locationId))
            ->groupByRaw("DATE_TRUNC('day', issue_date)")
            ->orderByRaw("DATE_TRUNC('day', issue_date)")
            ->get();
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['date', 'invoice_count', 'subtotal', 'discount_total', 'tax_total', 'total'];
    }

    /** @param \stdClass $row */
    public function map($row): array
    {
        return [
            Carbon::parse($row->period)->toDateString(),
            (int) $row->invoice_count,
            number_format((float) $row->subtotal, 2, '.', ''),
            number_format((float) $row->discount_total, 2, '.', ''),
            number_format((float) $row->tax_total, 2, '.', ''),
            number_format((float) $row->total, 2, '.', ''),
        ];
    }
}
