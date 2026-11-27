<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read-only dashboard aggregations.
 * All queries run against posted invoices (issued, partially_paid, paid)
 * and active payments (status = active).
 * Results are cached for 60 seconds keyed by period + location + user.
 */
class DashboardService
{
    private const POSTED = ['issued', 'partially_paid', 'paid'];

    private const TTL = 60;

    // ─── Period helpers ───────────────────────────────────────────────────

    /** @return array{start: Carbon, end: Carbon, prev_start: Carbon, prev_end: Carbon} */
    public function periodBounds(string $period): array
    {
        $tz = config('app.timezone', 'UTC');
        $now = Carbon::now($tz);

        return match ($period) {
            '7d' => [
                'start' => $now->copy()->subDays(6)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
                'prev_start' => $now->copy()->subDays(13)->startOfDay(),
                'prev_end' => $now->copy()->subDays(7)->endOfDay(),
            ],
            'month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
                'prev_start' => $now->copy()->subMonthNoOverflow()->startOfMonth(),
                'prev_end' => $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            default => [ // today
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
                'prev_start' => $now->copy()->subDay()->startOfDay(),
                'prev_end' => $now->copy()->subDay()->endOfDay(),
            ],
        };
    }

    // ─── Sales KPIs ───────────────────────────────────────────────────────

