<?php

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sales Summary: aggregate posted invoices (issued, partially_paid, paid) by issue_date.
 * Groups rows by day for ranges ≤ 31 days, by month otherwise.
 */
class SalesSummaryService
{
    /**
     * @return array{rows: Collection<int, \stdClass>, totals: array{subtotal: string, discount_total: string, tax_total: string, total: string}, group_by: string}
     */
    public function get(Carbon $from, Carbon $to, ?int $locationId = null): array
    {
        $days = $from->diffInDays($to);
        $groupBy = $days <= 31 ? 'day' : 'month';

        $posted = [
            InvoiceStatus::Issued->value,
            InvoiceStatus::PartiallyPaid->value,
            InvoiceStatus::Paid->value,
        ];

        $dateTrunc = $groupBy === 'day'
            ? "DATE_TRUNC('day', issue_date)"
            : "DATE_TRUNC('month', issue_date)";

        $query = DB::table('invoices')
            ->selectRaw("{$dateTrunc} AS period")
            ->selectRaw('COUNT(*) AS invoice_count')
            ->selectRaw('SUM(subtotal) AS subtotal')
            ->selectRaw('SUM(discount_total) AS discount_total')
            ->selectRaw('SUM(tax_total) AS tax_total')
            ->selectRaw('SUM(total) AS total')
            ->whereIn('status', $posted)
            ->whereNotNull('issue_date')
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->when($locationId !== null, fn ($q) => $q->where('location_id', $locationId))
            ->groupByRaw($dateTrunc)
            ->orderByRaw($dateTrunc);

        $rows = $query->get();

        $totals = [
            'subtotal' => number_format((float) $rows->sum('subtotal'), 2),
            'discount_total' => number_format((float) $rows->sum('discount_total'), 2),
            'tax_total' => number_format((float) $rows->sum('tax_total'), 2),
            'total' => number_format((float) $rows->sum('total'), 2),
        ];

        return ['rows' => $rows, 'totals' => $totals, 'group_by' => $groupBy];
    }
}
