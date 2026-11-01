<div class="space-y-4">
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white p-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns><flux:table.column><button type="button" wire:click="sort('name')">Name</button></flux:table.column><flux:table.column><button type="button" wire:click="sort('products_count')">Products</button></flux:table.column><flux:table.column>Actions</flux:table.column></flux:table.columns>
            <flux:table.rows>
                @forelse ($categories as $category)
                    <flux:table.row :key="$category->id">
                        <flux:table.cell><button type="button" class="font-medium hover:underline" wire:click="show({{ $category->id }})">{{ $category->name }}</button></flux:table.cell>
                        <flux:table.cell>{{ $category->products_count }}</flux:table.cell>
                        <flux:table.cell><div class="flex items-center gap-2">@can('update', $category)<flux:button size="sm" variant="ghost" wire:click="openForm({{ $category->id }})">Edit</flux:button>@endcan @can('delete', $category)<flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $category->id }})">Delete</flux:button>@endcan</div></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="3" class="py-10 text-center text-zinc-500">No categories found.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    {{ $categories->links() }}
</div>