<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Invoices</flux:heading>
            <flux:subheading>Create, issue and manage back-office invoices.</flux:subheading>
        </div>
        @can('create', App\Models\Invoice::class)
            <flux:button variant="primary" icon="plus" wire:click="openForm">New invoice</flux:button>
        @endcan
    </div>

    {{-- Filters --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:input wire:model.live.debounce.300ms="search" label="Search" placeholder="Number or customer name" icon="magnifying-glass" />
        <flux:select wire:model.live="statusFilter" label="Status">
            <option value="all">All statuses</option>
            <option value="draft">Draft</option>
            <option value="issued">Issued</option>
            <option value="partially_paid">Partially paid</option>
            <option value="paid">Paid</option>
            <option value="void">Void</option>
        </flux:select>
        <flux:input wire:model.live="dateFrom" label="From date" type="date" />
        <flux:input wire:model.live="dateTo" label="To date" type="date" />
        <flux:input wire:model.live.debounce.300ms="customerFilter" label="Customer" placeholder="Filter by customer name" />
        <div class="flex items-end gap-3">
            <flux:checkbox wire:model.live="overdueOnly" label="Overdue only" />
        </div>
    </div>

    @include('livewire.invoicing.partials.table')
    @include('livewire.invoicing.partials.form')
    @include('livewire.invoicing.partials.details')
    @include('livewire.invoicing.partials.issue-confirmation')
    @include('livewire.invoicing.partials.void-confirmation')
    @include('livewire.invoicing.partials.delete-confirmation')
</div>
