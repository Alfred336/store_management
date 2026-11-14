<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <x-page-header
        title="Units"
        subtitle="Manage the measures used by your products."
        :breadcrumbs="[['label' => 'Units']]"
    >
        <x-slot:actions>
            @can('create', App\Models\Unit::class)
                <flux:button variant="primary" icon="plus" wire:click="openForm">New unit</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Toolbar --}}
    <div class="max-w-xs">
        <x-search-input label="Search units" placeholder="Name or abbreviation" :current-value="$search" />
    </div>

    @include('livewire.units.partials.table')
    @include('livewire.units.partials.form')
    @include('livewire.units.partials.details')
    @include('livewire.units.partials.delete-confirmation')
</div>
