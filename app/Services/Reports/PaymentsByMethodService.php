<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Payments by Method: total received, grouped by payment method. */
class PaymentsByMethodService
{
    /**
     * @param  list<int>|null  $allowedIds  null = unrestricted; [] = no access
     * @return array{rows: Collection<int, \stdClass>, totals: array{amount: string, payment_count: int}}
     */
    public function get(Carbon $from, Carbon $to, ?int $locationId = null, ?array $allowedIds = null): array
    {
        // Half-open range against received_at (stored in app local time).
        $start = $from->toDateTimeString();
        $end = $to->copy()->addSecond()->toDateTimeString();

        $query = DB::table('payments')
            ->selectRaw('payments.method')
            ->selectRaw('COUNT(*) AS payment_count')
            ->selectRaw('SUM(payments.amount) AS amount')
            ->where('payments.status', 'active')
            ->where('payments.received_at', '>=', $start)
            ->where('payments.received_at', '<', $end)
            ->when($locationId !== null, fn ($q) => $q->where('payments.location_id', $locationId))
            ->groupBy('payments.method')
            ->orderBy('payments.method');

        if ($allowedIds !== null) {
            empty($allowedIds)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('payments.location_id', $allowedIds);
        }

        $rows = $query->get();

        $totals = [
            'amount' => number_format((float) $rows->sum('amount'), 2),
            'payment_count' => (int) $rows->sum('payment_count'),
        ];

        return ['rows' => $rows, 'totals' => $totals];
    }
}
