<div class="space-y-4">
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white p-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Product</flux:table.column>
                <flux:table.column>Location</flux:table.column>
                <flux:table.column>Batch / Expiry</flux:table.column>
                <flux:table.column>Quantity</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($levels as $level)
                    <flux:table.row :key="$level->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $level->product->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $level->product->sku }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $level->location->name }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($level->batch)
                                <div class="font-mono text-xs">{{ $level->batch->batch_number }}</div>
                                @if ($level->batch->expiry_date)
                                    <div class="text-xs text-zinc-500">{{ $level->batch->expiry_date->format('d M Y') }}</div>
                                @endif
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="font-mono">{{ $level->quantity }}</span>
                            <span class="text-xs text-zinc-500">{{ $level->product->baseUnit->abbreviation }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $productId = $level->product_id;
                                $totalQty = $productTotals[$productId] ?? '0';
                                $reorder = $reorderLevels[$productId] ?? '0';
                                $isOutOfStock = bccomp($totalQty, '0', 3) === 0;
                                $isLowStock = !$isOutOfStock
                                    && bccomp($reorder, '0', 3) > 0
                                    && bccomp($totalQty, $reorder, 3) <= 0;
                            @endphp
                            @if ($isOutOfStock)
                                <span class="rounded-full bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-700 dark:text-red-300">Out of stock</span>
                            @elseif ($isLowStock)
                                <span class="rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-700 dark:text-amber-300">Low stock</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap items-center gap-1.5">
                                @can('inventory.receive')
                                    <flux:button size="sm" variant="ghost"
                                        wire:click="openReceive({{ $level->product_id }}, '{{ $level->location_id }}')">
                                        Receive
                                    </flux:button>
                                @endcan
                                @can('inventory.adjust')
                                    <flux:button size="sm" variant="ghost"
                                        wire:click="openAdjust({{ $level->product_id }}, '{{ $level->location_id }}', {{ $level->batch_id ?? 'null' }})">
                                        Adjust
                                    </flux:button>
                                @endcan
                                @can('inventory.transfer')
                                    <flux:button size="sm" variant="ghost"
                                        wire:click="openTransfer({{ $level->product_id }}, '{{ $level->location_id }}', {{ $level->batch_id ?? 'null' }})">
                                        Transfer
                                    </flux:button>
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-10 text-center text-zinc-400">No stock records found.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    {{ $levels->links() }}
</div>
