<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Roles &amp; Permissions</flux:heading>
            <flux:subheading>Manage roles and assign permissions to control access.</flux:subheading>
        </div>
        @can('roles.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreate">New role</flux:button>
        @endcan
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[20rem_1fr]">
        @include('livewire.roles.partials.role-list')
        @include('livewire.roles.partials.permission-matrix')
    </div>

    @include('livewire.roles.partials.create-modal')
    @include('livewire.roles.partials.rename-modal')
    @include('livewire.roles.partials.delete-modal')
    @include('livewire.roles.partials.save-confirm-modal')
</div>
