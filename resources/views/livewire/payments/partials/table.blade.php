<div class="space-y-4">
    {{-- Totals bar --}}
    @if($filteredCount > 0)
        <div class="flex items-center gap-6 rounded-xl border border-zinc-200 bg-white px-5 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900">
            <span class="text-zinc-500 dark:text-zinc-400">Active in view:</span>
            <span class="font-semibold">{{ $filteredCount }} {{ Str::plural('payment', $filteredCount) }}</span>
            <span class="text-zinc-500 dark:text-zinc-400">Total:</span>
            <span class="font-mono font-semibold">{{ $filteredTotal }}</span>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white p-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column><button type="button" wire:click="sort('number')">Number</button></flux:table.column>
                <flux:table.column>Customer</flux:table.column>
                <flux:table.column><button type="button" wire:click="sort('received_at')">Date</button></flux:table.column>
                <flux:table.column>Method</flux:table.column>
                <flux:table.column>Reference</flux:table.column>
                <flux:table.column><button type="button" wire:click="sort('amount')">Amount</button></flux:table.column>
                <flux:table.column><button type="button" wire:click="sort('status')">Status</button></flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($payments as $payment)
                    <flux:table.row :key="$payment->id">
                        <flux:table.cell>
                            <button type="button" class="font-mono text-sm hover:underline" wire:click="show({{ $payment->id }})">
                                {{ $payment->number }}
                            </button>
                        </flux:table.cell>
                        <flux:table.cell>{{ $payment->customer?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $payment->received_at?->format('d M Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ Str::title(str_replace('_', ' ', $payment->method)) }}</flux:table.cell>
                        <flux:table.cell>{{ $payment->reference ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $payment->amount }}</flux:table.cell>
                        <flux:table.cell>
                            @php
                                $statusColor = $payment->status === 'active'
                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                    : 'bg-red-500/10 text-red-600 dark:text-red-300';
                            @endphp
                            <span class="rounded-full px-2.5 py-1 text-xs {{ $statusColor }}">
                                {{ Str::title($payment->status) }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap items-center gap-1.5">
                                @can('view', $payment)
                                    <flux:button size="sm" variant="ghost" wire:click="show({{ $payment->id }})">View</flux:button>
                                @endcan
                                @if($payment->status === 'active')
                                    <a href="{{ route('payments.receipt', ['payment' => $payment->id]) }}" target="_blank"
                                       class="inline-flex items-center rounded px-2 py-1 text-xs text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800">
                                        Receipt
                                    </a>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-400">No payments found.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{ $payments->links() }}
</div>
