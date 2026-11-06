<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">POS Sessions</flux:heading>
            <flux:subheading>Closed sessions with cash reconciliation summary.</flux:subheading>
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
                <flux:table.column>Opened</flux:table.column>
                <flux:table.column>Location / Cashier</flux:table.column>
                <flux:table.column class="text-right">Opening Cash</flux:table.column>
                <flux:table.column class="text-right">Expected Cash</flux:table.column>
                <flux:table.column class="text-right">Counted Cash</flux:table.column>
                <flux:table.column class="text-right">Difference</flux:table.column>
                <flux:table.column>Status</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($sessions as $session)
                    <flux:table.row :key="$session->id">
                        <flux:table.cell class="font-mono text-xs">
                            {{ \Carbon\Carbon::parse($session->opened_at)->setTimezone('Africa/Dar_es_Salaam')->format('d M Y H:i') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium">{{ $session->location_name }}</div>
                            <div class="text-xs text-zinc-500">{{ $session->cashier_name }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ number_format($session->opening_cash, 2) }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ $session->expected_cash !== null ? number_format($session->expected_cash, 2) : '—' }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ $session->closing_cash_counted !== null ? number_format($session->closing_cash_counted, 2) : '—' }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono {{ ($session->difference ?? 0) < 0 ? 'text-red-600 dark:text-red-400' : (($session->difference ?? 0) > 0 ? 'text-green-600 dark:text-green-400' : '') }}">
                            {{ $session->difference !== null ? number_format($session->difference, 2) : '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium
                                {{ $session->status === 'open' ? 'bg-green-500/10 text-green-700 dark:text-green-300' : 'bg-zinc-500/10 text-zinc-700 dark:text-zinc-300' }}">
                                {{ ucfirst($session->status) }}
                            </span>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-400">No sessions for the selected period.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    {{ $sessions->links() }}
</div>
