<flux:modal name="quotation-form" class="w-full max-w-4xl" variant="flyout" position="right" scroll="body">
    <form wire:submit="{{ $quotationId ? 'update' : 'store' }}" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $quotationId ? 'Edit draft quotation' : 'New quotation' }}</flux:heading>
            <flux:subheading>Prepare a sales quotation for a registered customer or a new enquiry.</flux:subheading>
        </div>

        {{-- Recipient mode --}}
        <div class="flex gap-4">
            <label class="flex cursor-pointer items-center gap-2 text-sm">
                <input type="radio" wire:model.live="recipientMode" value="customer" class="rounded-full"> Registered customer
            </label>
            <label class="flex cursor-pointer items-center gap-2 text-sm">
                <input type="radio" wire:model.live="recipientMode" value="prospect" class="rounded-full"> New enquiry (prospect)
            </label>
        </div>

        @if($recipientMode === 'customer')
            {{-- Customer picker --}}
            <div class="space-y-2">
                <flux:label required>Customer</flux:label>
                @if($selectedCustomerId)
                    <div class="flex items-center justify-between rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800">
                        <span class="font-medium">{{ $selectedCustomerName }}</span>
                        <flux:button type="button" size="sm" variant="ghost" wire:click="$set('selectedCustomerId', null); $set('selectedCustomerName', '')">Change</flux:button>
                    </div>
                @else
                    <flux:input wire:model.live.debounce.300ms="customerSearch" placeholder="Search by name or code…" icon="magnifying-glass" />
                    @if(!empty($customerResults))
                        <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            @foreach($customerResults as $cr)
                                <button type="button" wire:click="selectCustomer({{ $cr['id'] }})"
                                    class="w-full px-4 py-2.5 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800 {{ !$loop->first ? 'border-t border-zinc-100 dark:border-zinc-700' : '' }}">
                                    <span class="font-medium">{{ $cr['name'] }}</span>
                                    <span class="ms-2 text-zinc-500">{{ $cr['code'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endif
                @error('customer_id') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
            </div>
        @else
            {{-- Prospect fields --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:input wire:model="prospectName" label="Prospect name" required placeholder="Company or person name" />
                    @error('prospect_name') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
                </div>
                <div>
                    <flux:input wire:model="prospectPhone" label="Phone" type="tel" />
                    @error('prospect_phone') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
                </div>
                <div>
                    <flux:input wire:model="prospectEmail" label="Email" type="email" />
                    @error('prospect_email') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
                </div>
                <div>
                    <flux:input wire:model="prospectAddress" label="Address" />
                    @error('prospect_address') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
                </div>
            </div>
        @endif

        {{-- Location and dates --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <flux:label required>Location</flux:label>
                <flux:select wire:model.live="locationId">
                    <option value="">Select location…</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                    @endforeach
                </flux:select>
                @error('location_id') <flux:text class="text-red-600" size="sm">{{ $message }}</flux:text> @enderror
            </div>
            <div>
                <flux:input wire:model.live="quoteDate" label="Quote date" type="date" required />
                @error('quote_date') <flux:text class="text-red-600" size="sm">{{ $message }}</flux:text> @enderror
            </div>
            <div>
                <flux:input wire:model="validUntil" label="Valid until" type="date" required />
                @error('valid_until') <flux:text class="text-red-600" size="sm">{{ $message }}</flux:text> @enderror
            </div>
        </div>

        {{-- Line items --}}
        <section class="space-y-3 border-t border-zinc-200 pt-5 dark:border-zinc-700">
            <flux:heading>Items</flux:heading>

            @foreach($items as $index => $item)
                <div class="grid items-end gap-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700 sm:grid-cols-[2fr_1fr_1fr_1fr_1fr_auto]" wire:key="line-{{ $index }}">
                    <div>
                        <flux:text size="sm" class="font-medium">{{ $item['product_name'] }}</flux:text>
                        @if($item['product_sku']) <flux:text size="sm" class="text-zinc-500">{{ $item['product_sku'] }}</flux:text> @endif
                        @if($locationId && isset($stockHints[(int)($item['product_id'] ?? 0)]))
                            <flux:text size="sm" class="text-zinc-400">In stock: {{ $stockHints[(int)($item['product_id'] ?? 0)] }}</flux:text>
                        @endif
                    </div>
                    <div>
                        <flux:label size="sm">Unit</flux:label>
                        <flux:select wire:model="items.{{ $index }}.unit_id" size="sm">
                            @foreach($item['available_units'] ?? [] as $unit)
                                <option value="{{ $unit['id'] }}">{{ $unit['abbreviation'] ?: $unit['name'] }}</option>
                            @endforeach
                        </flux:select>
                        @error("items.{$index}.unit_id") <flux:text class="text-red-600" size="sm">{{ $message }}</flux:text> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="items.{{ $index }}.quantity" label="Qty" type="number" min="0.001" step="0.001" size="sm" />
                        @error("items.{$index}.quantity") <flux:text class="text-red-600" size="sm">{{ $message }}</flux:text> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="items.{{ $index }}.unit_price" label="Unit price" type="number" min="0" step="0.01" size="sm" />
                        @error("items.{$index}.unit_price") <flux:text class="text-red-600" size="sm">{{ $message }}</flux:text> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="items.{{ $index }}.discount_amount" label="Discount" type="number" min="0" step="0.01" size="sm" />
                        @error("items.{$index}.discount_amount") <flux:text class="text-red-600" size="sm">{{ $message }}</flux:text> @enderror
                    </div>
                    <flux:button type="button" size="sm" variant="ghost" wire:click="removeItem({{ $index }})">✕</flux:button>
                </div>
            @endforeach

            @error('items') <flux:text class="text-red-600" size="sm">{{ $message }}</flux:text> @enderror

            {{-- Product search to add lines --}}
            <div class="space-y-2">
                <flux:input wire:model.live.debounce.300ms="productSearch" placeholder="Add product: search by name, SKU or barcode…" icon="plus-circle" />
                @if(!empty($productResults))
                    <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        @foreach($productResults as $pr)
                            <button type="button" wire:click="addLineItem({{ $pr['id'] }})"
                                class="w-full px-4 py-2.5 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800 {{ !$loop->first ? 'border-t border-zinc-100 dark:border-zinc-700' : '' }}">
                                <span class="font-medium">{{ $pr['name'] }}</span>
                                @if($pr['sku']) <span class="ms-2 text-zinc-500">{{ $pr['sku'] }}</span> @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- Live totals preview --}}
        @if($liveTotals)
            <div class="rounded-xl bg-zinc-50 p-4 text-sm dark:bg-zinc-800">
                <dl class="space-y-1">
                    <div class="flex justify-between"><dt class="text-zinc-500">Subtotal</dt><dd class="font-mono">{{ $liveTotals['subtotal'] }}</dd></div>
                    @if(bccomp($liveTotals['discount_total'], '0', 2) > 0)
                        <div class="flex justify-between"><dt class="text-zinc-500">Discount</dt><dd class="font-mono text-red-600">−{{ $liveTotals['discount_total'] }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-zinc-500">VAT</dt><dd class="font-mono">{{ $liveTotals['tax_total'] }}</dd></div>
                    <div class="flex justify-between border-t border-zinc-200 pt-1 dark:border-zinc-700"><dt class="font-semibold">Total</dt><dd class="font-mono font-semibold">{{ $liveTotals['total'] }}</dd></div>
                </dl>
            </div>
        @endif

        {{-- Notes and terms --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:textarea wire:model="notes" label="Notes" rows="3" placeholder="Internal note or memo…" />
            <flux:textarea wire:model="terms" label="Terms" rows="3" placeholder="Payment terms, conditions…" />
        </div>

        <div class="flex justify-end gap-3 border-t border-zinc-200 pt-5 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" wire:click="closeForm">Cancel</flux:button>
            <flux:button type="submit" variant="primary" icon="document-check">Save draft</flux:button>
        </div>
    </form>
</flux:modal>
