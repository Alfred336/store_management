<flux:modal name="quotation-details" class="w-full max-w-3xl" variant="flyout" position="right" scroll="body">
    @if ($selectedQuotation)
        @php
            $quot = $selectedQuotation;
            $displayStatus = $quot->displayStatus();
            $statusColors = [
                'draft'     => 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-300',
                'sent'      => 'bg-blue-500/10 text-blue-700 dark:text-blue-300',
                'expired'   => 'bg-orange-500/10 text-orange-700 dark:text-orange-300',
                'accepted'  => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
                'rejected'  => 'bg-red-500/10 text-red-600 dark:text-red-300',
                'converted' => 'bg-purple-500/10 text-purple-700 dark:text-purple-300',
            ];
        @endphp

        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ $quot->number ?? 'Draft' }}</flux:heading>
                    <flux:subheading>
                        {{ $quot->location?->name ?? '—' }} ·
                        {{ $quot->quote_date->format('d M Y') }}
                        @if($quot->revision > 1) · Rev. {{ $quot->revision }} @endif
                    </flux:subheading>
                </div>
                <span class="rounded-full px-3 py-1 text-sm {{ $statusColors[$displayStatus] ?? '' }}">{{ $quot->displayStatusLabel() }}</span>
            </div>

            {{-- Recipient --}}
            <section class="space-y-3">
                <flux:heading size="sm">Recipient</flux:heading>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-zinc-500">Name</dt><dd class="mt-0.5 font-medium">{{ $quot->customer_name ?? $quot->prospect_name ?? '—' }}</dd></div>
                    <div><dt class="text-zinc-500">Phone</dt><dd class="mt-0.5">{{ $quot->customer_phone ?? $quot->prospect_phone ?: '—' }}</dd></div>
                    <div><dt class="text-zinc-500">Address</dt><dd class="mt-0.5">{{ $quot->customer_address ?? $quot->prospect_address ?: '—' }}</dd></div>
                    @if($quot->prospect_email)<div><dt class="text-zinc-500">Email</dt><dd class="mt-0.5">{{ $quot->prospect_email }}</dd></div>@endif
                </dl>
                @if($quot->customer_id)
                    <flux:text size="sm" class="text-zinc-500">Registered customer</flux:text>
                @else
                    <flux:text size="sm" class="text-zinc-500">Prospect / enquiry</flux:text>
                @endif
            </section>

            {{-- Validity --}}
            <section class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-zinc-500">Quote date</dt><dd class="mt-0.5">{{ $quot->quote_date->format('d M Y') }}</dd></div>
                <div>
                    <dt class="text-zinc-500">Valid until</dt>
                    <dd class="mt-0.5 {{ $quot->isExpired() ? 'text-orange-600 font-medium' : '' }}">
                        {{ $quot->valid_until->format('d M Y') }}
                        @if($quot->isExpired()) <span class="ms-1 text-xs">(Expired)</span> @endif
                    </dd>
                </div>
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
                            @foreach($quot->items as $item)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ $item->description }}</div>
                                        @if($item->sku) <div class="text-xs text-zinc-500">{{ $item->sku }}</div> @endif
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
                <div class="flex justify-between"><span class="text-zinc-500">Subtotal</span><span class="font-mono">{{ $quot->subtotal }}</span></div>
                @if(bccomp((string) $quot->discount_total, '0', 2) > 0)
                    <div class="flex justify-between"><span class="text-zinc-500">Discount</span><span class="font-mono text-red-600">−{{ $quot->discount_total }}</span></div>
                @endif
                <div class="flex justify-between"><span class="text-zinc-500">VAT</span><span class="font-mono">{{ $quot->tax_total }}</span></div>
                <div class="flex justify-between border-t border-zinc-200 pt-1 font-semibold dark:border-zinc-700"><span>Total</span><span class="font-mono">{{ $quot->total }}</span></div>
            </section>

            {{-- Notes and terms --}}
            @if($quot->notes)
                <section><flux:heading size="sm">Notes</flux:heading><p class="mt-1 whitespace-pre-line text-sm">{{ $quot->notes }}</p></section>
            @endif
            @if($quot->terms)
                <section><flux:heading size="sm">Terms</flux:heading><p class="mt-1 whitespace-pre-line text-sm">{{ $quot->terms }}</p></section>
            @endif

            {{-- Reject reason --}}
            @if($quot->status === \App\Enums\QuotationStatus::Rejected && $quot->reject_reason)
                <section class="rounded-xl border border-red-200 bg-red-50/50 p-4 dark:border-red-800 dark:bg-red-950/30">
                    <flux:heading size="sm" class="text-red-700 dark:text-red-400">Reject reason</flux:heading>
                    <p class="mt-1 text-sm">{{ $quot->reject_reason }}</p>
                </section>
            @endif

            {{-- Conversion link --}}
            @if($quot->status === \App\Enums\QuotationStatus::Converted && $quot->convertedInvoice)
                <section class="rounded-xl border border-purple-200 bg-purple-50/50 p-4 dark:border-purple-800 dark:bg-purple-950/30">
                    <flux:heading size="sm" class="text-purple-700 dark:text-purple-400">Converted to invoice</flux:heading>
                    <p class="mt-1 text-sm">Invoice #{{ $quot->convertedInvoice->number ?? '(draft)' }} — ID {{ $quot->convertedInvoice->id }}</p>
                </section>
            @endif

            {{-- Revision history --}}
            @if($quot->revisedFrom || $quot->revisions->isNotEmpty())
                <section>
                    <flux:heading size="sm">Revision history</flux:heading>
                    <ul class="mt-2 space-y-1 text-sm">
                        @if($quot->revisedFrom)
                            <li class="text-zinc-500">Revised from: {{ $quot->revisedFrom->number ?? '(draft)' }} (Rev. {{ $quot->revisedFrom->revision }})</li>
                        @endif
                        @foreach($quot->revisions as $rev)
                            <li class="text-zinc-500">Revision {{ $rev->revision }}: {{ $rev->number ?? '(draft)' }} — {{ $rev->displayStatusLabel() }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Audit info --}}
            <section class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-zinc-500">Created by</dt><dd class="mt-0.5">{{ $quot->creator?->name ?? '—' }} · {{ $quot->created_at?->format('d M Y H:i') ?? '—' }}</dd></div>
                @if($quot->sent_at)
                    <div><dt class="text-zinc-500">Sent by</dt><dd class="mt-0.5">{{ $quot->sender?->name ?? '—' }} · {{ $quot->sent_at->format('d M Y H:i') }}</dd></div>
                @endif
                @if($quot->accepted_at)
                    <div><dt class="text-zinc-500">Accepted at</dt><dd class="mt-0.5">{{ $quot->accepted_at->format('d M Y H:i') }}</dd></div>
                @endif
                @if($quot->rejected_at)
                    <div><dt class="text-zinc-500">Rejected at</dt><dd class="mt-0.5">{{ $quot->rejected_at->format('d M Y H:i') }}</dd></div>
                @endif
            </section>

            {{-- Action buttons --}}
            <div class="flex flex-wrap gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                @can('print', $quot)
                    <flux:button size="sm" variant="ghost" icon="printer"
                        :href="route('quotations.pdf', ['quotation' => $quot->id, 'mode' => 'inline'])" target="_blank">
                        Print
                    </flux:button>
                    <flux:button size="sm" variant="ghost" icon="arrow-down-tray"
                        :href="route('quotations.pdf', ['quotation' => $quot->id, 'mode' => 'download'])">
                        Download PDF
                    </flux:button>
                    <flux:button size="sm" variant="ghost" icon="envelope" wire:click="closeDetails(); $nextTick(() => openEmailPanel({{ $quot->id }}))">
                        Email
                    </flux:button>
                @endcan
                <flux:button variant="primary" wire:click="closeDetails">Done</flux:button>
            </div>
        </div>
    @endif
</flux:modal>
