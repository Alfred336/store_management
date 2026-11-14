<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <x-page-header
        title="Products"
        subtitle="Catalog, stock availability, and reorder health."
        :breadcrumbs="[['label' => 'Products']]"
    >
        <x-slot:actions>
            @can('create', App\Models\Product::class)
                <flux:button variant="primary" icon="plus" wire:click="openForm">New product</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1">
            <x-search-input label="Search products" placeholder="Name or SKU" :current-value="$search" />
        </div>
        <div class="w-40 shrink-0">
            <flux:select wire:model.live="activeFilter" label="Status">
                <option value="all">All products</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </flux:select>
        </div>
        <div class="flex items-end pb-1">
            <flux:checkbox wire:model.live="lowStockOnly" label="Low stock only" />
        </div>
    </div>

    @include('livewire.products.partials.table')
    @include('livewire.products.partials.form')
    @include('livewire.products.partials.details')
    @include('livewire.products.partials.delete-confirmation')
</div>
