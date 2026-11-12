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
        // Half-open range against opened_at (stored in app local time).
        $start = $from->toDateTimeString();
        $end = $to->copy()->addSecond()->toDateTimeString();

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
            ->where('pos_sessions.opened_at', '>=', $start)
            ->where('pos_sessions.opened_at', '<', $end)
            ->when($locationId !== null, fn ($q) => $q->where('pos_sessions.location_id', $locationId))
            ->orderBy('pos_sessions.opened_at', 'desc')
            ->paginate($perPage);
    }
}
