<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat-card
        label="Out of Stock"
        :value="number_format($inventoryKpis['out_of_stock'])"
        icon="archive-box-x-mark"
        :color="$inventoryKpis['out_of_stock'] > 0 ? 'red' : 'default'"
        :href="auth()->user()?->can('inventory.view') ? route('inventory.index') : null"
    />
    <x-stat-card
        label="Low Stock"
        :value="number_format($inventoryKpis['low_stock'])"
        icon="archive-box"
        :color="$inventoryKpis['low_stock'] > 0 ? 'amber' : 'default'"
        :href="auth()->user()?->can('inventory.view') ? route('inventory.index') : null"
    />
    <x-stat-card
        label="Expiring in 30 Days"
        :value="number_format($inventoryKpis['expiring_30'])"
        icon="clock"
        :color="$inventoryKpis['expiring_30'] > 0 ? 'amber' : 'default'"
        :href="auth()->user()?->can('inventory.view') ? route('inventory.index') : null"
    />
    <x-stat-card
        label="Expired Batches"
        :value="number_format($inventoryKpis['expired'])"
        icon="x-circle"
        :color="$inventoryKpis['expired'] > 0 ? 'red' : 'default'"
        :href="auth()->user()?->can('inventory.view') ? route('inventory.index') : null"
    />
</div>
