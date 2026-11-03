<div class="space-y-4">
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white p-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column><button type="button" wire:click="sort('number')">Number</button></flux:table.column>
                <flux:table.column>Customer</flux:table.column>
                <flux:table.column>Location</flux:table.column>
                <flux:table.column><button type="button" wire:click="sort('issue_date')">Date</button></flux:table.column>
                <flux:table.column>Due date</flux:table.column>
                <flux:table.column><button type="button" wire:click="sort('status')">Status</button></flux:table.column>
                <flux:table.column><button type="button" wire:click="sort('total')">Total</button></flux:table.column>
                <flux:table.column>Balance</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($invoices as $invoice)
                    <flux:table.row :key="$invoice->id">
                        <flux:table.cell>
                            <button type="button" class="font-mono text-sm hover:underline" wire:click="show({{ $invoice->id }})">
                                {{ $invoice->number ?? '—' }}
                            </button>
                        </flux:table.cell>
                        <flux:table.cell>{{ $invoice->customer?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $invoice->location?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $invoice->issue_date?->format('d M Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $invoice->due_date?->format('d M Y') ?? '—' }}
                            @if($invoice->isOverdue())
                                <span class="ms-1 rounded-full bg-red-500/10 px-1.5 py-0.5 text-xs text-red-700 dark:text-red-300">Overdue</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $statusColors = [
                                    'draft'           => 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-300',
                                    'issued'          => 'bg-blue-500/10 text-blue-700 dark:text-blue-300',
                                    'partially_paid'  => 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
                                    'paid'            => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
                                    'void'            => 'bg-red-500/10 text-red-600 dark:text-red-300',
                                ];
                            @endphp
                            <span class="rounded-full px-2.5 py-1 text-xs {{ $statusColors[$invoice->status->value] ?? '' }}">{{ $invoice->status->label() }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $invoice->total }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">
                            @if($invoice->status !== \App\Enums\InvoiceStatus::Draft && $invoice->status !== \App\Enums\InvoiceStatus::Void)
                                {{ $invoice->balance() }}
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap items-center gap-1.5">
                                @can('view', $invoice)
                                    <flux:button size="sm" variant="ghost" wire:click="show({{ $invoice->id }})">View</flux:button>
                                @endcan
                                @can('update', $invoice)
                                    <flux:button size="sm" variant="ghost" wire:click="openForm({{ $invoice->id }})">Edit</flux:button>
                                @endcan
                                @if($invoice->status === \App\Enums\InvoiceStatus::Draft)
                                    @can('issue', $invoice)
                                        <flux:button size="sm" variant="primary" wire:click="openIssueConfirmation({{ $invoice->id }})">Issue</flux:button>
                                    @endcan
                                    @can('delete', $invoice)
                                        <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $invoice->id }})">Delete</flux:button>
                                    @endcan
                                @endif
                                @if($invoice->status->isPosted() && bccomp((string) $invoice->amount_paid, '0', 2) === 0)
                                    @can('void', $invoice)
                                        <flux:button size="sm" variant="ghost" wire:click="openVoidConfirmation({{ $invoice->id }})">Void</flux:button>
                                    @endcan
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9" class="py-10 text-center text-zinc-500">No invoices found.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    {{ $invoices->links() }}
</div>
