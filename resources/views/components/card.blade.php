@props(['padding' => true, 'title' => null, 'subtitle' => null])

<div {{ $attributes->class('rounded-xl border border-zinc-200 bg-white shadow-[var(--shadow-card)] dark:border-zinc-700 dark:bg-zinc-900') }}>
    @if($title)
        <div class="{{ $padding ? 'px-5 pt-5' : 'px-5 pt-4' }} flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $title }}</h2>
                @if($subtitle)
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $subtitle }}</p>
                @endif
            </div>
            @if(isset($actions))
                <div>{{ $actions }}</div>
            @endif
        </div>
    @endif

    @if($padding)
        <div class="{{ $title ? 'pt-3' : '' }} p-5">{{ $slot }}</div>
    @else
        {{ $slot }}
    @endif
</div>
