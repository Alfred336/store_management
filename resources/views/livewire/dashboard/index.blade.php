<div class="mx-auto w-full max-w-7xl space-y-8 p-4 lg:p-8">

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <x-page-header
            title="Dashboard"
            :subtitle="'Welcome back, '.auth()->user()->name"
        />

        {{-- Period + location filters --}}
        <div class="flex flex-wrap items-center gap-2">
            {{-- Period selector --}}
            <div class="flex overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700" role="group" aria-label="Period">
                @foreach(['today' => 'Today', '7d' => '7 Days', 'month' => 'This Month'] as $val => $lbl)
                    <button
                        wire:click="$set('period', '{{ $val }}')"
                        class="px-3 py-1.5 text-sm font-medium transition-colors
                               {{ $period === $val
                                   ? 'bg-accent text-white dark:text-white'
                                   : 'bg-white text-zinc-600 hover:bg-zinc-50 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800'
                               }}"
                        aria-pressed="{{ $period === $val ? 'true' : 'false' }}"
                    >
                        {{ $lbl }}
                    </button>
                @endforeach
            </div>

            {{-- Location filter --}}
            @if($locations->count() > 1)
                <flux:select wire:model.live="locationId" class="text-sm" aria-label="Filter by location">
                    <flux:select.option value="">All Locations</flux:select.option>
                    @foreach($locations as $loc)
                        <flux:select.option :value="$loc->id">{{ $loc->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </div>
    </div>

    {{-- ── Quick actions ──────────────────────────────────────────────── --}}
    <div class="flex flex-wrap gap-2">
        @can('pos.use')
            <flux:button :href="route('pos.index')" icon="computer-desktop" size="sm" wire:navigate>New Sale (POS)</flux:button>
        @endcan
        @can('viewAny', App\Models\Invoice::class)
            <flux:button :href="route('invoices.index')" icon="document-text" size="sm" variant="outline" wire:navigate>New Invoice</flux:button>
        @endcan
        @can('viewAny', App\Models\Quotation::class)
            <flux:button :href="route('quotations.index')" icon="document-magnifying-glass" size="sm" variant="outline" wire:navigate>New Quotation</flux:button>
        @endcan
        @can('inventory.receive')
            <flux:button :href="route('inventory.index')" icon="archive-box" size="sm" variant="outline" wire:navigate>Receive Stock</flux:button>
        @endcan
        @can('viewAny', App\Models\Payment::class)
            <flux:button :href="route('payments.index')" icon="banknotes" size="sm" variant="outline" wire:navigate>Receive Payment</flux:button>
        @endcan
        @can('viewAny', App\Models\Customer::class)
            <flux:button :href="route('customers.index')" icon="user-plus" size="sm" variant="outline" wire:navigate>New Customer</flux:button>
        @endcan
    </div>

    {{-- ── Sales section ──────────────────────────────────────────────── --}}
    @if(isset($salesKpis))
        <section aria-labelledby="sales-heading">
            <h2 id="sales-heading" class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Sales</h2>

            @include('livewire.dashboard.partials.sales-kpis')

            {{-- Receivables + quotations (non-cashier only) --}}
            @if(isset($receivablesKpis))
                <div class="mt-4">
                    @include('livewire.dashboard.partials.receivables')
                </div>

                @if(isset($quotationKpis) && ($quotationKpis['open_count'] > 0))
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <x-stat-card
                            label="Open Quotations"
                            :value="number_format($quotationKpis['open_count'])"
                            icon="document-magnifying-glass"
                            color="blue"
                            :href="auth()->user()?->can('viewAny', App\Models\Quotation::class) ? route('quotations.index') : null"
                        />
                        <x-stat-card
                            label="Open Quotations Value"
                            :value="'TZS '.number_format((float) $quotationKpis['open_value'])"
                            icon="document-check"
                            color="blue"
                        />
                    </div>
                @endif
            @endif
        </section>

        {{-- ── Charts ────────────────────────────────────────────────── --}}
        @if(!$isCashier && isset($salesByDayChart, $salesByCatChart, $paymentsByMethod))
            <section aria-labelledby="charts-heading">
                <h2 id="charts-heading" class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Trends</h2>

                <div class="grid gap-4 lg:grid-cols-3">
                    {{-- Sales line chart --}}
                    <x-card title="Sales – last 30 days" class="lg:col-span-2" :padding="false">
                        <div class="p-4">
                            <x-chart
                                type="line"
                                :chart-data="$salesByDayChart"
                                height="220"
                                title=""
                            />
                        </div>
                    </x-card>

                    {{-- Payments doughnut --}}
                    <x-card title="Payments by Method">
                        <x-chart
                            type="doughnut"
                            :chart-data="$paymentsByMethod"
                            height="220"
                        />
                        @if(empty($paymentsByMethod['labels']))
                            <x-empty-state icon="banknotes" title="No payments yet" class="py-6" />
                        @endif
                    </x-card>
                </div>

                <div class="mt-4">
                    <x-card title="Sales by Category" :padding="false">
                        <div class="p-4">
                            <x-chart
                                type="bar"
                                :chart-data="$salesByCatChart"
                                height="180"
                            />
                        </div>
                    </x-card>
                </div>
            </section>
        @endif

        {{-- ── Recent invoices ────────────────────────────────────────── --}}
        @if(isset($recentInvoices) && $recentInvoices->isNotEmpty())
            <section aria-labelledby="recent-heading">
                <div class="mb-3 flex items-center justify-between">
                    <h2 id="recent-heading" class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Recent Invoices</h2>
                    @can('viewAny', App\Models\Invoice::class)
                        <flux:link :href="route('invoices.index')" wire:navigate class="text-xs">View all</flux:link>
                    @endcan
                </div>

                <x-card :padding="false">
                    <x-data-table>
                        <x-slot name="head">
                            <th class="px-4 py-3">Number</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Date</th>
                        </x-slot>

                        @foreach($recentInvoices as $inv)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $inv->number ?? '—' }}</td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $inv->customer_name }}</td>
                                <td class="px-4 py-3"><x-status-badge :status="$inv->status" /></td>
                                <td class="px-4 py-3 text-right tabular-nums text-zinc-700 dark:text-zinc-200">
                                    TZS {{ number_format((float) $inv->total) }}
                                </td>
                                <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">
                                    {{ $inv->issued_at ? \Carbon\Carbon::parse($inv->issued_at)->format('j M Y') : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </x-data-table>
                </x-card>
            </section>
        @endif

        {{-- ── Top products ────────────────────────────────────────────── --}}
        @if(!$isCashier && isset($topProducts) && $topProducts->isNotEmpty())
            <section aria-labelledby="top-products-heading">
                <div class="mb-3 flex items-center justify-between">
                    <h2 id="top-products-heading" class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Top Products</h2>
                </div>

                <x-card :padding="false">
                    <x-data-table>
                        <x-slot name="head">
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3 text-right">Revenue</th>
                            <th class="px-4 py-3 text-right">Units Sold</th>
                        </x-slot>

                        @foreach($topProducts as $prod)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $prod->name }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-zinc-700 dark:text-zinc-200">TZS {{ number_format((float) $prod->revenue) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-zinc-500 dark:text-zinc-400">{{ number_format((float) $prod->qty_sold, 1) }}</td>
                            </tr>
                        @endforeach
                    </x-data-table>
                </x-card>
            </section>
        @endif

        {{-- ── Overdue invoices ────────────────────────────────────────── --}}
        @if(!$isCashier && isset($overdueInvoices) && $overdueInvoices->isNotEmpty())
            <section aria-labelledby="overdue-heading">
                <div class="mb-3 flex items-center justify-between">
                    <h2 id="overdue-heading" class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 flex items-center gap-2">
                        <span class="size-2 rounded-full bg-red-500" aria-hidden="true"></span>
                        Overdue Invoices
                    </h2>
                    @can('viewAny', App\Models\Invoice::class)
                        <flux:link :href="route('invoices.index')" wire:navigate class="text-xs">View all</flux:link>
                    @endcan
                </div>

                <x-card :padding="false">
                    <x-data-table>
                        <x-slot name="head">
                            <th class="px-4 py-3">Number</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Due Date</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                        </x-slot>

                        @foreach($overdueInvoices as $inv)
                            <tr class="hover:bg-red-50/50 dark:hover:bg-red-950/20">
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $inv->number }}</td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $inv->customer_name }}</td>
                                <td class="px-4 py-3 text-red-600 dark:text-red-400">
                                    {{ \Carbon\Carbon::parse($inv->due_date)->format('j M Y') }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums font-semibold text-red-600 dark:text-red-400">
                                    TZS {{ number_format((float) $inv->balance) }}
                                </td>
                            </tr>
                        @endforeach
                    </x-data-table>
                </x-card>
            </section>
        @endif
    @endif

    {{-- ── Inventory section ──────────────────────────────────────────── --}}
    @if(isset($inventoryKpis))
        <section aria-labelledby="inventory-heading">
            <h2 id="inventory-heading" class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Inventory</h2>

            @include('livewire.dashboard.partials.inventory-kpis')

            @if(isset($urgentItems) && $urgentItems->isNotEmpty())
                <div class="mt-4">
                    <x-card title="Urgent Items" :padding="false">
                        <x-slot name="actions">
                            <flux:link :href="route('inventory.index')" wire:navigate class="text-xs">View all stock</flux:link>
                        </x-slot>

                        <x-data-table>
                            <x-slot name="head">
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Qty</th>
                            </x-slot>

                            @foreach($urgentItems as $item)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $item->name }}</td>
                                    <td class="px-4 py-3"><x-status-badge :status="$item->issue_type" /></td>
                                    <td class="px-4 py-3 text-right tabular-nums text-zinc-500 dark:text-zinc-400">
                                        {{ number_format((float) $item->qty, 1) }}
                                    </td>
                                </tr>
                            @endforeach
                        </x-data-table>
                    </x-card>
                </div>
            @endif
        </section>
    @endif

    {{-- ── Empty state for no permissions ────────────────────────────── --}}
    @if(!isset($salesKpis) && !isset($inventoryKpis))
        <x-empty-state
            icon="chart-bar"
            title="No data to display"
            description="Your account does not have access to any dashboard sections. Contact your administrator."
        />
    @endif

</div>
