<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">VAT Summary</flux:heading>
            <flux:subheading>Taxable, exempt, and VAT amounts from posted invoices.</flux:subheading>
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
                <flux:table.column>Date</flux:table.column>
                <flux:table.column class="text-right">Taxable Amount</flux:table.column>
                <flux:table.column class="text-right">Exempt Amount</flux:table.column>
                <flux:table.column class="text-right">VAT</flux:table.column>
                <flux:table.column class="text-right">Gross Total</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($rows as $row)
                    <flux:table.row>
                        <flux:table.cell class="font-mono text-sm">{{ \Carbon\Carbon::parse($row->period)->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ number_format($row->taxable_amount, 2) }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ number_format($row->exempt_amount, 2) }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono text-blue-600 dark:text-blue-400">{{ number_format($row->vat_amount, 2) }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono font-semibold">{{ number_format($row->gross_total, 2) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-10 text-center text-zinc-400">No data for the selected period.</flux:table.cell>
                    </flux:table.row>
                @endforelse
                @if ($rows->isNotEmpty())
                    <flux:table.row class="border-t-2 border-zinc-300 dark:border-zinc-600 font-semibold bg-zinc-50 dark:bg-zinc-800">
                        <flux:table.cell>Total</flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ $totals['taxable_amount'] }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ $totals['exempt_amount'] }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono text-blue-600 dark:text-blue-400">{{ $totals['vat_amount'] }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ $totals['gross_total'] }}</flux:table.cell>
                    </flux:table.row>
                @endif
            </flux:table.rows>
        </flux:table>
    </div>
</div>
