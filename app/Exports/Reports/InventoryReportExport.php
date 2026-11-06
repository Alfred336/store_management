<?php

namespace App\Exports\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InventoryReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly ?int $locationId = null,
        private readonly ?int $categoryId = null,
    ) {}

    /** @return Collection<int, \stdClass> */
    public function collection(): Collection
    {
        return DB::table('stock_levels')
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->join('locations', 'locations.id', '=', 'stock_levels.location_id')
            ->join('units', 'units.id', '=', 'products.base_unit_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->select([
                'products.sku',
                'products.name AS product_name',
                'categories.name AS category_name',
                'locations.name AS location_name',
                'stock_levels.quantity',
                'units.abbreviation AS unit_abbreviation',
                'products.reorder_level',
                'products.selling_price',
            ])
            ->selectRaw('(stock_levels.quantity * products.selling_price) AS stock_value')
            ->whereNull('products.deleted_at')
            ->when($this->locationId !== null, fn ($q) => $q->where('stock_levels.location_id', $this->locationId))
            ->when($this->categoryId !== null, fn ($q) => $q->where('products.category_id', $this->categoryId))
            ->orderBy('products.name')
            ->orderBy('locations.name')
            ->get();
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['sku', 'product_name', 'category', 'location', 'quantity', 'unit', 'reorder_level', 'selling_price', 'stock_value'];
    }

    /** @param \stdClass $row */
    public function map($row): array
    {
        return [
            $this->safeText($row->sku ?? ''),
            $this->safeText($row->product_name),
            $this->safeText($row->category_name ?? ''),
            $this->safeText($row->location_name),
            $row->quantity,
            $row->unit_abbreviation,
            $row->reorder_level,
            number_format((float) $row->selling_price, 2, '.', ''),
            number_format((float) $row->stock_value, 2, '.', ''),
        ];
    }

    private function safeText(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
