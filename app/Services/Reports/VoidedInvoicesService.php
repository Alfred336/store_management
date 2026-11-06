<?php

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** Voided Invoices Audit: invoices voided within the date range. */
class VoidedInvoicesService
{
    /** @return LengthAwarePaginator<int, \stdClass> */
    public function paginate(Carbon $from, Carbon $to, ?int $locationId = null, int $perPage = 50): LengthAwarePaginator
    {
        // Convert EAT boundaries to UTC for half-open range comparison against
        // voided_at (timestamp without time zone, stored in UTC).
        $startUtc = $from->copy()->utc()->toDateTimeString();
        $endUtc = $to->copy()->addSecond()->utc()->toDateTimeString();

        return DB::table('invoices')
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->join('locations', 'locations.id', '=', 'invoices.location_id')
            ->leftJoin('users AS issued_user', 'issued_user.id', '=', 'invoices.issued_by')
            ->leftJoin('users AS voided_user', 'voided_user.id', '=', 'invoices.voided_by')
            ->select([
                'invoices.id',
                'invoices.number',
                'invoices.channel',
                'invoices.issue_date',
                'invoices.voided_at',
                'invoices.void_reason',
                'invoices.total',
                'customers.name AS customer_name',
                'customers.code AS customer_code',
                'locations.name AS location_name',
                'issued_user.name AS issued_by_name',
                'voided_user.name AS voided_by_name',
            ])
            ->where('invoices.status', InvoiceStatus::Void->value)
            ->whereNotNull('invoices.voided_at')
            ->where('invoices.voided_at', '>=', $startUtc)
            ->where('invoices.voided_at', '<', $endUtc)
            ->when($locationId !== null, fn ($q) => $q->where('invoices.location_id', $locationId))
            ->orderBy('invoices.voided_at', 'desc')
            ->paginate($perPage);
    }
}
