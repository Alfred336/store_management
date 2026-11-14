@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="$companyName ?? config('app.name')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent text-accent-foreground">
            <x-app-logo-icon class="size-5 fill-current text-white" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$companyName ?? config('app.name')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent text-accent-foreground">
            <x-app-logo-icon class="size-5 fill-current text-white" />
        </x-slot>
    </flux:brand>
@endif
