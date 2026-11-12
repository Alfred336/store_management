<flux:modal name="quotation-convert" class="max-w-lg">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">Convert to invoice draft</flux:heading>
            <flux:subheading>An invoice draft will be created with the quoted prices. You can issue it after reviewing.</flux:subheading>
        </div>

        @if($selectedQuotation && !$selectedQuotation->customer_id)
            {{-- Prospect quotation — customer selection required --}}
            <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950/30">
                This quotation was created for a prospect. Please select a registered customer to create the invoice.
            </div>
        @endif

        {{-- Customer selection --}}
        <div class="space-y-2">
            <flux:label required>Customer</flux:label>
            @if($convertCustomerId)
                <div class="flex items-center justify-between rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800">
                    <span class="font-medium">{{ $convertCustomerName }}</span>
                    <flux:button type="button" size="sm" variant="ghost" wire:click="$set('convertCustomerId', null); $set('convertCustomerName', '')">Change</flux:button>
                </div>
            @else
                <flux:input wire:model.live.debounce.300ms="convertCustomerSearch" placeholder="Search customer…" icon="magnifying-glass" />
                @if(!empty($convertCustomerResults))
                    <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        @foreach($convertCustomerResults as $cr)
                            <button type="button" wire:click="selectConvertCustomer({{ $cr['id'] }})"
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

        @if($convertError)
            <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $convertError }}</flux:text>
        @endif

        <div class="flex justify-end gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" wire:click="closeConvertPanel">Cancel</flux:button>
            <flux:button type="button" variant="primary" icon="document-duplicate" wire:click="convertToInvoice">Create invoice draft</flux:button>
        </div>
    </div>
</flux:modal>
