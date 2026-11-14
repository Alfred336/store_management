<div class="space-y-4">
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-(--shadow-card) dark:border-zinc-700 dark:bg-zinc-900"
         wire:loading.class="opacity-50">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>
                    <button type="button" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white"
                            wire:click="sort('name')">
                        Product
                        @if($sortBy === 'name')
                            <span aria-label="{{ $sortDirection === 'asc' ? 'sorted ascending' : 'sorted descending' }}">
                                @if($sortDirection === 'asc')
                                    <svg class="size-3.5" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 3.5 3.5 9.5h9L8 3.5Z"/></svg>
                                @else
                                    <svg class="size-3.5" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 12.5 12.5 6.5h-9L8 12.5Z"/></svg>
                                @endif
                            </span>
                        @else
                            <svg class="size-3.5 text-zinc-300 dark:text-zinc-600" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5 7.5 8 3.5l3 4H5ZM5 8.5h6l-3 4-3-4Z"/></svg>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>Category</flux:table.column>
                <flux:table.column>Base unit</flux:table.column>
                <flux:table.column class="text-right">
                    <button type="button" class="flex items-center gap-1 ml-auto hover:text-zinc-900 dark:hover:text-white"
                            wire:click="sort('total_qty')">
                        @if($sortBy === 'total_qty')
                            <span aria-label="{{ $sortDirection === 'asc' ? 'sorted ascending' : 'sorted descending' }}">
                                @if($sortDirection === 'asc')
                                    <svg class="size-3.5" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 3.5 3.5 9.5h9L8 3.5Z"/></svg>
                                @else
                                    <svg class="size-3.5" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 12.5 12.5 6.5h-9L8 12.5Z"/></svg>
                                @endif
                            </span>
                        @else
                            <svg class="size-3.5 text-zinc-300 dark:text-zinc-600" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5 7.5 8 3.5l3 4H5ZM5 8.5h6l-3 4-3-4Z"/></svg>
                        @endif
                        Qty
                    </button>
                </flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column class="text-right">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($products as $product)
                    <flux:table.row :key="$product->id">
                        <flux:table.cell>
                            <button type="button"
                                    class="text-start font-medium hover:underline"
                                    wire:click="show({{ $product->id }})">
                                {{ $product->name }}
                            </button>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $product->sku }}</div>
                        </flux:table.cell>

                        <flux:table.cell class="text-zinc-600 dark:text-zinc-300">
                            {{ $product->category?->name ?? '—' }}
                        </flux:table.cell>

                        <flux:table.cell class="text-zinc-600 dark:text-zinc-300">
                            {{ $product->baseUnit->abbreviation }}
                        </flux:table.cell>

                        <flux:table.cell class="text-right tabular-nums text-zinc-700 dark:text-zinc-200">
                            {{ $product->total_qty ?? '0.000' }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1.5">
                                @if ($outOfStockIds->contains($product->id))
                                    <x-status-badge status="out_of_stock" />
                                @elseif ($lowStockIds->contains($product->id))
                                    <x-status-badge status="low_stock" />
                                @endif
                                @if (! $product->is_active)
                                    <x-status-badge status="inactive" />
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                @can('update', $product)
                                    <flux:button size="sm" variant="ghost" wire:click="openForm({{ $product->id }})">
                                        Edit
                                    </flux:button>
                                @endcan
                                @can('delete', $product)
                                    <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $product->id }})">
                                        Archive
                                    </flux:button>
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <x-empty-state
                                icon="cube"
                                title="No products found"
                                :description="$search ? 'Try a different search term.' : 'Get started by adding your first product.'"
                            >
                                @can('create', App\Models\Product::class)
                                    @if (!$search)
                                        <x-slot:action>
                                            <flux:button variant="primary" icon="plus" wire:click="openForm">
                                                New product
                                            </flux:button>
                                        </x-slot:action>
                                    @endif
                                @endcan
                            </x-empty-state>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{ $products->links() }}
</div>
