<?php

namespace App\Exports\Reports;

use App\Enums\InvoiceStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReceivablesAgingExport implements FromCollection, WithHeadings, WithMapping
{
    private readonly Carbon $asOf;

    public function __construct(string $asOf)
    {
        $this->asOf = Carbon::parse($asOf)->endOfDay();
    }

    /** @return Collection<int, \stdClass> */
    public function collection(): Collection
    {
        $open = [
            InvoiceStatus::Issued->value,
            InvoiceStatus::PartiallyPaid->value,
        ];

        $asOf = $this->asOf->toDateString();

        return DB::table('invoices')
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->selectRaw('customers.id AS customer_id')
            ->selectRaw('customers.name AS customer_name')
            ->selectRaw('customers.code AS customer_code')
            ->selectRaw(
                'SUM(CASE WHEN due_date >= ? OR due_date IS NULL THEN (invoices.total - invoices.amount_paid) ELSE 0 END) AS current_amount',
                [$asOf]
            )
            ->selectRaw(
                'SUM(CASE WHEN due_date < ? AND due_date >= ? THEN (invoices.total - invoices.amount_paid) ELSE 0 END) AS days_31_60',
                [$asOf, $this->asOf->copy()->subDays(60)->toDateString()]
            )
            ->selectRaw(
                'SUM(CASE WHEN due_date < ? AND due_date >= ? THEN (invoices.total - invoices.amount_paid) ELSE 0 END) AS days_61_90',
                [$this->asOf->copy()->subDays(30)->toDateString(), $this->asOf->copy()->subDays(90)->toDateString()]
            )
            ->selectRaw(
                'SUM(CASE WHEN due_date < ? THEN (invoices.total - invoices.amount_paid) ELSE 0 END) AS days_90_plus',
                [$this->asOf->copy()->subDays(90)->toDateString()]
            )
            ->selectRaw('SUM(invoices.total - invoices.amount_paid) AS total_outstanding')
            ->whereIn('invoices.status', $open)
            ->where('customers.is_active', true)
            ->groupBy('customers.id', 'customers.name', 'customers.code')
            ->havingRaw('SUM(invoices.total - invoices.amount_paid) > 0')
            ->orderBy('customers.name')
            ->get();
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['customer_code', 'customer_name', 'current_0_30', 'days_31_60', 'days_61_90', 'days_90_plus', 'total_outstanding'];
    }

    /** @param \stdClass $row */
    public function map($row): array
    {
        return [
            $this->safeText($row->customer_code),
            $this->safeText($row->customer_name),
            number_format((float) $row->current_amount, 2, '.', ''),
            number_format((float) $row->days_31_60, 2, '.', ''),
            number_format((float) $row->days_61_90, 2, '.', ''),
            number_format((float) $row->days_90_plus, 2, '.', ''),
            number_format((float) $row->total_outstanding, 2, '.', ''),
        ];
    }

    private function safeText(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
