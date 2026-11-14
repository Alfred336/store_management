<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <x-page-header
        title="Categories"
        subtitle="Organize your product catalog."
        :breadcrumbs="[['label' => 'Categories']]"
    >
        <x-slot:actions>
            @can('create', App\Models\Category::class)
                <flux:button variant="primary" icon="plus" wire:click="openForm">New category</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Toolbar --}}
    <div class="max-w-xs">
        <x-search-input label="Search categories" placeholder="Category name" :current-value="$search" />
    </div>

    @include('livewire.categories.partials.table')
    @include('livewire.categories.partials.form')
    @include('livewire.categories.partials.details')
    @include('livewire.categories.partials.delete-confirmation')
</div>
