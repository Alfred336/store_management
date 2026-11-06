<?php

namespace App\Services\Reports;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** Inventory Report: current stock on hand grouped by product and location. */
class InventoryReportService
{
    /** @return LengthAwarePaginator<int, \stdClass> */
    public function paginate(?int $locationId = null, ?int $categoryId = null, int $perPage = 50): LengthAwarePaginator
    {
        return DB::table('stock_levels')
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->join('locations', 'locations.id', '=', 'stock_levels.location_id')
            ->join('units', 'units.id', '=', 'products.base_unit_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->select([
                'products.id AS product_id',
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
            ->when($locationId !== null, fn ($q) => $q->where('stock_levels.location_id', $locationId))
            ->when($categoryId !== null, fn ($q) => $q->where('products.category_id', $categoryId))
            ->orderBy('products.name')
            ->orderBy('locations.name')
            ->paginate($perPage);
    }

    /**
     * @return array{total_value: string, total_lines: int}
     */
    public function totals(?int $locationId = null, ?int $categoryId = null): array
    {
        $row = DB::table('stock_levels')
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->selectRaw('SUM(stock_levels.quantity * products.selling_price) AS total_value')
            ->selectRaw('COUNT(*) AS total_lines')
            ->whereNull('products.deleted_at')
            ->when($locationId !== null, fn ($q) => $q->where('stock_levels.location_id', $locationId))
            ->when($categoryId !== null, fn ($q) => $q->where('products.category_id', $categoryId))
            ->first();

        return [
            'total_value' => number_format((float) ($row->total_value ?? 0), 2),
            'total_lines' => (int) ($row->total_lines ?? 0),
        ];
    }
}
