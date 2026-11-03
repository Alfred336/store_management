<?php

namespace App\Exports;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** Export customer catalog records without calculated balances. */
class CustomersExport implements FromQuery, WithHeadings, WithMapping
{
    public function query(): Builder
    {
        return Customer::query()->orderBy('id');
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['code', 'name', 'type', 'phone', 'email', 'address', 'tin', 'vrn', 'notes', 'credit_limit', 'payment_terms_days', 'is_active'];
    }

    /** @param Customer $row
     * @return array<int, string|int>
     */
    public function map($row): array
    {
        return [
            $this->safeText($row->code),
            $this->safeText($row->name),
            $this->safeText($row->type),
            $this->safeText($row->phone ?? ''),
            $this->safeText($row->email ?? ''),
            $this->safeText($row->address ?? ''),
            $this->safeText($row->tin ?? ''),
            $this->safeText($row->vrn ?? ''),
            $this->safeText($row->notes ?? ''),
            $this->safeText($row->credit_limit),
            $row->payment_terms_days,
            $row->is_active ? 1 : 0,
        ];
    }

    private function safeText(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
