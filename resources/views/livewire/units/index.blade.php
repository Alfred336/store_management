<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div><flux:heading size="xl">Units</flux:heading><flux:subheading>Manage the measures used by your products.</flux:subheading></div>
            @can('create', App\Models\Unit::class)<flux:button variant="primary" icon="plus" wire:click="openForm">New unit</flux:button>@endcan
        </div>
        <x-search-input label="Search units" placeholder="Name or abbreviation" :current-value="$search" />
        @include('livewire.units.partials.table')
        @include('livewire.units.partials.form')
        @include('livewire.units.partials.details')
        @include('livewire.units.partials.delete-confirmation')
</div>
