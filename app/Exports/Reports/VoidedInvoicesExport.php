<?php

namespace App\Exports\Reports;

use App\Enums\InvoiceStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VoidedInvoicesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly string $dateFrom,
        private readonly string $dateTo,
        private readonly ?int $locationId = null,
    ) {}

    /** @return Collection<int, \stdClass> */
    public function collection(): Collection
    {
        $tz = 'Africa/Dar_es_Salaam';
        $startUtc = Carbon::parse($this->dateFrom, $tz)->startOfDay()->utc()->toDateTimeString();
        $endUtc = Carbon::parse($this->dateTo, $tz)->addDay()->startOfDay()->utc()->toDateTimeString();

        return DB::table('invoices')
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->join('locations', 'locations.id', '=', 'invoices.location_id')
            ->leftJoin('users AS issued_user', 'issued_user.id', '=', 'invoices.issued_by')
            ->leftJoin('users AS voided_user', 'voided_user.id', '=', 'invoices.voided_by')
            ->select([
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
            ->when($this->locationId !== null, fn ($q) => $q->where('invoices.location_id', $this->locationId))
            ->orderBy('invoices.voided_at', 'desc')
            ->get();
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['number', 'channel', 'issue_date', 'voided_at', 'customer_code', 'customer_name', 'location', 'total', 'issued_by', 'voided_by', 'void_reason'];
    }

    /** @param \stdClass $row */
    public function map($row): array
    {
        return [
            $this->safeText($row->number ?? ''),
            $row->channel,
            $row->issue_date ?? '',
            $row->voided_at ?? '',
            $this->safeText($row->customer_code),
            $this->safeText($row->customer_name),
            $this->safeText($row->location_name),
            number_format((float) $row->total, 2, '.', ''),
            $this->safeText($row->issued_by_name ?? ''),
            $this->safeText($row->voided_by_name ?? ''),
            $this->safeText($row->void_reason ?? ''),
        ];
    }

    private function safeText(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
