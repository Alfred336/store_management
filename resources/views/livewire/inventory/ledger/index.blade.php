<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <div>
        <flux:heading size="xl">Stock Ledger</flux:heading>
        <flux:subheading>Read-only history of all stock movements.</flux:subheading>
    </div>

    {{-- Filters --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-search-input label="Search product" placeholder="Name or SKU" :current-value="$search" />
        <flux:select wire:model.live="productFilter" label="Product">
            <option value="">All products</option>
            @foreach ($products as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="locationFilter" label="Location">
            <option value="">All locations</option>
            @foreach ($locations as $loc)
                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="typeFilter" label="Movement type">
            <option value="">All types</option>
            @foreach ($movementTypes as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="userFilter" label="User">
            <option value="">All users</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
        </flux:select>
        <div class="grid grid-cols-2 gap-2">
            <flux:input wire:model.live="dateFrom" label="Date from" type="date" />
            <flux:input wire:model.live="dateTo" label="Date to" type="date" />
        </div>
    </div>

    @include('livewire.inventory.ledger.partials.table')
</div>
