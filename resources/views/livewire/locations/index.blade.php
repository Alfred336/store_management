<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <x-page-header
        title="Locations"
        subtitle="Manage stores, warehouses, and branches."
        :breadcrumbs="[['label' => 'Locations']]"
    >
        <x-slot:actions>
            @can('create', App\Models\Location::class)
                <flux:button variant="primary" icon="plus" wire:click="openForm">New location</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1">
            <x-search-input label="Search locations" placeholder="Location name" :current-value="$search" />
        </div>
        <div class="w-40 shrink-0">
            <flux:select wire:model.live="statusFilter" label="Status">
                <option value="all">All locations</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </flux:select>
        </div>
    </div>

    @include('livewire.locations.partials.table')
    @include('livewire.locations.partials.form')
    @include('livewire.locations.partials.details')
    @include('livewire.locations.partials.delete-confirmation')
</div>
