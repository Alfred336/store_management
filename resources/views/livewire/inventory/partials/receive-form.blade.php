<flux:modal name="receive-form" class="w-full max-w-2xl" variant="flyout" position="right" scroll="body">
    <form wire:submit="receive" class="space-y-6">
        <div>
            <flux:heading size="lg">Receive stock</flux:heading>
            <flux:subheading>Record incoming stock against a product and location.</flux:subheading>
        </div>

        {{-- Product --}}
        <div>
            <flux:label required>Product</flux:label>
            <flux:select wire:model.live="receiveProductId">
                <option value="">Select product…</option>
                @foreach (\App\Models\Product::query()->whereNull('deleted_at')->orderBy('name')->get() as $p)
                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                @endforeach
            </flux:select>
            @error('receiveProductId') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
        </div>

        {{-- Location --}}
        <div>
            <flux:label required>Location</flux:label>
            <flux:select wire:model="receiveLocationId">
                <option value="">Select location…</option>
                @foreach ($locations as $loc)
                    <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                @endforeach
            </flux:select>
            @error('receiveLocationId') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
        </div>

        {{-- Quantity and Unit --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <flux:input wire:model="receiveQuantity" label="Quantity" type="number" min="0.001" step="0.001" required />
                @error('receiveQuantity') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
            </div>
            @if ($receiveProduct && $receiveProduct->productUnits->isNotEmpty())
                <div>
                    <flux:label>Unit</flux:label>
                    <flux:select wire:model="receiveUnitId">
                        <option value="">{{ $receiveProduct->baseUnit->abbreviation }} (base)</option>
                        @foreach ($receiveProduct->productUnits as $pu)
                            <option value="{{ $pu->unit_id }}">{{ $pu->unit->abbreviation }} (×{{ $pu->factor }})</option>
                        @endforeach
                    </flux:select>
                </div>
            @endif
        </div>

        {{-- Batch (batch-tracked products only) --}}
        @if ($receiveProduct?->track_batches)
            <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="sm">Batch</flux:heading>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" wire:model.live="receiveBatchChoice" value="existing" class="rounded-full" />
                        Existing batch
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" wire:model.live="receiveBatchChoice" value="new" class="rounded-full" />
                        New batch
                    </label>
                </div>

                @if ($receiveBatchChoice === 'existing')
                    <div>
                        <flux:label required>Select batch</flux:label>
                        <flux:select wire:model="receiveBatchId">
                            <option value="">Select batch…</option>
                            @foreach ($receiveBatches as $batch)
                                <option value="{{ $batch->id }}">
                                    {{ $batch->batch_number }}{{ $batch->expiry_date ? ' — exp. '.$batch->expiry_date->format('d M Y') : '' }}
                                </option>
                            @endforeach
                        </flux:select>
                        @error('receiveBatchId') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
                    </div>
                @else
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <flux:input wire:model="receiveBatchNumber" label="Batch number" required />
                            @error('receiveBatchNumber') <flux:text class="text-red-600 dark:text-red-400" size="sm">{{ $message }}</flux:text> @enderror
                        </div>
                        <flux:input wire:model="receiveBatchExpiry" label="Expiry date" type="date" />
                    </div>
                @endif
            </div>
        @endif

        {{-- Reference / notes --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="receiveReference" label="Reference" placeholder="PO or delivery number" />
            <flux:input wire:model="receivePersonName" label="Received from" placeholder="Supplier or person name" />
        </div>
        <flux:textarea wire:model="receiveNotes" label="Notes / reason" rows="2" placeholder="e.g. Supplier delivery note…" />

        <div class="flex justify-end gap-3 border-t border-zinc-200 pt-5 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" x-on:click="$flux.modal('receive-form').close()">Cancel</flux:button>
            <flux:button type="submit" variant="primary" icon="arrow-down-tray">Save receipt</flux:button>
        </div>
    </form>
</flux:modal>
