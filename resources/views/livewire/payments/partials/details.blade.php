@if ($showDetails && $detailPayment)
    <flux:modal name="payment-details" class="w-full max-w-2xl">
        <div class="space-y-6 p-6">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="lg">{{ $detailPayment->number }}</flux:heading>
                    <flux:subheading>{{ $detailPayment->customer?->name ?? '—' }}</flux:subheading>
                </div>
                @php
                    $statusColor = $detailPayment->status === 'active'
                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                        : 'bg-red-500/10 text-red-600 dark:text-red-300';
                @endphp
                <span class="rounded-full px-3 py-1 text-sm {{ $statusColor }}">
                    {{ Str::title($detailPayment->status) }}
                </span>
            </div>

            {{-- Payment details --}}
            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <dt class="text-zinc-500">Amount</dt>
                    <dd class="font-mono font-semibold">{{ $detailPayment->amount }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500">Method</dt>
                    <dd>{{ Str::title(str_replace('_', ' ', $detailPayment->method)) }}</dd>
                </div>
                @if($detailPayment->provider)
                    <div>
                        <dt class="text-zinc-500">Provider</dt>
                        <dd>{{ $detailPayment->provider }}</dd>
                    </div>
                @endif
                @if($detailPayment->reference)
                    <div>
                        <dt class="text-zinc-500">Reference</dt>
                        <dd class="font-mono">{{ $detailPayment->reference }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-zinc-500">Received on</dt>
                    <dd>{{ $detailPayment->received_at?->format('d M Y') }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500">Received by</dt>
                    <dd>{{ $detailPayment->receiver?->name ?? '—' }}</dd>
                </div>
                @if($detailCredit && bccomp($detailCredit, '0.00', 2) > 0)
                    <div class="col-span-2">
                        <dt class="text-zinc-500">Unallocated credit</dt>
                        <dd class="font-mono text-amber-600 dark:text-amber-400">{{ $detailCredit }}</dd>
                    </div>
                @endif
                @if($detailPayment->notes)
                    <div class="col-span-2">
                        <dt class="text-zinc-500">Notes</dt>
                        <dd>{{ $detailPayment->notes }}</dd>
                    </div>
                @endif
            </dl>

            {{-- Allocations --}}
            @if($detailPayment->allocations->isNotEmpty())
                <div class="space-y-2">
                    <flux:heading size="sm">Invoice allocations</flux:heading>
                    <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-50 dark:bg-zinc-800">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">Invoice</th>
                                    <th class="px-3 py-2 text-right font-medium">Allocated</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($detailPayment->allocations as $alloc)
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-xs">{{ $alloc->invoice?->number ?? "INV #{$alloc->invoice_id}" }}</td>
                                        <td class="px-3 py-2 text-right font-mono">{{ $alloc->amount }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Reversal info --}}
            @if($detailPayment->status === 'reversed')
                <div class="rounded-lg bg-red-50 p-4 text-sm dark:bg-red-900/20">
                    <p class="font-medium text-red-700 dark:text-red-400">Reversed on {{ $detailPayment->reversed_at?->format('d M Y H:i') }} by {{ $detailPayment->reverser?->name ?? '—' }}</p>
                    @if($detailPayment->reverse_reason)
                        <p class="mt-1 text-red-600 dark:text-red-300">Reason: {{ $detailPayment->reverse_reason }}</p>
                    @endif
                </div>
            @endif

            {{-- Reverse confirmation inline --}}
            @if ($showReverseConfirmation)
                <div class="space-y-3 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                    <p class="text-sm font-medium text-red-700 dark:text-red-400">Confirm reversal</p>
                    <flux:input wire:model="reverseReason" label="Reason (required)" placeholder="Reason for reversal…" />
                    @if ($reverseError)
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $reverseError }}</p>
                    @endif
                    <div class="flex gap-2">
                        <flux:button size="sm" variant="danger" wire:click="reverse">Confirm reverse</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="$set('showReverseConfirmation', false)">Cancel</flux:button>
                    </div>
                </div>
            @endif

            <div class="flex items-center justify-between pt-2">
                <div class="flex gap-2">
                    @if($detailPayment->status === 'active')
                        <a href="{{ route('payments.receipt', ['payment' => $detailPayment->id]) }}" target="_blank">
                            <flux:button size="sm" variant="ghost" icon="printer">Print receipt</flux:button>
                        </a>
                        @can('reverse', $detailPayment)
                            @if (!$showReverseConfirmation)
                                <flux:button size="sm" variant="danger" wire:click="openReverseConfirmation">Reverse</flux:button>
                            @endif
                        @endcan
                    @endif
                </div>
                <flux:button variant="ghost" wire:click="closeDetails">Close</flux:button>
            </div>
        </div>
    </flux:modal>
@endif
