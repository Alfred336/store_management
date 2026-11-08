<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
        <div class="flex flex-wrap items-end justify-between gap-4"><div><flux:heading size="xl">Locations</flux:heading><flux:subheading>Manage stores, warehouses, and branches.</flux:subheading></div>@can('create', App\Models\Location::class)<flux:button variant="primary" icon="plus" wire:click="openForm">New location</flux:button>@endcan</div>
        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_12rem]">
            <x-search-input label="Search locations" placeholder="Location name" :current-value="$search" />
            <flux:select wire:model.live="statusFilter" label="Status"><option value="all">All locations</option><option value="active">Active</option><option value="inactive">Inactive</option></flux:select>
        </div>
        @include('livewire.locations.partials.table')
        @include('livewire.locations.partials.form')
        @include('livewire.locations.partials.details')
        @include('livewire.locations.partials.delete-confirmation')
</div>
