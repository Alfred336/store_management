<flux:modal name="adjust-form" class="w-full max-w-xl" variant="flyout" position="right">
    <form wire:submit="adjust" class="space-y-6">
        <div>
            <flux:heading size="lg">Adjust stock</flux:heading>
            <flux:subheading>Positive to add, negative to remove. A reason is required.</flux:subheading>
        </div>

        @if ($adjustProduct)
            <div class="rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-800">
                <div class="font-medium">{{ $adjustProduct->name }}</div>
                <div class="text-sm text-zinc-500">{{ $adjustProduct->sku }} · {{ $adjustProduct->baseUnit->abbreviation }}</div>
            </div>
        @endif

        {{-- Location --}}
        <div>
            <flux:label required>Location</flux:label>
            <flux:select wire:model="adjustLocationId">
                <option value="">Select location…</option>
                @foreach ($locations as $loc)
                    <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                @endforeach
            </flux:select>
            @error('adjustLocationId') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
        </div>

        {{-- Quantity --}}
        <div>
            <flux:input wire:model="adjustQuantity" label="Quantity adjustment" type="number" step="0.001"
                placeholder="e.g. −5.000 or +3.000" required />
            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Use a negative number to reduce stock.</flux:text>
            @error('adjustQuantity') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
        </div>

        {{-- Reason --}}
        <div>
            <flux:textarea wire:model="adjustReason" label="Reason" rows="2"
                placeholder="Damage, stocktake correction, theft, etc." required />
            @error('adjustReason') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
        </div>

        <flux:input wire:model="adjustReference" label="Reference" placeholder="Optional document reference" />

        <div class="flex justify-end gap-3 border-t border-zinc-200 pt-5 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" x-on:click="$flux.modal('adjust-form').close()">Cancel</flux:button>
            <flux:button type="submit" variant="primary">Save adjustment</flux:button>
        </div>
    </form>
</flux:modal>
