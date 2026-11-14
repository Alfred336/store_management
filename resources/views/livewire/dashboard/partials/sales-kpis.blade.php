@php
use App\Services\Dashboard\DashboardService;

$kpis = $salesKpis;

// % change helpers
$pctChange = function (string $curr, string $prev): ?float {
    $c = (float) $curr;
    $p = (float) $prev;
    if ($p == 0) return $c > 0 ? null : null; // no meaningful % if prev was 0
    return round((($c - $p) / abs($p)) * 100, 1);
};

$salesChange     = $pctChange((string) $kpis['total_sales'],       (string) $kpis['total_sales_prev']);
$countChange     = $kpis['invoice_count_prev'] > 0
    ? round((($kpis['invoice_count'] - $kpis['invoice_count_prev']) / abs($kpis['invoice_count_prev'])) * 100, 1)
    : null;
$paymentsChange  = $pctChange((string) $kpis['payments_received'], (string) $kpis['payments_received_prev']);

$periodLabel = match ($period) {
    '7d'    => 'vs prev 7 days',
    'month' => 'vs last month',
    default => 'vs yesterday',
};
@endphp

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat-card
        label="Total Sales"
        :value="'TZS '.number_format((float) $kpis['total_sales'])"
        icon="banknotes"
        color="emerald"
        :trend="$salesChange"
        :trendLabel="$salesChange !== null ? abs($salesChange).'% '.$periodLabel : null"
        :href="auth()->user()?->can('invoices.view') ? route('invoices.index') : null"
    />

    <x-stat-card
        label="Number of Sales"
        :value="number_format($kpis['invoice_count'])"
        icon="document-text"
        :trend="$countChange"
        :trendLabel="$countChange !== null ? abs($countChange).'% '.$periodLabel : null"
    />

    <x-stat-card
        label="Average Sale"
        :value="'TZS '.number_format((float) $kpis['avg_sale'])"
        icon="chart-bar"
    />

    <x-stat-card
        label="Payments Received"
        :value="'TZS '.number_format((float) $kpis['payments_received'])"
        icon="credit-card"
        color="blue"
        :trend="$paymentsChange"
        :trendLabel="$paymentsChange !== null ? abs($paymentsChange).'% '.$periodLabel : null"
        :href="auth()->user()?->can('viewAny', App\Models\Payment::class) ? route('payments.index') : null"
    />
</div>
