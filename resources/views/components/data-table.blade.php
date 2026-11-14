@props(['loading' => false])

<div {{ $attributes->class('overflow-x-auto') }}>
    <table class="w-full text-sm">
        @if(isset($head))
            <thead class="border-b border-zinc-200 dark:border-zinc-700">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    {{ $head }}
                </tr>
            </thead>
        @endif
        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 {{ $loading ? 'opacity-50' : '' }}">
            {{ $slot }}
        </tbody>
    </table>
</div>
