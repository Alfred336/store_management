<?php

namespace App\Exports\Reports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PosSessionsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly string $dateFrom,
        private readonly string $dateTo,
        private readonly ?int $locationId = null,
    ) {}

    /** @return Collection<int, \stdClass> */
    public function collection(): Collection
    {
        $start = Carbon::parse($this->dateFrom)->startOfDay()->toDateTimeString();
        $end = Carbon::parse($this->dateTo)->addDay()->startOfDay()->toDateTimeString();

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
                'users.name AS cashier_name',
                'locations.name AS location_name',
                'closed_by_user.name AS closed_by_name',
            ])
            ->where('pos_sessions.opened_at', '>=', $start)
            ->where('pos_sessions.opened_at', '<', $end)
            ->when($this->locationId !== null, fn ($q) => $q->where('pos_sessions.location_id', $this->locationId))
            ->orderBy('pos_sessions.opened_at', 'desc')
            ->get();
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['id', 'location', 'cashier', 'opened_at', 'closed_at', 'opening_cash', 'expected_cash', 'closing_cash_counted', 'difference', 'status', 'closed_by'];
    }

    /** @param \stdClass $row */
    public function map($row): array
    {
        return [
            $row->id,
            $this->safeText($row->location_name),
            $this->safeText($row->cashier_name),
            $row->opened_at,
            $row->closed_at ?? '',
            number_format((float) $row->opening_cash, 2, '.', ''),
            $row->expected_cash !== null ? number_format((float) $row->expected_cash, 2, '.', '') : '',
            $row->closing_cash_counted !== null ? number_format((float) $row->closing_cash_counted, 2, '.', '') : '',
            $row->difference !== null ? number_format((float) $row->difference, 2, '.', '') : '',
            $row->status,
            $this->safeText($row->closed_by_name ?? ''),
        ];
    }

    private function safeText(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
