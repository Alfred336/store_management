<div class="space-y-4">
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white p-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns><flux:table.column><button type="button" wire:click="sort('name')">Name</button></flux:table.column><flux:table.column><button type="button" wire:click="sort('type')">Type</button></flux:table.column><flux:table.column><button type="button" wire:click="sort('is_active')">Status</button></flux:table.column><flux:table.column>Stock records</flux:table.column><flux:table.column>Actions</flux:table.column></flux:table.columns>
            <flux:table.rows>
                @forelse ($locations as $location)
                    <flux:table.row :key="$location->id">
                        <flux:table.cell><button type="button" class="font-medium hover:underline" wire:click="show({{ $location->id }})">{{ $location->name }}</button></flux:table.cell>
                        <flux:table.cell class="capitalize">{{ $location->type }}</flux:table.cell>
                        <flux:table.cell><span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $location->is_active ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-300' }}">{{ $location->is_active ? 'Active' : 'Inactive' }}</span></flux:table.cell>
                        <flux:table.cell>{{ $location->stock_levels_count }} levels · {{ $location->stock_movements_count }} movements</flux:table.cell>
                        <flux:table.cell><div class="flex items-center gap-2">@can('update', $location)<flux:button size="sm" variant="ghost" wire:click="openForm({{ $location->id }})">Edit / deactivate</flux:button>@endcan @can('delete', $location)<flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $location->id }})">Delete</flux:button>@endcan</div></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="5" class="py-10 text-center text-zinc-500">No locations found.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    {{ $locations->links() }}
</div>