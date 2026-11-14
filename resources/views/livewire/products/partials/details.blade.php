<flux:modal name="product-details" class="w-full max-w-2xl" variant="flyout" position="right" scroll="body">
    @if ($selected)
        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ $selected->name }}</flux:heading>
                    <flux:subheading>{{ $selected->sku }}</flux:subheading>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    @if ($outOfStockIds->contains($selected->id))
                        <x-status-badge status="out_of_stock" />
                    @elseif ($lowStockIds->contains($selected->id))
                        <x-status-badge status="low_stock" />
                    @endif
                    @if (! $selected->is_active)
                        <x-status-badge status="inactive" />
                    @endif
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Category</dt>
                    <dd class="mt-1 font-medium">{{ $selected->category?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Base unit</dt>
                    <dd class="mt-1 font-medium">{{ $selected->baseUnit->name }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Total quantity</dt>
                    <dd class="mt-1 font-medium tabular-nums">{{ $selected->total_qty ?? '0.000' }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Reorder level</dt>
                    <dd class="mt-1 font-medium tabular-nums">{{ $selected->reorder_level }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Selling price</dt>
                    <dd class="mt-1 font-medium tabular-nums">{{ $selected->selling_price }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Batch tracking</dt>
                    <dd class="mt-1 font-medium">{{ $selected->track_batches ? 'Enabled' : 'Disabled' }}</dd>
                </div>
            </dl>

            @if ($selected->description)
                <div>
                    <flux:text class="font-medium">Description</flux:text>
                    <p class="mt-1 whitespace-pre-line text-sm text-zinc-600 dark:text-zinc-300">{{ $selected->description }}</p>
                </div>
            @endif

            <div>
                <flux:text class="font-medium">Packaging conversions</flux:text>
                <ul class="mt-2 space-y-2 text-sm">
                    @forelse ($selected->productUnits as $conversion)
                        <li class="flex justify-between">
                            <span>{{ $conversion->unit->name }}</span>
                            <span class="tabular-nums text-zinc-600 dark:text-zinc-300">
                                = {{ $conversion->factor }} {{ $selected->baseUnit->abbreviation }}
                            </span>
                        </li>
                    @empty
                        <li class="text-zinc-500">No additional packaging units.</li>
                    @endforelse
                </ul>
            </div>

            <flux:modal.close>
                <flux:button variant="primary">Done</flux:button>
            </flux:modal.close>
        </div>
    @endif
</flux:modal>
