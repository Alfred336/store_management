<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Voided Invoices Audit</flux:heading>
            <flux:subheading>Invoices voided within the selected date range.</flux:subheading>
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
                <flux:table.column>Invoice</flux:table.column>
                <flux:table.column>Customer</flux:table.column>
                <flux:table.column>Location</flux:table.column>
                <flux:table.column>Channel</flux:table.column>
                <flux:table.column class="text-right">Total</flux:table.column>
                <flux:table.column>Voided At</flux:table.column>
                <flux:table.column>Voided By</flux:table.column>
                <flux:table.column>Reason</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($invoices as $invoice)
                    <flux:table.row :key="$invoice->id">
                        <flux:table.cell>
                            <div class="font-mono text-sm font-medium">{{ $invoice->number }}</div>
                            <div class="text-xs text-zinc-500">{{ $invoice->issue_date ? \Carbon\Carbon::parse($invoice->issue_date)->format('d M Y') : '—' }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium">{{ $invoice->customer_name }}</div>
                            <div class="text-xs text-zinc-500">{{ $invoice->customer_code }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $invoice->location_name }}</flux:table.cell>
                        <flux:table.cell>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $invoice->channel === 'pos' ? 'bg-purple-500/10 text-purple-700 dark:text-purple-300' : 'bg-blue-500/10 text-blue-700 dark:text-blue-300' }}">
                                {{ strtoupper($invoice->channel) }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ number_format($invoice->total, 2) }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">
                            {{ \Carbon\Carbon::parse($invoice->voided_at)->setTimezone('Africa/Dar_es_Salaam')->format('d M Y H:i') }}
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $invoice->voided_by_name ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="max-w-xs truncate text-sm text-zinc-500">{{ $invoice->void_reason ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-400">No voided invoices for the selected period.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    {{ $invoices->links() }}
</div>
