<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Inventory Report</flux:heading>
            <flux:subheading>Current stock on hand with estimated values.</flux:subheading>
        </div>
        @can('reports.export')
            <div class="flex gap-2">
                <flux:button size="sm" icon="arrow-down-tray" wire:click="exportXlsx">Excel</flux:button>
                <flux:button size="sm" icon="arrow-down-tray" wire:click="exportCsv">CSV</flux:button>
            </div>
        @endcan
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:select wire:model.live="locationFilter" label="Location">
            <option value="">All locations</option>
            @foreach ($locations as $location)
                <option value="{{ $location->id }}">{{ $location->name }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="categoryFilter" label="Category">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </flux:select>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="text-sm text-zinc-500">Total Lines</div>
            <div class="text-2xl font-semibold">{{ number_format($totals['total_lines']) }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="text-sm text-zinc-500">Estimated Stock Value</div>
            <div class="text-2xl font-semibold font-mono">{{ $totals['total_value'] }}</div>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white p-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Product</flux:table.column>
                <flux:table.column>Category</flux:table.column>
                <flux:table.column>Location</flux:table.column>
                <flux:table.column class="text-right">Quantity</flux:table.column>
                <flux:table.column class="text-right">Reorder Level</flux:table.column>
                <flux:table.column class="text-right">Selling Price</flux:table.column>
                <flux:table.column class="text-right">Stock Value</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($levels as $level)
                    <flux:table.row :key="$level->product_id . '-' . $level->location_name">
                        <flux:table.cell>
                            <div class="font-medium">{{ $level->product_name }}</div>
                            <div class="text-xs text-zinc-500">{{ $level->sku }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">{{ $level->category_name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $level->location_name }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono">
                            {{ $level->quantity }}
                            <span class="text-xs text-zinc-500">{{ $level->unit_abbreviation }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="text-right font-mono text-zinc-500">{{ $level->reorder_level }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ number_format($level->selling_price, 2) }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono font-semibold">{{ number_format($level->stock_value, 2) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-400">No stock records found.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    {{ $levels->links() }}
</div>
