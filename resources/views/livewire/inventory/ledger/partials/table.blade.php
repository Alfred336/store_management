<div class="space-y-4">
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white p-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Date</flux:table.column>
                <flux:table.column>Product</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Location</flux:table.column>
                <flux:table.column>Batch</flux:table.column>
                <flux:table.column>Quantity</flux:table.column>
                <flux:table.column>Reference</flux:table.column>
                <flux:table.column>Reason</flux:table.column>
                <flux:table.column>User</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($movements as $m)
                    <flux:table.row :key="$m->id">
                        <flux:table.cell class="whitespace-nowrap text-xs text-zinc-500">
                            {{ $m->moved_at?->format('d M Y H:i') ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium">{{ $m->product->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $m->product->sku }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $typeColors = [
                                    'receipt'      => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
                                    'sale'         => 'bg-blue-500/10 text-blue-700 dark:text-blue-300',
                                    'issue'        => 'bg-purple-500/10 text-purple-700 dark:text-purple-300',
                                    'damage'       => 'bg-red-500/10 text-red-700 dark:text-red-300',
                                    'adjustment'   => 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
                                    'transfer_in'  => 'bg-teal-500/10 text-teal-700 dark:text-teal-300',
                                    'transfer_out' => 'bg-orange-500/10 text-orange-700 dark:text-orange-300',
                                    'sale_return'  => 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
                                ];
                                $color = $typeColors[$m->type] ?? 'bg-zinc-500/10 text-zinc-600';
                            @endphp
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $color }}">
                                {{ $movementTypes[$m->type] ?? $m->type }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $m->location->name }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($m->batch)
                                <span class="font-mono text-xs">{{ $m->batch->batch_number }}</span>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $isPositive = in_array($m->type, ['receipt', 'transfer_in', 'sale_return'])
                                    || ($m->type === 'adjustment' && bccomp($m->quantity, '0', 3) > 0);
                                $qtyClass = $isPositive
                                    ? 'text-emerald-700 dark:text-emerald-300'
                                    : 'text-red-700 dark:text-red-300';
                            @endphp
                            <span class="font-mono text-sm {{ $qtyClass }}">{{ $m->quantity }}</span>
                            <span class="text-xs text-zinc-500">{{ $m->product->baseUnit->abbreviation }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="max-w-[12rem] truncate text-sm font-mono">{{ $m->reference ?? '—' }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="max-w-[14rem] truncate text-sm">{{ $m->reason ?? '—' }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $m->user?->name ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9" class="py-10 text-center text-zinc-400">No movements found.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    {{ $movements->links() }}
</div>
