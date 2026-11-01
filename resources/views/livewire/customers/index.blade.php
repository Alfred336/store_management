<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Customers</flux:heading>
            <flux:subheading>Manage customer profiles, credit terms and account statements.</flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @can('viewAny', App\Models\Customer::class)
                <flux:button :href="route('customers.export', ['format' => 'xlsx'])" icon="arrow-down-tray">Export Excel</flux:button>
                <flux:button :href="route('customers.export', ['format' => 'csv'])" icon="arrow-down-tray">Export CSV</flux:button>
            @endcan
            @can('create', App\Models\Customer::class)
                <flux:button variant="primary" icon="plus" wire:click="openForm">New customer</flux:button>
            @endcan
        </div>
    </div>

    @can('create', App\Models\Customer::class)
        <form wire:submit="importCustomers" class="flex flex-wrap items-end gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="min-w-64 flex-1"><flux:input type="file" wire:model="importFile" label="Import customers (CSV or Excel)" accept=".csv,.txt,.xls,.xlsx" /></div>
            <flux:button type="submit" variant="filled">Import customers</flux:button>
            <flux:text size="sm">Use headings: name, type, phone, email, address, tin, notes, credit_limit, payment_terms_days, is_active.</flux:text>
        </form>
    @endcan

    <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_12rem_12rem]">
        <flux:input wire:model.live.debounce.300ms="search" label="Search customers" placeholder="Name, code, phone or email" />
        <flux:select wire:model.live="typeFilter" label="Customer type">
            <option value="all">All types</option><option value="individual">Individual</option><option value="business">Business</option>
        </flux:select>
        <flux:select wire:model.live="statusFilter" label="Status">
            <option value="active">Active</option><option value="inactive">Inactive</option><option value="all">All customers</option>
        </flux:select>
    </div>

    @include('livewire.customers.partials.table')
    @include('livewire.customers.partials.form')
    @include('livewire.customers.partials.details')
    @include('livewire.customers.partials.delete-confirmation')
</div>