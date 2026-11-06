<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Payments by Method</flux:heading>
            <flux:subheading>Total received payments grouped by payment method.</flux:subheading>
        </div>
        @can('reports.export')
            <div class="flex gap-2">
                <flux:button size="sm" icon="arrow-down-tray" wire:click="exportXlsx">Excel</flux:button>
                <flux:button size="sm" icon="arrow-down-tray" wire:click="exportCsv">CSV</flux:button>
            </div>
        @endcan
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:input wire:model.live="dateFrom" label="From date" type="date" />
        <flux:input wire:model.live="dateTo" label="To date" type="date" />
        <flux:select wire:model.live="locationFilter" label="Location">
            <option value="">All locations</option>
            @foreach ($locations as $location)
                <option value="{{ $location->id }}">{{ $location->name }}</option>
            @endforeach
        </flux:select>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white p-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Method</flux:table.column>
                <flux:table.column class="text-right">Transactions</flux:table.column>
                <flux:table.column class="text-right">Amount</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($rows as $row)
                    <flux:table.row>
                        <flux:table.cell class="font-medium capitalize">{{ ucwords(str_replace('_', ' ', $row->method)) }}</flux:table.cell>
                        <flux:table.cell class="text-right">{{ number_format($row->payment_count) }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono font-semibold">{{ number_format($row->amount, 2) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="py-10 text-center text-zinc-400">No payments for the selected period.</flux:table.cell>
                    </flux:table.row>
                @endforelse
                @if ($rows->isNotEmpty())
                    <flux:table.row class="border-t-2 border-zinc-300 dark:border-zinc-600 font-semibold bg-zinc-50 dark:bg-zinc-800">
                        <flux:table.cell>Total</flux:table.cell>
                        <flux:table.cell class="text-right">{{ number_format($totals['payment_count']) }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ $totals['amount'] }}</flux:table.cell>
                    </flux:table.row>
                @endif
            </flux:table.rows>
        </flux:table>
    </div>
</div>
