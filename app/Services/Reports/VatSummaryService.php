<?php

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** VAT Summary: standard vs exempt lines from posted invoices by issue_date. */
class VatSummaryService
{
    /**
     * @return array{rows: Collection<int, \stdClass>, totals: array{taxable_amount: string, exempt_amount: string, vat_amount: string, gross_total: string}}
     */
    public function get(Carbon $from, Carbon $to, ?int $locationId = null): array
    {
        $posted = [
            InvoiceStatus::Issued->value,
            InvoiceStatus::PartiallyPaid->value,
            InvoiceStatus::Paid->value,
        ];

        $rows = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->selectRaw("DATE_TRUNC('day', invoices.issue_date) AS period")
            ->selectRaw("SUM(CASE WHEN invoice_items.tax_type = 'standard' THEN invoice_items.line_total - invoice_items.line_tax ELSE 0 END) AS taxable_amount")
            ->selectRaw("SUM(CASE WHEN invoice_items.tax_type != 'standard' THEN invoice_items.line_total ELSE 0 END) AS exempt_amount")
            ->selectRaw('SUM(invoice_items.line_tax) AS vat_amount')
            ->selectRaw('SUM(invoice_items.line_total) AS gross_total')
            ->whereIn('invoices.status', $posted)
            ->whereNotNull('invoices.issue_date')
            ->whereBetween('invoices.issue_date', [$from->toDateString(), $to->toDateString()])
            ->when($locationId !== null, fn ($q) => $q->where('invoices.location_id', $locationId))
            ->groupByRaw("DATE_TRUNC('day', invoices.issue_date)")
            ->orderByRaw("DATE_TRUNC('day', invoices.issue_date)")
            ->get();

        $totals = [
            'taxable_amount' => number_format((float) $rows->sum('taxable_amount'), 2),
            'exempt_amount' => number_format((float) $rows->sum('exempt_amount'), 2),
            'vat_amount' => number_format((float) $rows->sum('vat_amount'), 2),
            'gross_total' => number_format((float) $rows->sum('gross_total'), 2),
        ];

        return ['rows' => $rows, 'totals' => $totals];
    }
}
