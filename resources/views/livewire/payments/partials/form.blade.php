@if ($showForm)
    <flux:modal name="payment-form" class="w-full max-w-3xl">
        <div class="space-y-6 p-6">
            <flux:heading size="lg">Receive payment</flux:heading>

            @if ($formError)
                <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                    {{ $formError }}
                </div>
            @endif

            {{-- Customer picker --}}
            <div class="space-y-2">
                @if ($formCustomerId)
                    <div class="flex items-center justify-between rounded-lg bg-zinc-50 px-4 py-2 dark:bg-zinc-800">
                        <span class="font-medium">{{ $formCustomerName }}</span>
                        <flux:button size="xs" variant="ghost" wire:click="$set('formCustomerId', null)">Change</flux:button>
                    </div>
                @else
                    <flux:input wire:model.live.debounce.300ms="formCustomerSearch" label="Customer" placeholder="Search by name or code…" icon="magnifying-glass" />
                    @if ($formCustomerSearch !== '' && count($customerResults) > 0)
                        <div class="rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            @foreach ($customerResults as $c)
                                <button type="button"
                                    wire:click="selectCustomer({{ $c['id'] }})"
                                    class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                    <span class="font-medium">{{ $c['name'] }}</span>
                                    <span class="text-zinc-400">{{ $c['code'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @elseif ($formCustomerSearch !== '')
                        <p class="text-sm text-zinc-400">No customers found.</p>
                    @endif
                @endif
            </div>

            {{-- Payment details --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model.live="formAmount" label="Amount" type="number" step="0.01" min="0.01" placeholder="0.00" />
                <flux:select wire:model="formMethod" label="Method">
                    <option value="cash">Cash</option>
                    <option value="mobile_money">Mobile money</option>
                    <option value="bank_transfer">Bank transfer</option>
                    <option value="cheque">Cheque</option>
                    <option value="card">Card</option>
                </flux:select>
                <flux:input wire:model="formProvider" label="Provider" placeholder="e.g. M-Pesa, Airtel Money, NMB" />
                <flux:input wire:model="formReference" label="Reference" placeholder="Transaction or cheque number" />
                <flux:input wire:model="formReceivedAt" label="Date received" type="date" />
                <flux:input wire:model="formNotes" label="Notes" placeholder="Optional notes" />
            </div>

            {{-- Invoice allocations --}}
            @if ($formCustomerId && count($formAllocations) > 0)
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">Allocate to invoices</flux:heading>
                        <flux:button size="xs" variant="ghost" wire:click="autoAllocate">Auto-allocate</flux:button>
                    </div>
                    <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-50 dark:bg-zinc-800">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">Invoice</th>
                                    <th class="px-3 py-2 text-right font-medium">Balance</th>
                                    <th class="px-3 py-2 text-right font-medium">Allocate</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($formAllocations as $i => $alloc)
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-xs">{{ $alloc['number'] }}</td>
                                        <td class="px-3 py-2 text-right font-mono">{{ $alloc['balance'] }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <input type="number"
                                                wire:model.blur="formAllocations.{{ $i }}.allocated"
                                                step="0.01" min="0" max="{{ $alloc['balance'] }}"
                                                class="w-28 rounded border border-zinc-300 px-2 py-1 text-right text-sm dark:border-zinc-600 dark:bg-zinc-900"
                                                value="{{ $alloc['allocated'] }}" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @php
                        $totalAllocated = collect($formAllocations)->sum(fn($a) => (float)($a['allocated'] ?? 0));
                        $unallocated = max(0, (float)($formAmount ?: 0) - $totalAllocated);
                    @endphp
                    @if ($unallocated > 0)
                        <p class="text-sm text-amber-600 dark:text-amber-400">
                            {{ number_format($unallocated, 2) }} will remain as unallocated customer credit.
                        </p>
                    @endif
                </div>
            @elseif ($formCustomerId)
                <p class="text-sm text-zinc-400">No open invoices for this customer. The full amount will be held as credit.</p>
            @endif

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="closeForm">Cancel</flux:button>
                <flux:button variant="primary" wire:click="store">Save payment</flux:button>
            </div>
        </div>
    </flux:modal>
@endif
