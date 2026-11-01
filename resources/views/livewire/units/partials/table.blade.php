<div class="space-y-4">
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white p-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column><button type="button" wire:click="sort('name')">Name</button></flux:table.column>
                <flux:table.column><button type="button" wire:click="sort('abbreviation')">Abbreviation</button></flux:table.column>
                <flux:table.column><button type="button" wire:click="sort('products_count')">Base products</button></flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($units as $unit)
                    <flux:table.row :key="$unit->id">
                        <flux:table.cell><button type="button" class="font-medium hover:underline" wire:click="show({{ $unit->id }})">{{ $unit->name }}</button></flux:table.cell>
                        <flux:table.cell>{{ $unit->abbreviation }}</flux:table.cell>
                        <flux:table.cell>{{ $unit->products_count }}</flux:table.cell>
                        <flux:table.cell><div class="flex items-center gap-2">
                            @can('update', $unit)<flux:button size="sm" variant="ghost" wire:click="openForm({{ $unit->id }})">Edit</flux:button>@endcan
                            @can('delete', $unit)<flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $unit->id }})">Delete</flux:button>@endcan
                        </div></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="4" class="py-10 text-center text-zinc-500">No units found.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    {{ $units->links() }}
</div>