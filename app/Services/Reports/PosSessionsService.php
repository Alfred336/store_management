<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** POS Sessions: closed sessions with cash reconciliation summary. */
class PosSessionsService
{
    /** @return LengthAwarePaginator<int, \stdClass> */
    public function paginate(Carbon $from, Carbon $to, ?int $locationId = null, int $perPage = 50): LengthAwarePaginator
    {
        // Convert EAT boundaries to UTC for half-open range comparison against
        // opened_at (timestamp without time zone, stored in UTC).
        $startUtc = $from->copy()->utc()->toDateTimeString();
        $endUtc = $to->copy()->addSecond()->utc()->toDateTimeString();

        return DB::table('pos_sessions')
            ->join('users', 'users.id', '=', 'pos_sessions.user_id')
            ->join('locations', 'locations.id', '=', 'pos_sessions.location_id')
            ->leftJoin('users AS closed_by_user', 'closed_by_user.id', '=', 'pos_sessions.closed_by')
            ->select([
                'pos_sessions.id',
                'pos_sessions.opened_at',
                'pos_sessions.closed_at',
                'pos_sessions.opening_cash',
                'pos_sessions.closing_cash_counted',
                'pos_sessions.expected_cash',
                'pos_sessions.difference',
                'pos_sessions.status',
                'pos_sessions.notes',
                'users.name AS cashier_name',
                'locations.name AS location_name',
                'closed_by_user.name AS closed_by_name',
            ])
            ->where('pos_sessions.opened_at', '>=', $startUtc)
            ->where('pos_sessions.opened_at', '<', $endUtc)
            ->when($locationId !== null, fn ($q) => $q->where('pos_sessions.location_id', $locationId))
            ->orderBy('pos_sessions.opened_at', 'desc')
            ->paginate($perPage);
    }
}
