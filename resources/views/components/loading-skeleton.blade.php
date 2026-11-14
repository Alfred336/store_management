@props(['lines' => 3, 'height' => 'h-4'])

<div {{ $attributes->class('animate-pulse space-y-2') }} aria-busy="true" aria-label="Loading…">
    @for($i = 0; $i < $lines; $i++)
        <div class="{{ $height }} rounded {{ $i === $lines - 1 ? 'w-3/4' : 'w-full' }} bg-zinc-200 dark:bg-zinc-700"></div>
    @endfor
</div>