    /**
     * Returns KPIs for the current and previous period.
     *
     * @param  list<int>|null  $allowedIds  null = unrestricted; [] = no access
     * @return array{
     *   total_sales: string,
     *   invoice_count: int,
     *   avg_sale: string,
     *   payments_received: string,
     *   total_sales_prev: string,
     *   invoice_count_prev: int,
     *   payments_received_prev: string,
     * }
     */
    public function salesKpis(string $period, ?int $locationId, bool $cashierOnly = false, int $userId = 0, ?array $allowedIds = null): array
    {
        $key = "dashboard:sales:{$period}:{$locationId}:{$cashierOnly}:{$userId}:".$this->allowedKey($allowedIds);

        return Cache::remember($key, self::TTL, function () use ($period, $locationId, $cashierOnly, $userId, $allowedIds): array {
            $bounds = $this->periodBounds($period);

            $baseQuery = function (Carbon $start, Carbon $end) use ($locationId, $cashierOnly, $userId, $allowedIds): object {
                $q = DB::table('invoices')
                    ->whereIn('status', self::POSTED)
                    ->where('issued_at', '>=', $start->toDateTimeString())
                    ->where('issued_at', '<=', $end->toDateTimeString());

                if ($locationId !== null) {
                    $q->where('location_id', $locationId);
                }

                if ($allowedIds !== null) {
                    $allowedIds === [] ? $q->whereRaw('1 = 0') : $q->whereIn('location_id', $allowedIds);
                }

                if ($cashierOnly && $userId > 0) {
                    $q->where('issued_by', $userId);
                }

                return $q->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(total),0) as total_sales')->first();
            };

            $curr = $baseQuery($bounds['start'], $bounds['end']);
            $prev = $baseQuery($bounds['prev_start'], $bounds['prev_end']);

            $paymentsQuery = function (Carbon $start, Carbon $end) use ($locationId, $allowedIds): string {
                $q = DB::table('payments')
                    ->where('payments.status', 'active')
                    ->where('received_at', '>=', $start->toDateTimeString())
                    ->where('received_at', '<=', $end->toDateTimeString());

                if ($locationId !== null) {
                    $q->where('payments.location_id', $locationId);
                }

                if ($allowedIds !== null) {
                    $allowedIds === [] ? $q->whereRaw('1 = 0') : $q->whereIn('payments.location_id', $allowedIds);
                }

                return (string) $q->sum('amount');
            };

            $invoiceCount = (int) ($curr->invoice_count ?? 0);
            $totalSales = (string) ($curr->total_sales ?? '0');
            $avgSale = $invoiceCount > 0
                ? bcdiv($totalSales, (string) $invoiceCount, 2)
                : '0.00';

            return [
                'total_sales' => $totalSales,
                'invoice_count' => $invoiceCount,
                'avg_sale' => $avgSale,
                'payments_received' => $paymentsQuery($bounds['start'], $bounds['end']),
                'total_sales_prev' => (string) ($prev->total_sales ?? '0'),
                'invoice_count_prev' => (int) ($prev->invoice_count ?? 0),
                'payments_received_prev' => $paymentsQuery($bounds['prev_start'], $bounds['prev_end']),
            ];
        });
    }

    /**
     * Outstanding receivables and overdue figures (not period-sensitive).
     *
     * @param  list<int>|null  $allowedIds  null = unrestricted; [] = no access
     * @return array{outstanding: string, overdue_count: int, overdue_amount: string}
     */
    public function receivablesKpis(?int $locationId, ?array $allowedIds = null): array
    {
        $key = "dashboard:receivables:{$locationId}:".$this->allowedKey($allowedIds);

        return Cache::remember($key, self::TTL, function () use ($locationId, $allowedIds): array {
            $base = DB::table('invoices')
                ->whereIn('status', ['issued', 'partially_paid']);

            if ($locationId !== null) {
                $base->where('location_id', $locationId);
            }

            if ($allowedIds !== null) {
                $allowedIds === [] ? $base->whereRaw('1 = 0') : $base->whereIn('location_id', $allowedIds);
            }

            $outstanding = (string) ($base->clone()->selectRaw('COALESCE(SUM(total - amount_paid), 0) as v')->value('v') ?? '0');

            $overdue = $base->clone()
                ->where('due_date', '<', Carbon::today()->toDateString())
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total - amount_paid), 0) as amt')
                ->first();

            return [
                'outstanding' => $outstanding,
                'overdue_count' => (int) ($overdue->cnt ?? 0),
                'overdue_amount' => (string) ($overdue->amt ?? '0'),
            ];
        });
    }

    /**
     * Open quotations (draft + sent).
     *
     * @param  list<int>|null  $allowedIds  null = unrestricted; [] = no access
     * @return array{open_count: int, open_value: string}
     */
    public function quotationKpis(?int $locationId, ?array $allowedIds = null): array
    {
        $key = "dashboard:quotations:{$locationId}:".$this->allowedKey($allowedIds);

        return Cache::remember($key, self::TTL, function () use ($locationId, $allowedIds): array {
            $q = DB::table('quotations')
                ->whereIn('status', ['draft', 'sent']);

            if ($locationId !== null) {
                $q->where('location_id', $locationId);
            }

            if ($allowedIds !== null) {
                $allowedIds === [] ? $q->whereRaw('1 = 0') : $q->whereIn('location_id', $allowedIds);
            }

            $row = $q->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total), 0) as val')->first();

            return [
                'open_count' => (int) ($row->cnt ?? 0),
                'open_value' => (string) ($row->val ?? '0'),
            ];
        });
    }

    // ─── Inventory KPIs ───────────────────────────────────────────────────

    /**
     * @param  list<int>|null  $allowedIds  null = unrestricted; [] = no access
     * @return array{out_of_stock: int, low_stock: int, expiring_30: int, expired: int}
     */
    public function inventoryKpis(?array $allowedIds = null): array
    {
        $key = 'dashboard:inventory:'.$this->allowedKey($allowedIds);

        return Cache::remember($key, self::TTL, function () use ($allowedIds): array {
            $today = Carbon::today()->toDateString();
            $in30 = Carbon::today()->addDays(30)->toDateString();

            if ($allowedIds === []) {
                return ['out_of_stock' => 0, 'low_stock' => 0, 'expiring_30' => 0, 'expired' => 0];
            }

            // Out of stock: no stock row with qty > 0 at allowed locations.
            $outOfStock = DB::table('products')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->whereNotExists(function ($q) use ($allowedIds): void {
                    $q->select(DB::raw('1'))
                        ->from('stock_levels as sl')
                        ->whereColumn('sl.product_id', 'products.id')
                        ->where('sl.quantity', '>', 0);
                    if ($allowedIds !== null) {
                        $q->whereIn('sl.location_id', $allowedIds);
                    }
                })
                ->count();

            // Low stock: total qty > 0 but <= reorder_level, using a derived-table count.
            $lowStock = DB::table(function ($q) use ($allowedIds): void {
                $q->from('products')
                    ->leftJoin('stock_levels as sl', function ($join) use ($allowedIds): void {
                        $join->on('sl.product_id', '=', 'products.id');
                        if ($allowedIds !== null) {
                            $join->whereIn('sl.location_id', $allowedIds);
                        }
                    })
                    ->where('products.is_active', true)
                    ->whereNull('products.deleted_at')
                    ->whereRaw('products.reorder_level > 0')
                    ->groupBy('products.id', 'products.reorder_level')
                    ->havingRaw('COALESCE(SUM(sl.quantity), 0) > 0')
                    ->havingRaw('COALESCE(SUM(sl.quantity), 0) <= products.reorder_level')
                    ->select('products.id');
            }, 'low_sub')
                ->count();

            $expiringQ = DB::table('stock_levels')
                ->join('batches', 'batches.id', '=', 'stock_levels.batch_id')
                ->where('batches.expiry_date', '>=', $today)
                ->where('batches.expiry_date', '<=', $in30)
                ->where('stock_levels.quantity', '>', 0);
            if ($allowedIds !== null) {
                $expiringQ->whereIn('stock_levels.location_id', $allowedIds);
            }
            $expiring = $expiringQ->distinct('stock_levels.batch_id')->count('stock_levels.batch_id');

            $expiredQ = DB::table('stock_levels')
                ->join('batches', 'batches.id', '=', 'stock_levels.batch_id')
                ->where('batches.expiry_date', '<', $today)
                ->where('stock_levels.quantity', '>', 0);
            if ($allowedIds !== null) {
                $expiredQ->whereIn('stock_levels.location_id', $allowedIds);
            }
            $expired = $expiredQ->distinct('stock_levels.batch_id')->count('stock_levels.batch_id');

            return [
                'out_of_stock' => $outOfStock,
                'low_stock' => $lowStock,
                'expiring_30' => $expiring,
                'expired' => $expired,
            ];
        });
    }

    /**
     * 8 most urgent inventory items (expired first, then by urgency).
     *
     * @param  list<int>|null  $allowedIds  null = unrestricted; [] = no access
     * @return Collection<int, \stdClass>
     */
    public function urgentInventoryItems(?array $allowedIds = null): Collection
    {
        $key = 'dashboard:urgent_items:'.$this->allowedKey($allowedIds);

        return $this->cachedRows($key, function () use ($allowedIds): Collection {
            if ($allowedIds === []) {
                return collect();
            }

            // Out-of-stock active products (no stock row with qty > 0 at allowed locations).
            $oos = DB::table('products')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->whereNotExists(function ($q) use ($allowedIds): void {
                    $q->select(DB::raw('1'))
                        ->from('stock_levels as sl')
                        ->whereColumn('sl.product_id', 'products.id')
                        ->where('sl.quantity', '>', 0);
                    if ($allowedIds !== null) {
                        $q->whereIn('sl.location_id', $allowedIds);
                    }
                })
                ->select('products.id', 'products.name', DB::raw("'out_of_stock' as issue_type"), DB::raw('0 as qty'))
                ->limit(4)
                ->get();

            // Low-stock active products (total qty > 0 but <= reorder_level).
            $low = DB::table('products')
                ->leftJoin('stock_levels as sl', function ($join) use ($allowedIds): void {
                    $join->on('sl.product_id', '=', 'products.id');
                    if ($allowedIds !== null) {
                        $join->whereIn('sl.location_id', $allowedIds);
                    }
                })
                ->where('products.is_active', true)
                ->whereNull('products.deleted_at')
                ->whereRaw('products.reorder_level > 0')
                ->groupBy('products.id', 'products.name', 'products.reorder_level')
                ->havingRaw('COALESCE(SUM(sl.quantity), 0) > 0')
                ->havingRaw('COALESCE(SUM(sl.quantity), 0) <= products.reorder_level')
                ->select(
                    'products.id',
                    'products.name',
                    DB::raw("'low_stock' as issue_type"),
                    DB::raw('COALESCE(SUM(sl.quantity), 0) as qty'),
                )
                ->limit(4)
                ->get();

            return $oos->merge($low)->take(8);
        });
    }

    // ─── Lists ────────────────────────────────────────────────────────────

    /**
     * @param  list<int>|null  $allowedIds  null = unrestricted; [] = no access
     * @return Collection<int, \stdClass>
     */
    public function recentInvoices(?int $locationId, bool $cashierOnly = false, int $userId = 0, ?array $allowedIds = null): Collection
    {
        $key = "dashboard:recent_invoices:{$locationId}:{$cashierOnly}:{$userId}:".$this->allowedKey($allowedIds);

        return $this->cachedRows($key, function () use ($locationId, $cashierOnly, $userId, $allowedIds): Collection {
            $q = DB::table('invoices')
                ->join('customers', 'customers.id', '=', 'invoices.customer_id')
                ->whereIn('invoices.status', self::POSTED)
                ->select(
                    'invoices.id',
                    'invoices.number',
                    'invoices.status',
                    'invoices.total',
                    'invoices.amount_paid',
                    'invoices.due_date',
                    'invoices.issued_at',
                    'customers.name as customer_name',
                );

            if ($locationId !== null) {
                $q->where('invoices.location_id', $locationId);
            }

            if ($allowedIds !== null) {
                $allowedIds === [] ? $q->whereRaw('1 = 0') : $q->whereIn('invoices.location_id', $allowedIds);
            }

            if ($cashierOnly && $userId > 0) {
                $q->where('invoices.issued_by', $userId);
            }

            return $q->orderByDesc('invoices.issued_at')->limit(8)->get();
        });
    }

    /**
     * @param  list<int>|null  $allowedIds  null = unrestricted; [] = no access
     * @return Collection<int, \stdClass>
     */
    public function topProducts(string $period, ?int $locationId, ?array $allowedIds = null): Collection
    {
        $key = "dashboard:top_products:{$period}:{$locationId}:".$this->allowedKey($allowedIds);

        return $this->cachedRows($key, function () use ($period, $locationId, $allowedIds): Collection {
            $bounds = $this->periodBounds($period);

            $q = DB::table('invoice_items')
                ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                ->join('products', 'products.id', '=', 'invoice_items.product_id')
                ->whereIn('invoices.status', self::POSTED)
                ->where('invoices.issued_at', '>=', $bounds['start']->toDateTimeString())
                ->where('invoices.issued_at', '<=', $bounds['end']->toDateTimeString())
                ->select(
                    'products.id',
                    'products.name',
                    DB::raw('SUM(invoice_items.line_total) as revenue'),
                    DB::raw('SUM(invoice_items.base_quantity) as qty_sold'),
                );

            if ($locationId !== null) {
                $q->where('invoices.location_id', $locationId);
            }

            if ($allowedIds !== null) {
                $allowedIds === [] ? $q->whereRaw('1 = 0') : $q->whereIn('invoices.location_id', $allowedIds);
            }

            return $q->groupBy('products.id', 'products.name')
                ->orderByDesc('revenue')
                ->limit(5)
                ->get();
        });
    }

    /**
     * @param  list<int>|null  $allowedIds  null = unrestricted; [] = no access
     * @return Collection<int, \stdClass>
     */
    public function overdueInvoices(?int $locationId, ?array $allowedIds = null): Collection
    {
        $key = "dashboard:overdue:{$locationId}:".$this->allowedKey($allowedIds);

        return $this->cachedRows($key, function () use ($locationId, $allowedIds): Collection {
            $q = DB::table('invoices')
                ->join('customers', 'customers.id', '=', 'invoices.customer_id')
                ->whereIn('invoices.status', ['issued', 'partially_paid'])
                ->where('invoices.due_date', '<', Carbon::today()->toDateString())
                ->select(
                    'invoices.id',
                    'invoices.number',
                    'invoices.due_date',
                    DB::raw('invoices.total - invoices.amount_paid as balance'),
                    'customers.name as customer_name',
                );

            if ($locationId !== null) {
                $q->where('invoices.location_id', $locationId);
            }

            if ($allowedIds !== null) {
                $allowedIds === [] ? $q->whereRaw('1 = 0') : $q->whereIn('invoices.location_id', $allowedIds);
            }

            return $q->orderBy('invoices.due_date')->limit(8)->get();
        });
    }

    // ─── Cache helper ─────────────────────────────────────────────────────

    /**
     * Cache a row-query as a plain list of scalar arrays so that any
     * serializing cache store (database, file) can round-trip the value
     * without needing stdClass in the allowed-class list.
     * On read the flat arrays are cast back to stdClass objects so callers
     * and Blade views can use ->property syntax as normal.
     *
     * @param  \Closure(): Collection<int, \stdClass>  $builder
     * @return Collection<int, \stdClass>
     */
    private function cachedRows(string $key, \Closure $builder): Collection
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember($key, self::TTL, static function () use ($builder): array {
            return $builder()->map(fn (\stdClass $r): array => (array) $r)->all();
        });

        return collect($rows)->map(fn (array $r): \stdClass => (object) $r);
    }

    /**
     * Compute a compact cache-key segment from an allowed-locations list.
     * null = Admin (all), [] = no access, int[] = specific set.
     *
     * @param  list<int>|null  $allowedIds
     */
    private function allowedKey(?array $allowedIds): string
    {
        if ($allowedIds === null) {
            return 'all';
        }
        if ($allowedIds === []) {
            return 'none';
        }
        $sorted = $allowedIds;
        sort($sorted);

        return implode(',', $sorted);
    }

    // ─── Chart data ───────────────────────────────────────────────────────

    /**
     * Sales per day for the last 30 days.
     *
     * @param  list<int>|null  $allowedIds  null = unrestricted; [] = no access
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public function salesByDay(?int $locationId, ?array $allowedIds = null): array
    {
        $key = "dashboard:chart_sales_day:{$locationId}:".$this->allowedKey($allowedIds);

        return Cache::remember($key, self::TTL, function () use ($locationId, $allowedIds): array {
            $tz = config('app.timezone', 'UTC');
            $start = Carbon::now($tz)->subDays(29)->startOfDay();
            $end = Carbon::now($tz)->endOfDay();

            $q = DB::table('invoices')
                ->whereIn('status', self::POSTED)
                ->where('issued_at', '>=', $start->toDateTimeString())
                ->where('issued_at', '<=', $end->toDateTimeString())
                ->selectRaw("DATE(issued_at AT TIME ZONE 'UTC' AT TIME ZONE ?) as day, COALESCE(SUM(total),0) as total", [$tz])
                ->groupByRaw('1')
                ->orderByRaw('1');

            if ($locationId !== null) {
                $q->where('location_id', $locationId);
            }

            if ($allowedIds !== null) {
                $allowedIds === [] ? $q->whereRaw('1 = 0') : $q->whereIn('location_id', $allowedIds);
            }

            $rows = $q->get()->keyBy('day');
            $labels = [];
            $data = [];

            for ($i = 29; $i >= 0; $i--) {
                $day = Carbon::now($tz)->subDays($i)->format('Y-m-d');
                $labels[] = Carbon::parse($day)->format('j M');
                $row = $rows->get($day);
                $data[] = $row !== null ? (float) $row->total : 0.0;
            }

            return [
                'labels' => $labels,
                'datasets' => [['label' => 'Sales (TZS)', 'data' => $data]],
            ];
        });
    }

    /**
     * Sales by product category for the given period.
     *
     * @param  list<int>|null  $allowedIds  null = unrestricted; [] = no access
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public function salesByCategory(string $period, ?int $locationId, ?array $allowedIds = null): array
    {
        $key = "dashboard:chart_sales_cat:{$period}:{$locationId}:".$this->allowedKey($allowedIds);

        return Cache::remember($key, self::TTL, function () use ($period, $locationId, $allowedIds): array {
            $bounds = $this->periodBounds($period);

            $q = DB::table('invoice_items')
                ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                ->join('products', 'products.id', '=', 'invoice_items.product_id')
                ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                ->whereIn('invoices.status', self::POSTED)
                ->where('invoices.issued_at', '>=', $bounds['start']->toDateTimeString())
                ->where('invoices.issued_at', '<=', $bounds['end']->toDateTimeString())
                ->selectRaw("COALESCE(categories.name, 'Uncategorised') as cat, COALESCE(SUM(invoice_items.line_total),0) as total")
                ->groupByRaw("COALESCE(categories.name, 'Uncategorised')")
                ->orderByDesc('total')
                ->limit(8);

            if ($locationId !== null) {
                $q->where('invoices.location_id', $locationId);
            }

            if ($allowedIds !== null) {
                $allowedIds === [] ? $q->whereRaw('1 = 0') : $q->whereIn('invoices.location_id', $allowedIds);
            }

            $rows = $q->get();

            return [
                'labels' => array_values($rows->pluck('cat')->map(fn ($v) => (string) $v)->all()),
                'datasets' => [['label' => 'Revenue', 'data' => array_values($rows->pluck('total')->map(fn ($v) => (float) $v)->all())]],
            ];
        });
    }

    /**
     * Payments by method for the given period.
     *
     * @param  list<int>|null  $allowedIds  null = unrestricted; [] = no access
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public function paymentsByMethod(string $period, ?int $locationId, ?array $allowedIds = null): array
    {
        $key = "dashboard:chart_payments_method:{$period}:{$locationId}:".$this->allowedKey($allowedIds);

        return Cache::remember($key, self::TTL, function () use ($period, $locationId, $allowedIds): array {
            $bounds = $this->periodBounds($period);

            $q = DB::table('payments')
                ->where('payments.status', 'active')
                ->where('payments.received_at', '>=', $bounds['start']->toDateTimeString())
                ->where('payments.received_at', '<=', $bounds['end']->toDateTimeString())
                ->selectRaw('payments.method, COALESCE(SUM(payments.amount),0) as total')
                ->groupBy('payments.method')
                ->orderByDesc('total');

            if ($locationId !== null) {
                $q->where('payments.location_id', $locationId);
            }

            if ($allowedIds !== null) {
                $allowedIds === [] ? $q->whereRaw('1 = 0') : $q->whereIn('payments.location_id', $allowedIds);
            }

            $rows = $q->get();

            return [
                'labels' => array_values($rows->pluck('method')->map(fn ($m) => ucfirst((string) $m))->all()),
                'datasets' => [['label' => 'Payments', 'data' => array_values($rows->pluck('total')->map(fn ($v) => (float) $v)->all())]],
            ];
        });
    }
}
