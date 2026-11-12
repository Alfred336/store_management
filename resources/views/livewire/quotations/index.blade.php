<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Quotations</flux:heading>
            <flux:subheading>Create, send and manage sales quotations and proposals.</flux:subheading>
        </div>
        @can('create', App\Models\Quotation::class)
            <flux:button variant="primary" icon="plus" wire:click="openForm">New quotation</flux:button>
        @endcan
    </div>

    {{-- Filters --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-search-input label="Search" placeholder="Number, customer or prospect" :current-value="$search" />
        <flux:select wire:model.live="statusFilter" label="Status">
            <option value="all">All statuses</option>
            <option value="draft">Draft</option>
            <option value="sent">Sent</option>
            <option value="expired">Expired</option>
            <option value="accepted">Accepted</option>
            <option value="rejected">Rejected</option>
            <option value="converted">Converted</option>
        </flux:select>
        <flux:input wire:model.live="dateFrom" label="From date" type="date" />
        <flux:input wire:model.live="dateTo" label="To date" type="date" />
        <flux:input wire:model.live.debounce.300ms="customerFilter" label="Customer / Prospect" placeholder="Filter by name" />
        <flux:select wire:model.live="locationFilter" label="Location">
            <option value="">All locations</option>
            @foreach($locations as $loc)
                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
            @endforeach
        </flux:select>
    </div>

    @include('livewire.quotations.partials.table')
    @include('livewire.quotations.partials.form')
    @include('livewire.quotations.partials.details')
    @include('livewire.quotations.partials.delete-confirmation')
    @include('livewire.quotations.partials.send-confirmation')
    @include('livewire.quotations.partials.status-panel')
    @include('livewire.quotations.partials.convert-panel')
    @include('livewire.quotations.partials.email-panel')
</div>
