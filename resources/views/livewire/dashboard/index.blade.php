<div class="mx-auto w-full max-w-7xl space-y-6 p-6 lg:p-10">
    <div>
        <flux:heading size="xl">Dashboard</flux:heading>
        <flux:subheading>Live inventory overview.</flux:subheading>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <article class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text>Active products</flux:text>
            <p class="mt-3 text-3xl font-semibold">{{ number_format($activeProducts) }}</p>
        </article>
        <article class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text>Low-stock products</flux:text>
            <p class="mt-3 text-3xl font-semibold {{ $lowStockProducts > 0 ? 'text-amber-600 dark:text-amber-400' : '' }}">{{ number_format($lowStockProducts) }}</p>
        </article>
        <article class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text>Out-of-stock products</flux:text>
            <p class="mt-3 text-3xl font-semibold {{ $outOfStockProducts > 0 ? 'text-red-600 dark:text-red-400' : '' }}">{{ number_format($outOfStockProducts) }}</p>
        </article>
        <article class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text>Active locations</flux:text>
            <p class="mt-3 text-3xl font-semibold">{{ number_format($activeLocations) }}</p>
        </article>
        <article class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text>Stock movements today</flux:text>
            <p class="mt-3 text-3xl font-semibold">{{ number_format($movementsToday) }}</p>
        </article>
    </div>

    <section class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg">Sales and receivables</flux:heading>
        <flux:subheading class="mt-2">Live sales, profit and receivables metrics will be available when invoicing and payments are implemented. No placeholder totals are shown.</flux:subheading>
    </section>
</div>