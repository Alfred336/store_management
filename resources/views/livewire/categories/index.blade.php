<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
        <div class="flex flex-wrap items-end justify-between gap-4"><div><flux:heading size="xl">Categories</flux:heading><flux:subheading>Organize your product catalog.</flux:subheading></div>@can('create', App\Models\Category::class)<flux:button variant="primary" icon="plus" wire:click="openForm">New category</flux:button>@endcan</div>
        <x-search-input label="Search categories" placeholder="Category name" :current-value="$search" />
        @include('livewire.categories.partials.table')
        @include('livewire.categories.partials.form')
        @include('livewire.categories.partials.details')
        @include('livewire.categories.partials.delete-confirmation')
</div>
