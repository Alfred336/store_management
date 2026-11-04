<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Payments</flux:heading>
            <flux:subheading>Record and manage customer payments.</flux:subheading>
        </div>
        @can('create', App\Models\Payment::class)
            <flux:button variant="primary" icon="plus" wire:click="openForm">Receive payment</flux:button>
        @endcan
    </div>

    {{-- Filters --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:input wire:model.live.debounce.300ms="search" label="Search" placeholder="Number, customer or reference" icon="magnifying-glass" />
        <flux:select wire:model.live="methodFilter" label="Method">
            <option value="all">All methods</option>
            <option value="cash">Cash</option>
            <option value="mobile_money">Mobile money</option>
            <option value="bank_transfer">Bank transfer</option>
            <option value="cheque">Cheque</option>
            <option value="card">Card</option>
        </flux:select>
        <flux:select wire:model.live="statusFilter" label="Status">
            <option value="all">All statuses</option>
            <option value="active">Active</option>
            <option value="reversed">Reversed</option>
        </flux:select>
        <flux:input wire:model.live.debounce.300ms="customerFilter" label="Customer" placeholder="Filter by name" />
        <flux:input wire:model.live="dateFrom" label="From date" type="date" />
        <flux:input wire:model.live="dateTo" label="To date" type="date" />
    </div>

    @include('livewire.payments.partials.table')
    @include('livewire.payments.partials.form')
    @include('livewire.payments.partials.details')
    @include('livewire.payments.partials.reverse-confirmation')
</div>
