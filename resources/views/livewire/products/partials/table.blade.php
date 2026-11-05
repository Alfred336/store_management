<div class="space-y-4">
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white p-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns><flux:table.column><button type="button" wire:click="sort('name')">Product</button></flux:table.column><flux:table.column>Category</flux:table.column><flux:table.column>Base unit</flux:table.column><flux:table.column><button type="button" wire:click="sort('total_qty')">Total quantity</button></flux:table.column><flux:table.column>Status</flux:table.column><flux:table.column>Actions</flux:table.column></flux:table.columns>
            <flux:table.rows>
                @forelse ($products as $product)
                    <flux:table.row :key="$product->id">
                        <flux:table.cell><button type="button" class="text-start font-medium hover:underline" wire:click="show({{ $product->id }})">{{ $product->name }}</button><div class="text-xs text-zinc-500">{{ $product->sku }}</div></flux:table.cell>
                        <flux:table.cell>{{ $product->category?->name ?? '—' }}</flux:table.cell><flux:table.cell>{{ $product->baseUnit->abbreviation }}</flux:table.cell>
                        <flux:table.cell>{{ $product->total_qty ?? '0.000' }}</flux:table.cell>
                        <flux:table.cell><div class="flex flex-wrap gap-2">@if ($outOfStockIds->contains($product->id))<span class="rounded-full bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-700 dark:text-red-300">Out of stock</span>@elseif ($lowStockIds->contains($product->id))<span class="rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-700 dark:text-amber-300">Low stock</span>@endif @if (! $product->is_active)<span class="rounded-full bg-zinc-500/10 px-2.5 py-1 text-xs font-medium text-zinc-600 dark:text-zinc-300">Inactive</span>@endif</div></flux:table.cell>
                        <flux:table.cell><div class="flex items-center gap-2">@can('update', $product)<flux:button size="sm" variant="ghost" wire:click="openForm({{ $product->id }})">Edit / deactivate</flux:button>@endcan @can('delete', $product)<flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $product->id }})">Archive</flux:button>@endcan</div></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="6" class="py-10 text-center text-zinc-500">No products found.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    {{ $products->links() }}
</div>