<flux:modal name="transfer-form" class="w-full max-w-xl" variant="flyout" position="right">
    <form wire:submit="transfer" class="space-y-6">
        <div>
            <flux:heading size="lg">Transfer stock</flux:heading>
            <flux:subheading>Move stock from one location to another.</flux:subheading>
        </div>

        @if ($transferProduct)
            <div class="rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-800">
                <div class="font-medium">{{ $transferProduct->name }}</div>
                <div class="text-sm text-zinc-500">{{ $transferProduct->sku }} · {{ $transferProduct->baseUnit->abbreviation }}</div>
            </div>
        @endif

        {{-- From / To location --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <flux:label required>From location</flux:label>
                <flux:select wire:model="transferFromLocationId">
                    <option value="">Select…</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                    @endforeach
                </flux:select>
                @error('transferFromLocationId') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
            </div>
            <div>
                <flux:label required>To location</flux:label>
                <flux:select wire:model="transferToLocationId">
                    <option value="">Select…</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                    @endforeach
                </flux:select>
                @error('transferToLocationId') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
            </div>
        </div>

        {{-- Quantity --}}
        <div>
            <flux:input wire:model="transferQuantity" label="Quantity" type="number" min="0.001" step="0.001" required />
            @error('transferQuantity') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
        </div>

        <flux:input wire:model="transferReference" label="Reference" placeholder="Optional document reference" />

        <div class="flex justify-end gap-3 border-t border-zinc-200 pt-5 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" x-on:click="$flux.modal('transfer-form').close()">Cancel</flux:button>
            <flux:button type="submit" variant="primary" icon="arrows-right-left">Save transfer</flux:button>
        </div>
    </form>
</flux:modal>
