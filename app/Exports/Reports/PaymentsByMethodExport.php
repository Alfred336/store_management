<?php

namespace App\Exports\Reports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaymentsByMethodExport implements FromCollection, WithHeadings, WithMapping
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

        return DB::table('payments')
            ->selectRaw('payments.method')
            ->selectRaw('COUNT(*) AS payment_count')
            ->selectRaw('SUM(payments.amount) AS amount')
            ->where('payments.status', 'active')
            ->where('payments.received_at', '>=', $start)
            ->where('payments.received_at', '<', $end)
            ->when($this->locationId !== null, fn ($q) => $q->whereExists(function ($sub): void {
                $sub->from('payment_allocations')
                    ->join('invoices', 'invoices.id', '=', 'payment_allocations.invoice_id')
                    ->whereColumn('payment_allocations.payment_id', 'payments.id')
                    ->where('invoices.location_id', $this->locationId);
            }))
            ->groupBy('payments.method')
            ->orderBy('payments.method')
            ->get();
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['method', 'payment_count', 'amount'];
    }

    /** @param \stdClass $row */
    public function map($row): array
    {
        return [
            $row->method,
            (int) $row->payment_count,
            number_format((float) $row->amount, 2, '.', ''),
        ];
    }
}
