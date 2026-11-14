@props([
    'title',
    'subtitle' => null,
    'breadcrumbs' => [], // array of ['label' => '', 'href' => ''] – last item has no href
])

<div {{ $attributes->class('mb-6') }}>
    @if(count($breadcrumbs))
        <nav class="mb-2 flex items-center gap-1.5 text-sm text-zinc-500 dark:text-zinc-400">
            @foreach($breadcrumbs as $crumb)
                @if(!$loop->last)
                    <a href="{{ $crumb['href'] }}" class="hover:text-zinc-700 dark:hover:text-zinc-200" wire:navigate>{{ $crumb['label'] }}</a>
                    <span aria-hidden="true">/</span>
                @else
                    <span class="text-zinc-700 dark:text-zinc-200">{{ $crumb['label'] }}</span>
                @endif
            @endforeach
        </nav>
    @endif

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $title }}</h1>
            @if($subtitle)
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $subtitle }}</p>
            @endif
        </div>

        @if(isset($actions))
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>
</div>
