<div class="flex h-screen flex-col overflow-hidden">
    {{-- Top bar --}}
    <div class="flex items-center justify-between border-b border-zinc-200 bg-white px-4 py-2 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="flex items-center gap-3">
            <flux:heading size="lg">POS</flux:heading>
            @if ($session)
                <span class="rounded-full bg-green-500/10 px-2.5 py-1 text-xs font-medium text-green-700 dark:text-green-300">
                    Session #{{ $session->id }} · {{ $session->location->name }}
                </span>
            @else
                <span class="rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-700 dark:text-amber-300">No session</span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @if ($session)
                <flux:button size="sm" variant="ghost" icon="pause" wire:click="holdSale">Hold</flux:button>
                <flux:button size="sm" variant="ghost" icon="list-bullet" wire:click="$set('activePanel', 'held')">Held ({{ $heldSales->count() }})</flux:button>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="$set('activePanel', 'session')">Close session</flux:button>
            @else
                <flux:button size="sm" variant="primary" wire:click="$set('activePanel', 'session')">Open session</flux:button>
            @endif
            <flux:button size="sm" variant="ghost" :href="route('dashboard')" wire:navigate>Back</flux:button>
        </div>
    </div>

    {{-- Main area --}}
    @if ($activePanel === 'sale')
        <div class="flex flex-1 overflow-hidden">
            {{-- Left: product search --}}
            <div class="flex w-1/2 flex-col gap-3 overflow-y-auto border-r border-zinc-200 p-4 dark:border-zinc-700">
                <flux:input
                    wire:model.live.debounce.200ms="productSearch"
                    placeholder="Search by name, SKU or barcode…"
                    icon="magnifying-glass"
                    autofocus
                />

                @if ($productResults->isNotEmpty())
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($productResults as $product)
                            <button
                                type="button"
                                wire:click="addToCart({{ $product->id }})"
                                class="rounded-xl border border-zinc-200 bg-white p-3 text-left hover:border-blue-400 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-blue-500"
                            >
                                <p class="font-medium text-sm">{{ $product->name }}</p>
                                <p class="text-xs text-zinc-500">{{ $product->sku }}</p>
                                <p class="mt-1 text-sm font-semibold">{{ $product->selling_price }} {{ $currency }}</p>
                            </button>
                        @endforeach
                    </div>
                @elseif ($productSearch !== '')
                    <p class="text-sm text-zinc-500">No products found.</p>
                @endif
            </div>

            {{-- Right: cart --}}
            <div class="flex w-1/2 flex-col overflow-hidden">
                {{-- Customer bar --}}
                <div class="border-b border-zinc-200 p-3 dark:border-zinc-700">
                    @if ($selectedCustomer)
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">{{ $selectedCustomer->name }}</span>
                            <flux:button size="xs" variant="ghost" wire:click="setCustomer(null)">Remove</flux:button>
                        </div>
                    @else
                        <div class="flex items-center gap-2">
                            <flux:input wire:model.live.debounce.300ms="customerSearch" placeholder="Customer (default: walk-in)" size="sm" class="flex-1" />
                        </div>
                        @if ($customerResults->isNotEmpty())
                            <div class="mt-1 rounded-lg border border-zinc-200 bg-white shadow dark:border-zinc-700 dark:bg-zinc-800">
                                @foreach ($customerResults as $cust)
                                    <button type="button" wire:click="setCustomer({{ $cust->id }})" class="block w-full px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700">
                                        {{ $cust->name }} <span class="text-zinc-500">{{ $cust->code }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>

                {{-- Cart items --}}
                <div class="flex-1 overflow-y-auto p-3 space-y-2">
                    @forelse ($cart as $index => $item)
                        <div class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-800" wire:key="cart-{{ $index }}">
                            <div class="flex-1 min-w-0">
                                <p class="truncate text-sm font-medium">{{ $item['name'] }}</p>
                                <p class="text-xs text-zinc-500">{{ $item['sku'] }}</p>
                            </div>
                            <flux:input
                                wire:change="updateCartItem({{ $index }}, 'quantity', $event.target.value)"
                                value="{{ $item['quantity'] }}"
                                type="number"
                                min="0.001"
                                step="0.001"
                                class="w-20"
                                size="sm"
                            />
                            <flux:input
                                wire:change="updateCartItem({{ $index }}, 'unit_price', $event.target.value)"
                                value="{{ $item['unit_price'] }}"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-24"
                                size="sm"
                            />
                            @can('pos.discount')
                                <flux:input
                                    wire:change="updateCartItem({{ $index }}, 'discount_amount', $event.target.value)"
                                    value="{{ $item['discount_amount'] }}"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-20"
                                    size="sm"
                                    placeholder="Disc."
                                />
                            @endcan
                            <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removeFromCart({{ $index }})" />
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-zinc-500">Cart is empty. Search for a product to add.</p>
                    @endforelse
                </div>

                {{-- Totals and action --}}
                <div class="border-t border-zinc-200 p-4 space-y-1 dark:border-zinc-700">
                    @php $totals = $this->totals(); @endphp
                    <div class="flex justify-between text-sm text-zinc-500"><span>Subtotal</span><span>{{ $totals['subtotal'] }} {{ $currency }}</span></div>
                    @if (bccomp($totals['discount_total'], '0', 2) > 0)
                        <div class="flex justify-between text-sm text-zinc-500"><span>Discount</span><span>-{{ $totals['discount_total'] }} {{ $currency }}</span></div>
                    @endif
                    <div class="flex justify-between text-sm text-zinc-500"><span>Tax</span><span>{{ $totals['tax_total'] }} {{ $currency }}</span></div>
                    <div class="flex justify-between text-base font-bold"><span>Total</span><span>{{ $totals['total'] }} {{ $currency }}</span></div>
                    <div class="pt-2">
                        <flux:button variant="primary" class="w-full" wire:click="openPaymentPanel" :disabled="empty($cart) || !$session">
                            Charge {{ $totals['total'] }} {{ $currency }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>

    @elseif ($activePanel === 'payment')
        <div class="flex flex-1 items-start justify-center overflow-y-auto p-6">
            <div class="w-full max-w-lg space-y-5">
                <div class="flex items-center gap-3">
                    <flux:button variant="ghost" icon="arrow-left" wire:click="$set('activePanel', 'sale')" />
                    <flux:heading size="lg">Payment</flux:heading>
                </div>

                @php $totals = $this->totals(); @endphp
                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="flex justify-between font-bold text-lg"><span>Total</span><span>{{ $totals['total'] }} {{ $currency }}</span></div>
                </div>

                {{-- Tenders --}}
                <div class="space-y-3">
                    @foreach ($tenders as $i => $tender)
                        <div class="flex items-center gap-2" wire:key="tender-{{ $i }}">
                            <flux:select wire:model="tenders.{{ $i }}.method" class="w-36">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="mobile_money">Mobile money</option>
                                <option value="bank_transfer">Bank transfer</option>
                                <option value="cheque">Cheque</option>
                            </flux:select>
                            <flux:input wire:model="tenders.{{ $i }}.amount" type="number" min="0" step="0.01" placeholder="Amount" class="flex-1" />
                            <flux:input wire:model="tenders.{{ $i }}.reference" placeholder="Ref." class="w-28" />
                            @if (count($tenders) > 1)
                                <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removeTender({{ $i }})" />
                            @endif
                        </div>
                    @endforeach
                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="addTender">Add tender</flux:button>
                </div>

                {{-- Change --}}
                @php $change = $this->changeGiven(); @endphp
                @if (bccomp($change, '0', 2) > 0)
                    <div class="rounded-xl bg-green-500/10 p-3 text-center">
                        <p class="text-sm text-green-700 dark:text-green-300">Change: <span class="font-bold">{{ $change }} {{ $currency }}</span></p>
                    </div>
                @endif

                @error('tenders') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @error('customer_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @error('items') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                <flux:button variant="primary" class="w-full" wire:click="completeSale">Complete Sale</flux:button>

                @can('pos.credit_sale')
                    <flux:button variant="ghost" class="w-full" wire:click="$set('tenders', [])" wire:then="completeSale">
                        Issue on Credit (no payment)
                    </flux:button>
                @endcan
            </div>
        </div>

    @elseif ($activePanel === 'receipt')
        <div class="flex flex-1 items-center justify-center p-6">
            <div class="w-full max-w-sm space-y-4 text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                    <flux:icon.check-circle class="h-9 w-9 text-green-600 dark:text-green-400" />
                </div>
                <flux:heading size="xl">Sale Complete</flux:heading>
                <p class="text-zinc-500">Receipt: <span class="font-mono font-semibold">{{ $lastReceiptNumber }}</span></p>
                <p class="text-zinc-500">Total: <span class="font-semibold">{{ $lastReceiptTotal }} {{ $currency }}</span></p>
                @if (bccomp($lastChangeGiven, '0', 2) > 0)
                    <p class="text-zinc-500">Change: <span class="font-semibold text-green-600">{{ $lastChangeGiven }} {{ $currency }}</span></p>
                @endif
                @if ($lastInvoiceId)
                    <flux:button :href="route('invoices.pdf', $lastInvoiceId)" target="_blank" icon="printer" variant="ghost" size="sm">Print receipt</flux:button>
                @endif
                <flux:button variant="primary" class="w-full" wire:click="newSale">New Sale</flux:button>
            </div>
        </div>

    @elseif ($activePanel === 'session')
        <div class="flex flex-1 items-start justify-center overflow-y-auto p-6">
            <div class="w-full max-w-md space-y-5">
                @if ($session)
                    <flux:heading size="lg">Close Session</flux:heading>
                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800 space-y-1 text-sm">
                        <div class="flex justify-between"><span class="text-zinc-500">Session</span><span>#{{ $session->id }}</span></div>
                        <div class="flex justify-between"><span class="text-zinc-500">Location</span><span>{{ $session->location->name }}</span></div>
                        <div class="flex justify-between"><span class="text-zinc-500">Opened</span><span>{{ $session->opened_at->format('d M H:i') }}</span></div>
                        <div class="flex justify-between"><span class="text-zinc-500">Opening cash</span><span>{{ $session->opening_cash }} {{ $currency }}</span></div>
                    </div>
                    <flux:input wire:model="closingCash" label="Cash in drawer (counted)" type="number" min="0" step="0.01" required />
                    @error('closing_cash_counted') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <flux:textarea wire:model="sessionNotes" label="Notes (optional)" rows="2" />
                    <div class="flex gap-3">
                        <flux:button variant="ghost" wire:click="$set('activePanel', 'sale')">Cancel</flux:button>
                        <flux:button variant="danger" wire:click="closeSession">Close Session</flux:button>
                    </div>
                @else
                    <flux:heading size="lg">Open Session</flux:heading>
                    <flux:select wire:model="openLocationId" label="Location" required>
                        <option value="">Select a location</option>
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </flux:select>
                    @error('location_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <flux:input wire:model="openingCash" label="Opening cash" type="number" min="0" step="0.01" />
                    @error('opening_cash') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @error('session') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <flux:button variant="primary" wire:click="openSession">Open Session</flux:button>
                @endif
            </div>
        </div>

    @elseif ($activePanel === 'held')
        <div class="flex flex-1 flex-col overflow-hidden p-4">
            <div class="mb-4 flex items-center gap-3">
                <flux:button variant="ghost" icon="arrow-left" wire:click="$set('activePanel', 'sale')" />
                <flux:heading size="lg">Held Sales</flux:heading>
            </div>
            @forelse ($heldSales as $held)
                <div class="mb-2 flex items-center justify-between rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-800">
                    <div>
                        <p class="font-medium text-sm">{{ $held->label ?: 'Unnamed hold' }}</p>
                        <p class="text-xs text-zinc-500">{{ $held->created_at?->format('H:i') }} · {{ count($held->cart) }} item(s)</p>
                    </div>
                    <div class="flex gap-2">
                        <flux:button size="sm" variant="ghost" wire:click="resumeSale($held->id)">Resume</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="voidHeldSale({{ $held->id }})">Discard</flux:button>
                    </div>
                </div>
            @empty
                <p class="py-10 text-center text-sm text-zinc-500">No held sales.</p>
            @endforelse
        </div>
    @endif
</div>
