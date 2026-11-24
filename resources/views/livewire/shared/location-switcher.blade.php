<div>
@if ($locations->count() > 1)
    <div class="px-3 pb-2 pt-1">
        <flux:dropdown>
            <flux:button
                icon="map-pin"
                icon-trailing="chevron-down"
                size="sm"
                variant="ghost"
                class="w-full justify-between truncate"
            >
                {{ $locations->firstWhere('id', $currentId)?->name ?? 'Select location' }}
            </flux:button>

            <flux:menu>
                @foreach ($locations as $location)
                    <flux:menu.item
                        wire:click="switchTo({{ $location->id }})"
                        :class="$currentId === $location->id ? 'font-semibold' : ''"
                    >
                        {{ $location->name }}
                        <flux:text class="ml-1 text-xs text-zinc-400">{{ $location->type }}</flux:text>
                        @if ($currentId === $location->id)
                            <flux:icon name="check" class="ml-auto h-4 w-4 text-accent" />
                        @endif
                    </flux:menu.item>
                @endforeach
            </flux:menu>
        </flux:dropdown>
    </div>
@elseif ($locations->count() === 1)
    <div class="px-3 pb-2 pt-1">
        <div class="flex items-center gap-1.5 px-2 py-1 text-xs text-zinc-500 dark:text-zinc-400">
            <flux:icon name="map-pin" class="h-3.5 w-3.5 shrink-0" />
            <span class="truncate">{{ $locations->first()->name }}</span>
        </div>
    </div>
@endif
</div>
