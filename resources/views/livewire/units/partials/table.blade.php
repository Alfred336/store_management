<div class="space-y-4">
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-(--shadow-card) dark:border-zinc-700 dark:bg-zinc-900"
         wire:loading.class="opacity-50">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>
                    <button type="button" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white"
                            wire:click="sort('name')">
                        Name
                        @if($sortBy === 'name')
                            @if($sortDirection === 'asc')
                                <svg class="size-3.5" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 3.5 3.5 9.5h9L8 3.5Z"/></svg>
                            @else
                                <svg class="size-3.5" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 12.5 12.5 6.5h-9L8 12.5Z"/></svg>
                            @endif
                        @else
                            <svg class="size-3.5 text-zinc-300 dark:text-zinc-600" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5 7.5 8 3.5l3 4H5ZM5 8.5h6l-3 4-3-4Z"/></svg>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white"
                            wire:click="sort('abbreviation')">
                        Abbreviation
                        @if($sortBy === 'abbreviation')
                            @if($sortDirection === 'asc')
                                <svg class="size-3.5" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 3.5 3.5 9.5h9L8 3.5Z"/></svg>
                            @else
                                <svg class="size-3.5" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 12.5 12.5 6.5h-9L8 12.5Z"/></svg>
                            @endif
                        @else
                            <svg class="size-3.5 text-zinc-300 dark:text-zinc-600" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5 7.5 8 3.5l3 4H5ZM5 8.5h6l-3 4-3-4Z"/></svg>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column class="text-right">
                    <button type="button" class="flex items-center gap-1 ml-auto hover:text-zinc-900 dark:hover:text-white"
                            wire:click="sort('products_count')">
                        @if($sortBy === 'products_count')
                            @if($sortDirection === 'asc')
                                <svg class="size-3.5" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 3.5 3.5 9.5h9L8 3.5Z"/></svg>
                            @else
                                <svg class="size-3.5" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 12.5 12.5 6.5h-9L8 12.5Z"/></svg>
                            @endif
                        @else
                            <svg class="size-3.5 text-zinc-300 dark:text-zinc-600" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5 7.5 8 3.5l3 4H5ZM5 8.5h6l-3 4-3-4Z"/></svg>
                        @endif
                        Base products
                    </button>
                </flux:table.column>
                <flux:table.column class="text-right">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($units as $unit)
                    <flux:table.row :key="$unit->id">
                        <flux:table.cell>
                            <button type="button" class="font-medium hover:underline"
                                    wire:click="show({{ $unit->id }})">
                                {{ $unit->name }}
                            </button>
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-600 dark:text-zinc-300">
                            {{ $unit->abbreviation }}
                        </flux:table.cell>
                        <flux:table.cell class="text-right tabular-nums text-zinc-600 dark:text-zinc-300">
                            {{ $unit->products_count }}
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                @can('update', $unit)
                                    <flux:button size="sm" variant="ghost" wire:click="openForm({{ $unit->id }})">Edit</flux:button>
                                @endcan
                                @can('delete', $unit)
                                    <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $unit->id }})">Delete</flux:button>
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <x-empty-state
                                icon="scale"
                                title="No units found"
                                :description="$search ? 'Try a different search term.' : 'Get started by adding your first unit.'"
                            >
                                @can('create', App\Models\Unit::class)
                                    @if (!$search)
                                        <x-slot:action>
                                            <flux:button variant="primary" icon="plus" wire:click="openForm">
                                                New unit
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

    {{ $units->links() }}
</div>
