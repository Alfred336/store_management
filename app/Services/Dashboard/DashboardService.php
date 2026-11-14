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
    public function salesKpis(string $period, ?int $locationId, bool $cashierOnly = false, int $userId = 0): array
    {
        $key = "dashboard:sales:{$period}:{$locationId}:{$cashierOnly}:{$userId}";

        return Cache::remember($key, self::TTL, function () use ($period, $locationId, $cashierOnly, $userId): array {
            $bounds = $this->periodBounds($period);

            $baseQuery = function (Carbon $start, Carbon $end) use ($locationId, $cashierOnly, $userId): object {
                $q = DB::table('invoices')
                    ->whereIn('status', self::POSTED)
                    ->where('issued_at', '>=', $start->toDateTimeString())
                    ->where('issued_at', '<=', $end->toDateTimeString());

                if ($locationId !== null) {
                    $q->where('location_id', $locationId);
                }

                if ($cashierOnly && $userId > 0) {
                    $q->where('issued_by', $userId);
                }

                return $q->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(total),0) as total_sales')->first();
            };

            $curr = $baseQuery($bounds['start'], $bounds['end']);
            $prev = $baseQuery($bounds['prev_start'], $bounds['prev_end']);

            $paymentsQuery = function (Carbon $start, Carbon $end) use ($locationId): string {
                $q = DB::table('payments')
                    ->where('payments.status', 'active')
                    ->where('received_at', '>=', $start->toDateTimeString())
                    ->where('received_at', '<=', $end->toDateTimeString());

                if ($locationId !== null) {
                    $q->join('pos_sessions', 'payments.pos_session_id', '=', 'pos_sessions.id')
                        ->where('pos_sessions.location_id', $locationId)
                        ->whereNotNull('payments.pos_session_id');
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
     * @return array{outstanding: string, overdue_count: int, overdue_amount: string}
     */
    public function receivablesKpis(?int $locationId): array
    {
        $key = "dashboard:receivables:{$locationId}";

        return Cache::remember($key, self::TTL, function () use ($locationId): array {
            $base = DB::table('invoices')
                ->whereIn('status', ['issued', 'partially_paid']);

            if ($locationId !== null) {
                $base->where('location_id', $locationId);
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
     * @return array{open_count: int, open_value: string}
     */
    public function quotationKpis(?int $locationId): array
    {
        $key = "dashboard:quotations:{$locationId}";

        return Cache::remember($key, self::TTL, function () use ($locationId): array {
            $q = DB::table('quotations')
                ->whereIn('status', ['draft', 'sent']);

            if ($locationId !== null) {
                $q->where('location_id', $locationId);
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
     * @return array{out_of_stock: int, low_stock: int, expiring_30: int, expired: int}
     */
    public function inventoryKpis(): array
    {
        return Cache::remember('dashboard:inventory', self::TTL, function (): array {
            $today = Carbon::today()->toDateString();
            $in30 = Carbon::today()->addDays(30)->toDateString();

            // Out of stock: total qty = 0 or no row
            $outOfStock = DB::table('products')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->whereRaw(
                    '(SELECT COALESCE(SUM(sl.quantity),0) FROM stock_levels sl WHERE sl.product_id = products.id) = 0'
                )
                ->count();

            // Low stock: qty > 0, reorder_level > 0, qty <= reorder_level
            $lowStock = DB::table('products')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->whereRaw('reorder_level > 0')
                ->whereRaw(
                    '(SELECT COALESCE(SUM(sl.quantity),0) FROM stock_levels sl WHERE sl.product_id = products.id) <= products.reorder_level'
                )
                ->whereRaw(
                    '(SELECT COALESCE(SUM(sl.quantity),0) FROM stock_levels sl WHERE sl.product_id = products.id) > 0'
                )
                ->count();

            $expiring = DB::table('stock_levels')
                ->join('batches', 'batches.id', '=', 'stock_levels.batch_id')
                ->where('batches.expiry_date', '>=', $today)
                ->where('batches.expiry_date', '<=', $in30)
                ->where('stock_levels.quantity', '>', 0)
                ->distinct('stock_levels.batch_id')
                ->count('stock_levels.batch_id');

            $expired = DB::table('stock_levels')
                ->join('batches', 'batches.id', '=', 'stock_levels.batch_id')
                ->where('batches.expiry_date', '<', $today)
                ->where('stock_levels.quantity', '>', 0)
                ->distinct('stock_levels.batch_id')
                ->count('stock_levels.batch_id');

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
     * @return Collection<int, \stdClass>
     */
    public function urgentInventoryItems(): Collection
    {
        return $this->cachedRows('dashboard:urgent_items', function (): Collection {
            // Out-of-stock active products
            $oos = DB::table('products')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->whereRaw(
                    '(SELECT COALESCE(SUM(sl.quantity),0) FROM stock_levels sl WHERE sl.product_id = products.id) = 0'
                )
                ->select('products.id', 'products.name', DB::raw("'out_of_stock' as issue_type"), DB::raw('0 as qty'))
                ->limit(4)
                ->get();

            // Low-stock active products
            $low = DB::table('products')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->whereRaw('reorder_level > 0')
                ->whereRaw(
                    '(SELECT COALESCE(SUM(sl.quantity),0) FROM stock_levels sl WHERE sl.product_id = products.id) <= products.reorder_level'
                )
                ->whereRaw(
                    '(SELECT COALESCE(SUM(sl.quantity),0) FROM stock_levels sl WHERE sl.product_id = products.id) > 0'
                )
                ->select(
                    'products.id',
                    'products.name',
                    DB::raw("'low_stock' as issue_type"),
                    DB::raw('(SELECT COALESCE(SUM(sl.quantity),0) FROM stock_levels sl WHERE sl.product_id = products.id) as qty'),
                )
                ->limit(4)
                ->get();

            return $oos->merge($low)->take(8);
        });
    }

    // ─── Lists ────────────────────────────────────────────────────────────

    /**
     * @return Collection<int, \stdClass>
     */
    public function recentInvoices(?int $locationId, bool $cashierOnly = false, int $userId = 0): Collection
    {
        $key = "dashboard:recent_invoices:{$locationId}:{$cashierOnly}:{$userId}";

        return $this->cachedRows($key, function () use ($locationId, $cashierOnly, $userId): Collection {
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

            if ($cashierOnly && $userId > 0) {
                $q->where('invoices.issued_by', $userId);
            }

            return $q->orderByDesc('invoices.issued_at')->limit(8)->get();
        });
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function topProducts(string $period, ?int $locationId): Collection
    {
        $key = "dashboard:top_products:{$period}:{$locationId}";

        return $this->cachedRows($key, function () use ($period, $locationId): Collection {
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

            return $q->groupBy('products.id', 'products.name')
                ->orderByDesc('revenue')
                ->limit(5)
                ->get();
        });
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function overdueInvoices(?int $locationId): Collection
    {
        return $this->cachedRows("dashboard:overdue:{$locationId}", function () use ($locationId): Collection {
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

    // ─── Chart data ───────────────────────────────────────────────────────

    /**
     * Sales per day for the last 30 days.
     *
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public function salesByDay(?int $locationId): array
    {
        $key = "dashboard:chart_sales_day:{$locationId}";

        return Cache::remember($key, self::TTL, function () use ($locationId): array {
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
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public function salesByCategory(string $period, ?int $locationId): array
    {
        $key = "dashboard:chart_sales_cat:{$period}:{$locationId}";

        return Cache::remember($key, self::TTL, function () use ($period, $locationId): array {
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
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public function paymentsByMethod(string $period, ?int $locationId): array
    {
        $key = "dashboard:chart_payments_method:{$period}:{$locationId}";

        return Cache::remember($key, self::TTL, function () use ($period, $locationId): array {
            $bounds = $this->periodBounds($period);

            $q = DB::table('payments')
                ->where('payments.status', 'active')
                ->where('payments.received_at', '>=', $bounds['start']->toDateTimeString())
                ->where('payments.received_at', '<=', $bounds['end']->toDateTimeString())
                ->selectRaw('payments.method, COALESCE(SUM(payments.amount),0) as total')
                ->groupBy('payments.method')
                ->orderByDesc('total');

            if ($locationId !== null) {
                $q->join('pos_sessions', 'payments.pos_session_id', '=', 'pos_sessions.id')
                    ->where('pos_sessions.location_id', $locationId)
                    ->whereNotNull('payments.pos_session_id');
            }

            $rows = $q->get();

            return [
                'labels' => array_values($rows->pluck('method')->map(fn ($m) => ucfirst((string) $m))->all()),
                'datasets' => [['label' => 'Payments', 'data' => array_values($rows->pluck('total')->map(fn ($v) => (float) $v)->all())]],
            ];
        });
    }
}
