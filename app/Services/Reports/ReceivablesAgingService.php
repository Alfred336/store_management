<?php

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Receivables Aging: outstanding balances bucketed as
 * Current (0–30 days), 31–60 days, 61–90 days, 90+ days
 * measured from the given as-of date.
 */
class ReceivablesAgingService
{
    /**
     * @return array{rows: Collection<int, \stdClass>, totals: array{current: string, days_31_60: string, days_61_90: string, days_90_plus: string, total: string}}
     */
    public function get(Carbon $asOf): array
    {
        $open = [
            InvoiceStatus::Issued->value,
            InvoiceStatus::PartiallyPaid->value,
        ];

        $rows = DB::table('invoices')
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->selectRaw('customers.id AS customer_id')
            ->selectRaw('customers.name AS customer_name')
            ->selectRaw('customers.code AS customer_code')
            ->selectRaw(
                'SUM(CASE WHEN due_date >= ? OR due_date IS NULL THEN (invoices.total - invoices.amount_paid) ELSE 0 END) AS current_amount',
                [$asOf->toDateString()]
            )
            ->selectRaw(
                'SUM(CASE WHEN due_date < ? AND due_date >= ? THEN (invoices.total - invoices.amount_paid) ELSE 0 END) AS days_31_60',
                [$asOf->toDateString(), $asOf->copy()->subDays(60)->toDateString()]
            )
            ->selectRaw(
                'SUM(CASE WHEN due_date < ? AND due_date >= ? THEN (invoices.total - invoices.amount_paid) ELSE 0 END) AS days_61_90',
                [$asOf->copy()->subDays(30)->toDateString(), $asOf->copy()->subDays(90)->toDateString()]
            )
            ->selectRaw(
                'SUM(CASE WHEN due_date < ? THEN (invoices.total - invoices.amount_paid) ELSE 0 END) AS days_90_plus',
                [$asOf->copy()->subDays(90)->toDateString()]
            )
            ->selectRaw('SUM(invoices.total - invoices.amount_paid) AS total_outstanding')
            ->whereIn('invoices.status', $open)
            ->where('customers.is_active', true)
            ->groupBy('customers.id', 'customers.name', 'customers.code')
            ->havingRaw('SUM(invoices.total - invoices.amount_paid) > 0')
            ->orderBy('customers.name')
            ->get();

        $totals = [
            'current' => number_format((float) $rows->sum('current_amount'), 2),
            'days_31_60' => number_format((float) $rows->sum('days_31_60'), 2),
            'days_61_90' => number_format((float) $rows->sum('days_61_90'), 2),
            'days_90_plus' => number_format((float) $rows->sum('days_90_plus'), 2),
            'total' => number_format((float) $rows->sum('total_outstanding'), 2),
        ];

        return ['rows' => $rows, 'totals' => $totals];
    }
}
