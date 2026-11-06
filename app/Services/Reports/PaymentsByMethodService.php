<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Payments by Method: total received, grouped by payment method. */
class PaymentsByMethodService
{
    /**
     * @return array{rows: Collection<int, \stdClass>, totals: array{amount: string, payment_count: int}}
     */
    public function get(Carbon $from, Carbon $to, ?int $locationId = null): array
    {
        // Convert EAT boundaries to UTC for half-open range comparison against
        // the timestamp column (stored in UTC, app.timezone = UTC).
        $startUtc = $from->copy()->utc()->toDateTimeString();
        $endUtc = $to->copy()->addSecond()->utc()->toDateTimeString();

        $rows = DB::table('payments')
            ->selectRaw('payments.method')
            ->selectRaw('COUNT(*) AS payment_count')
            ->selectRaw('SUM(payments.amount) AS amount')
            ->where('payments.status', 'active')
            ->where('payments.received_at', '>=', $startUtc)
            ->where('payments.received_at', '<', $endUtc)
            ->when($locationId !== null, fn ($q) => $q->whereExists(function ($sub) use ($locationId): void {
                // A payment's location is determined by the invoices it was allocated to.
                // This covers both back-office and POS payments uniformly.
                $sub->from('payment_allocations')
                    ->join('invoices', 'invoices.id', '=', 'payment_allocations.invoice_id')
                    ->whereColumn('payment_allocations.payment_id', 'payments.id')
                    ->where('invoices.location_id', $locationId);
            }))
            ->groupBy('payments.method')
            ->orderBy('payments.method')
            ->get();

        $totals = [
            'amount' => number_format((float) $rows->sum('amount'), 2),
            'payment_count' => (int) $rows->sum('payment_count'),
        ];

        return ['rows' => $rows, 'totals' => $totals];
    }
}
