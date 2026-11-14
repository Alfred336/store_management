@props(['icon' => 'inbox', 'title', 'description' => null])

<div {{ $attributes->class('flex flex-col items-center justify-center py-12 text-center') }}>
    <div class="flex size-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800" aria-hidden="true">
        <flux:icon :icon="$icon" class="size-6 text-zinc-400 dark:text-zinc-500" />
    </div>
    <h3 class="mt-4 text-sm font-semibold text-zinc-900 dark:text-white">{{ $title }}</h3>
    @if($description)
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
    @endif
    @if(isset($action))
        <div class="mt-4">{{ $action }}</div>
    @endif
</div>
