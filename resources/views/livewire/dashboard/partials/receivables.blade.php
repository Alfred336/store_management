<div class="grid gap-4 sm:grid-cols-3">
    <x-stat-card
        label="Outstanding Receivables"
        :value="'TZS '.number_format((float) $receivablesKpis['outstanding'])"
        icon="clock"
        color="amber"
    />
    <x-stat-card
        label="Overdue Invoices"
        :value="number_format($receivablesKpis['overdue_count'])"
        icon="exclamation-triangle"
        :color="$receivablesKpis['overdue_count'] > 0 ? 'red' : 'default'"
        :href="auth()->user()?->can('invoices.view') ? route('invoices.index') : null"
    />
    <x-stat-card
        label="Overdue Amount"
        :value="'TZS '.number_format((float) $receivablesKpis['overdue_amount'])"
        icon="x-circle"
        :color="((float) $receivablesKpis['overdue_amount']) > 0 ? 'red' : 'default'"
    />
</div>
