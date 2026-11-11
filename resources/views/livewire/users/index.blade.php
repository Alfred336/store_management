<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Users</flux:heading>
            <flux:subheading>Manage user accounts, roles and access.</flux:subheading>
        </div>
        @can('users.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreate">New user</flux:button>
        @endcan
    </div>

    <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_12rem_12rem]">
        <x-search-input label="Search" placeholder="Name or email" :current-value="$search" />
        <flux:select wire:model.live="roleFilter" label="Role">
            <option value="">All roles</option>
            @foreach ($availableRoles as $role)
                <option value="{{ $role }}">{{ $role }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="statusFilter" label="Status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="all">All users</option>
            <option value="deleted">Deleted (anonymized)</option>
        </flux:select>
    </div>

    @include('livewire.users.partials.table')
    @include('livewire.users.partials.form')
    @include('livewire.users.partials.details')
    @include('livewire.users.partials.delete-confirmation')
</div>
