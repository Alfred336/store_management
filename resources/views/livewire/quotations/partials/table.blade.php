<div class="space-y-4">
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white p-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column><button type="button" wire:click="sort('number')">Number</button></flux:table.column>
                <flux:table.column>Customer / Prospect</flux:table.column>
                <flux:table.column>Location</flux:table.column>
                <flux:table.column><button type="button" wire:click="sort('quote_date')">Date</button></flux:table.column>
                <flux:table.column><button type="button" wire:click="sort('valid_until')">Valid until</button></flux:table.column>
                <flux:table.column><button type="button" wire:click="sort('status')">Status</button></flux:table.column>
                <flux:table.column><button type="button" wire:click="sort('total')">Total</button></flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($quotations as $quotation)
                    @php
                        $displayStatus = $quotation->displayStatus();
                        $statusColors = [
                            'draft'     => 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-300',
                            'sent'      => 'bg-blue-500/10 text-blue-700 dark:text-blue-300',
                            'expired'   => 'bg-orange-500/10 text-orange-700 dark:text-orange-300',
                            'accepted'  => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
                            'rejected'  => 'bg-red-500/10 text-red-600 dark:text-red-300',
                            'converted' => 'bg-purple-500/10 text-purple-700 dark:text-purple-300',
                        ];
                    @endphp
                    <flux:table.row :key="$quotation->id">
                        <flux:table.cell>
                            <button type="button" class="font-mono text-sm hover:underline" wire:click="show({{ $quotation->id }})">
                                {{ $quotation->number ?? '—' }}
                            </button>
                        </flux:table.cell>
                        <flux:table.cell>{{ $quotation->recipientName() }}</flux:table.cell>
                        <flux:table.cell>{{ $quotation->location?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $quotation->quote_date->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $quotation->valid_until->format('d M Y') }}
                            @if($quotation->isExpired())
                                <span class="ms-1 rounded-full bg-orange-500/10 px-1.5 py-0.5 text-xs text-orange-700 dark:text-orange-300">Expired</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="rounded-full px-2.5 py-1 text-xs {{ $statusColors[$displayStatus] ?? '' }}">{{ $quotation->displayStatusLabel() }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $quotation->total }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap items-center gap-1.5">
                                @can('view', $quotation)
                                    <flux:button size="sm" variant="ghost" wire:click="show({{ $quotation->id }})">View</flux:button>
                                @endcan
                                @can('update', $quotation)
                                    <flux:button size="sm" variant="ghost" wire:click="openForm({{ $quotation->id }})">Edit</flux:button>
                                @endcan
                                @can('send', $quotation)
                                    <flux:button size="sm" variant="primary" wire:click="openSendConfirmation({{ $quotation->id }})">Send</flux:button>
                                @endcan
                                @if($quotation->status === \App\Enums\QuotationStatus::Sent && !$quotation->isExpired())
                                    @can('accept', $quotation)
                                        <flux:button size="sm" variant="ghost" wire:click="openStatusPanel({{ $quotation->id }}, 'accept')">Accept</flux:button>
                                    @endcan
                                    @can('reject', $quotation)
                                        <flux:button size="sm" variant="ghost" wire:click="openStatusPanel({{ $quotation->id }}, 'reject')">Reject</flux:button>
                                    @endcan
                                @endif
                                @can('revise', $quotation)
                                    <flux:button size="sm" variant="ghost" wire:click="revise({{ $quotation->id }})">Revise</flux:button>
                                @endcan
                                @can('convert', $quotation)
                                    <flux:button size="sm" variant="ghost" wire:click="openConvertPanel({{ $quotation->id }})">Convert</flux:button>
                                @endcan
                                @can('delete', $quotation)
                                    <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $quotation->id }})">Delete</flux:button>
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-500">No quotations found.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    {{ $quotations->links() }}
</div>
