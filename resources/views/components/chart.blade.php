@props([
    'type' => 'line',   // line | bar | doughnut
    'chartData' => [],  // array {labels:[], datasets:[...]} – JSON-serialisable
    'height' => '220',
    'title' => null,
])

<div
    x-data="appChart({{ Js::from($type) }}, {{ Js::from($chartData) }})"
    x-init="init()"
    @chart-update.window="onUpdate($event.detail)"
    wire:ignore
>
    @if($title)
        <p class="mb-3 text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $title }}</p>
    @endif
    <canvas x-ref="canvas" height="{{ $height }}" role="img" aria-label="{{ $title ?? 'Chart' }}"></canvas>
</div>
