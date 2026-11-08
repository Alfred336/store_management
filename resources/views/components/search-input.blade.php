@props(['label' => 'Search', 'placeholder' => 'Search…', 'currentValue' => ''])

<div class="flex items-end gap-2">
    <div class="min-w-0 flex-1">
        <flux:input
            wire:model.live.debounce.300ms="search"
            :label="$label"
            :placeholder="$placeholder"
            autocomplete="off"
        />
    </div>
    @if ($currentValue !== '')
        <flux:button
            icon="x-mark"
            wire:click="clearSearch"
            class="shrink-0"
            aria-label="Clear search"
        />
    @endif
</div>
