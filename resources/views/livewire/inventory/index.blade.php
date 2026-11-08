<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Stock on Hand</flux:heading>
            <flux:subheading>Current stock levels by product, location, and batch.</flux:subheading>
        </div>
        @can('inventory.receive')
            <flux:button variant="primary" icon="arrow-down-tray" wire:click="openReceive">Receive stock</flux:button>
        @endcan
    </div>

    {{-- Filters --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-search-input label="Search" placeholder="Name, SKU, barcode" :current-value="$search" />
        <flux:select wire:model.live="locationFilter" label="Location">
            <option value="">All locations</option>
            @foreach ($locations as $loc)
                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="categoryFilter" label="Category">
            <option value="">All categories</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
        </flux:select>
        <div class="flex flex-col gap-2">
            <flux:label>Quick filters</flux:label>
            <div class="flex flex-wrap gap-4">
                <flux:checkbox wire:model.live="outOfStockOnly" label="Out of stock" />
                <flux:checkbox wire:model.live="lowStockOnly" label="Low stock" />
            </div>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <flux:label>Expiring within</flux:label>
        <flux:select wire:model.live="expiringDays" class="w-40">
            <option value="0">— any —</option>
            <option value="7">7 days</option>
            <option value="30">30 days</option>
            <option value="60">60 days</option>
            <option value="90">90 days</option>
        </flux:select>
    </div>

    @include('livewire.inventory.partials.table')
    @include('livewire.inventory.partials.receive-form')
    @include('livewire.inventory.partials.adjust-form')
    @include('livewire.inventory.partials.transfer-form')
</div>
