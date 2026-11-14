@props([
    'label',
    'value',
    'icon' => null,
    'trend' => null,       // numeric: positive = up, negative = down, null = no trend
    'trendLabel' => null,  // e.g. "vs yesterday"
    'href' => null,
    'color' => 'default',  // 'default'|'emerald'|'amber'|'red'|'blue'
])

@php
$colorMap = [
    'default' => 'text-zinc-600 bg-zinc-100 dark:text-zinc-300 dark:bg-zinc-800',
    'emerald' => 'text-emerald-600 bg-emerald-50 dark:text-emerald-400 dark:bg-emerald-950',
    'amber'   => 'text-amber-600 bg-amber-50 dark:text-amber-400 dark:bg-amber-950',
    'red'     => 'text-red-600 bg-red-50 dark:text-red-400 dark:bg-red-950',
    'blue'    => 'text-blue-600 bg-blue-50 dark:text-blue-400 dark:bg-blue-950',
];
$iconClass = $colorMap[$color] ?? $colorMap['default'];

$trendUp   = $trend !== null && $trend > 0;
$trendDown = $trend !== null && $trend < 0;
$trendFlat = $trend !== null && $trend == 0;

$trendColor = $trendUp   ? 'text-emerald-600 dark:text-emerald-400'
            : ($trendDown ? 'text-red-600 dark:text-red-400'
            : 'text-zinc-500 dark:text-zinc-400');
@endphp

<article class="relative rounded-xl border border-zinc-200 bg-white p-5 shadow-[var(--shadow-card)] dark:border-zinc-700 dark:bg-zinc-900 {{ $href ? 'transition-shadow hover:shadow-md' : '' }}">
    @if($href)
        <a href="{{ $href }}" class="absolute inset-0 rounded-xl" wire:navigate aria-label="{{ $label }}"></a>
    @endif

    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
            <p class="mt-1.5 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $value }}</p>

            @if($trend !== null)
                <p class="mt-1.5 flex items-center gap-1 text-xs font-medium {{ $trendColor }}">
                    @if($trendUp)
                        <svg class="size-3.5 shrink-0" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M3.22 11.78a.75.75 0 0 1 0-1.06l7.5-7.5H5a.75.75 0 0 1 0-1.5h7.5a.75.75 0 0 1 .75.75v7.5a.75.75 0 0 1-1.5 0V4.56l-7.5 7.5a.75.75 0 0 1-1.03-.28Z"/></svg>
                    @elseif($trendDown)
                        <svg class="size-3.5 shrink-0" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M12.78 4.22a.75.75 0 0 1 0 1.06l-7.5 7.5H11a.75.75 0 0 1 0 1.5H3.5a.75.75 0 0 1-.75-.75V6a.75.75 0 0 1 1.5 0v5.44l7.5-7.5a.75.75 0 0 1 1.03.28Z"/></svg>
                    @else
                        <svg class="size-3.5 shrink-0" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M2 8a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 8Z"/></svg>
                    @endif
                    <span>{{ $trendLabel ?? (abs((float)$trend).'%') }}</span>
                </p>
            @endif
        </div>

        @if($icon)
            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $iconClass }}" aria-hidden="true">
                <flux:icon :icon="$icon" class="size-5" />
            </div>
        @endif
    </div>
</article>
