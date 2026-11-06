<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Receivables Aging</flux:heading>
            <flux:subheading>Outstanding customer balances bucketed by overdue period.</flux:subheading>
        </div>
        @can('reports.export')
            <div class="flex gap-2">
                <flux:button size="sm" icon="arrow-down-tray" wire:click="exportXlsx">Excel</flux:button>
                <flux:button size="sm" icon="arrow-down-tray" wire:click="exportCsv">CSV</flux:button>
            </div>
        @endcan
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:input wire:model.live="asOf" label="As of date" type="date" />
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white p-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Customer</flux:table.column>
                <flux:table.column class="text-right">Current (0–30d)</flux:table.column>
                <flux:table.column class="text-right">31–60 days</flux:table.column>
                <flux:table.column class="text-right">61–90 days</flux:table.column>
                <flux:table.column class="text-right">90+ days</flux:table.column>
                <flux:table.column class="text-right">Total Outstanding</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($rows as $row)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="font-medium">{{ $row->customer_name }}</div>
                            <div class="text-xs text-zinc-500">{{ $row->customer_code }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ number_format($row->current_amount, 2) }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono {{ $row->days_31_60 > 0 ? 'text-amber-600 dark:text-amber-400' : '' }}">{{ number_format($row->days_31_60, 2) }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono {{ $row->days_61_90 > 0 ? 'text-orange-600 dark:text-orange-400' : '' }}">{{ number_format($row->days_61_90, 2) }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono {{ $row->days_90_plus > 0 ? 'text-red-600 dark:text-red-400' : '' }}">{{ number_format($row->days_90_plus, 2) }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono font-semibold">{{ number_format($row->total_outstanding, 2) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-10 text-center text-zinc-400">No outstanding receivables as of this date.</flux:table.cell>
                    </flux:table.row>
                @endforelse
                @if ($rows->isNotEmpty())
                    <flux:table.row class="border-t-2 border-zinc-300 dark:border-zinc-600 font-semibold bg-zinc-50 dark:bg-zinc-800">
                        <flux:table.cell>Total</flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ $totals['current'] }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono text-amber-600 dark:text-amber-400">{{ $totals['days_31_60'] }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono text-orange-600 dark:text-orange-400">{{ $totals['days_61_90'] }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono text-red-600 dark:text-red-400">{{ $totals['days_90_plus'] }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ $totals['total'] }}</flux:table.cell>
                    </flux:table.row>
                @endif
            </flux:table.rows>
        </flux:table>
    </div>
</div>
