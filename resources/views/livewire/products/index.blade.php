<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
        <div class="flex flex-wrap items-end justify-between gap-4"><div><flux:heading size="xl">Products</flux:heading><flux:subheading>Catalog, stock availability, and reorder health.</flux:subheading></div>@can('create', App\Models\Product::class)<flux:button variant="primary" icon="plus" wire:click="openForm">New product</flux:button>@endcan</div>
        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_12rem]">
            <x-search-input label="Search products" placeholder="Name or SKU" :current-value="$search" />
            <flux:select wire:model.live="activeFilter" label="Status"><option value="all">All products</option><option value="active">Active</option><option value="inactive">Inactive</option></flux:select>
        </div>
        <flux:checkbox wire:model.live="lowStockOnly" label="Show low-stock products only" />
        @include('livewire.products.partials.table')
        @include('livewire.products.partials.form')
        @include('livewire.products.partials.details')
        @include('livewire.products.partials.delete-confirmation')
</div>
