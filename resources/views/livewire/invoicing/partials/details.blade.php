<flux:modal name="invoice-details" class="w-full max-w-3xl" variant="flyout" position="right" scroll="body">
    @if ($selectedInvoice)
        @php
            $inv = $selectedInvoice;
            $statusColors = [
                'draft'           => 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-300',
                'issued'          => 'bg-blue-500/10 text-blue-700 dark:text-blue-300',
                'partially_paid'  => 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
                'paid'            => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
                'void'            => 'bg-red-500/10 text-red-600 dark:text-red-300',
            ];
        @endphp

        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ $inv->number ?? 'Draft' }}</flux:heading>
                    <flux:subheading>{{ $inv->location?->name ?? '—' }} · {{ $inv->issue_date?->format('d M Y') ?? 'No issue date' }}</flux:subheading>
                </div>
                <span class="rounded-full px-3 py-1 text-sm {{ $statusColors[$inv->status->value] ?? '' }}">{{ $inv->status->label() }}</span>
            </div>

            {{-- Customer snapshot --}}
            <section class="space-y-3">
                <flux:heading size="sm">Customer</flux:heading>
                @if($inv->status === \App\Enums\InvoiceStatus::Draft)
                    {{-- Live customer data for drafts --}}
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-zinc-500">Name</dt><dd class="mt-0.5 font-medium">{{ $inv->customer?->name ?? '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Phone</dt><dd class="mt-0.5">{{ $inv->customer?->phone ?: '—' }}</dd></div>
                        <div><dt class="text-zinc-500">TIN</dt><dd class="mt-0.5">{{ $inv->customer?->tin ?: '—' }}</dd></div>
                        <div><dt class="text-zinc-500">VRN</dt><dd class="mt-0.5">{{ $inv->customer?->vrn ?: '—' }}</dd></div>
                    </dl>
                @else
                    {{-- Immutable snapshot for issued/voided invoices --}}
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-zinc-500">Name</dt><dd class="mt-0.5 font-medium">{{ $inv->customer_name ?? '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Phone</dt><dd class="mt-0.5">{{ $inv->customer_phone ?: '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Address</dt><dd class="mt-0.5">{{ $inv->customer_address ?: '—' }}</dd></div>
                        <div><dt class="text-zinc-500">TIN</dt><dd class="mt-0.5">{{ $inv->customer_tin ?: '—' }}</dd></div>
                        <div><dt class="text-zinc-500">VRN</dt><dd class="mt-0.5">{{ $inv->customer_vrn ?: '—' }}</dd></div>
                    </dl>
                @endif
            </section>

            {{-- Line items --}}
            <section class="space-y-2">
                <flux:heading size="sm">Items</flux:heading>
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <th class="px-4 py-2 text-start font-medium">Product</th>
                                <th class="px-4 py-2 text-end font-medium">Qty</th>
                                <th class="px-4 py-2 text-end font-medium">Unit price</th>
                                <th class="px-4 py-2 text-end font-medium">Discount</th>
                                <th class="px-4 py-2 text-end font-medium">Tax</th>
                                <th class="px-4 py-2 text-end font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                            @foreach($inv->items as $item)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ $item->description }}</div>
                                        @if($item->sku) <div class="text-xs text-zinc-500">{{ $item->sku }}</div> @endif
                                        {{-- Allocations for issued/partially_paid/paid invoices --}}
                                        @if($item->allocations->isNotEmpty())
                                            <div class="mt-1 space-y-0.5 text-xs text-zinc-500">
                                                @foreach($item->allocations as $alloc)
                                                    <div>
                                                        {{ $alloc->batch?->batch_number ?? 'No batch' }}
                                                        @if($alloc->batch?->expiry_date) · exp {{ $alloc->batch->expiry_date->format('d M Y') }} @endif
                                                        · {{ $alloc->base_quantity }} base units
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-end font-mono">{{ $item->quantity }} {{ $item->unit?->abbreviation }}</td>
                                    <td class="px-4 py-3 text-end font-mono">{{ $item->unit_price }}</td>
                                    <td class="px-4 py-3 text-end font-mono">{{ $item->discount_amount }}</td>
                                    <td class="px-4 py-3 text-end font-mono">{{ $item->line_tax }}</td>
                                    <td class="px-4 py-3 text-end font-mono font-medium">{{ $item->line_total }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Totals --}}
            <section class="space-y-1 rounded-xl bg-zinc-50 p-4 text-sm dark:bg-zinc-800">
                <div class="flex justify-between"><span class="text-zinc-500">Subtotal</span><span class="font-mono">{{ $inv->subtotal }}</span></div>
                @if(bccomp((string) $inv->discount_total, '0', 2) > 0)
                    <div class="flex justify-between"><span class="text-zinc-500">Discount</span><span class="font-mono text-red-600">−{{ $inv->discount_total }}</span></div>
                @endif
                <div class="flex justify-between"><span class="text-zinc-500">VAT</span><span class="font-mono">{{ $inv->tax_total }}</span></div>
                <div class="flex justify-between border-t border-zinc-200 pt-1 font-semibold dark:border-zinc-700"><span>Total</span><span class="font-mono">{{ $inv->total }}</span></div>
                @if($inv->status !== \App\Enums\InvoiceStatus::Draft && $inv->status !== \App\Enums\InvoiceStatus::Void)
                    <div class="flex justify-between"><span class="text-zinc-500">Paid</span><span class="font-mono">{{ $inv->amount_paid }}</span></div>
                    <div class="flex justify-between font-semibold"><span>Balance</span><span class="font-mono">{{ $inv->balance() }}</span></div>
                @endif
            </section>

            {{-- Notes and terms --}}
            @if($inv->notes)
                <section><flux:heading size="sm">Notes</flux:heading><p class="mt-1 whitespace-pre-line text-sm">{{ $inv->notes }}</p></section>
            @endif
            @if($inv->terms)
                <section><flux:heading size="sm">Terms</flux:heading><p class="mt-1 whitespace-pre-line text-sm">{{ $inv->terms }}</p></section>
            @endif

            {{-- Void reason --}}
            @if($inv->status === \App\Enums\InvoiceStatus::Void)
                <section class="rounded-xl border border-red-200 bg-red-50/50 p-4 dark:border-red-800 dark:bg-red-950/30">
                    <flux:heading size="sm" class="text-red-700 dark:text-red-400">Void reason</flux:heading>
                    <p class="mt-1 text-sm">{{ $inv->void_reason }}</p>
                </section>
            @endif

            {{-- Audit info --}}
            <section class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-zinc-500">Created by</dt><dd class="mt-0.5">{{ $inv->creator?->name ?? '—' }} · {{ $inv->created_at?->format('d M Y H:i') ?? '—' }}</dd></div>
                @if($inv->issued_at)
                    <div><dt class="text-zinc-500">Issued by</dt><dd class="mt-0.5">{{ $inv->issuer?->name ?? '—' }} · {{ $inv->issued_at->format('d M Y H:i') }}</dd></div>
                @endif
                @if($inv->voided_at)
                    <div><dt class="text-zinc-500">Voided by</dt><dd class="mt-0.5">{{ $inv->voider?->name ?? '—' }} · {{ $inv->voided_at->format('d M Y H:i') }}</dd></div>
                @endif
            </section>

            <div class="flex justify-end"><flux:button variant="primary" wire:click="closeDetails">Done</flux:button></div>
        </div>
    @endif
</flux:modal>
