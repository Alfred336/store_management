<flux:modal name="product-form" class="w-full max-w-3xl" variant="flyout" position="right" scroll="body">
    <form wire:submit="{{ $productId ? 'update' : 'store' }}" class="space-y-7">
        <div><flux:heading size="lg">{{ $productId ? 'Edit product' : 'Create product' }}</flux:heading><flux:subheading>Product details and packaging conversions are saved together.</flux:subheading></div>
        @if ($productId && $hasMovements)<div class="rounded-xl border border-amber-300/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">Base unit and batch tracking are locked because this product has stock movement history.</div>@endif
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="sku" label="SKU" required />
            <flux:input wire:model="barcode" label="Barcode" />
            <flux:input wire:model="name" label="Product name" required />
            <flux:select wire:model="taxType" label="Tax type" required><option value="standard">Standard VAT</option><option value="exempt">Exempt</option><option value="zero_rated">Zero-rated</option></flux:select>
            <flux:select wire:model="categoryId" label="Category"><option value="">No category</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</flux:select>
            @if ($productId && $hasMovements)
                <flux:select label="Base unit" disabled><option>{{ $editingProduct->baseUnit->name }}</option></flux:select>
            @else
                <flux:select wire:model="baseUnitId" label="Base unit" required><option value="">Select a unit</option>@foreach ($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->abbreviation }})</option>@endforeach</flux:select>
            @endif
            <flux:input wire:model="reorderLevel" label="Reorder level (base units)" type="number" min="0" step="0.001" required />
            <flux:input wire:model="sellingPrice" label="Selling price" type="number" min="0" step="0.01" required />
            <flux:textarea wire:model="description" label="Description" rows="3" />
            @if ($productId && $hasMovements)
                <flux:checkbox wire:model="trackBatches" label="Track batches (locked)" disabled />
            @else
                <flux:checkbox wire:model="trackBatches" label="Track batches" />
            @endif
            <flux:checkbox wire:model="isActive" label="Product is active" />
        </div>

        <section class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
            <div><flux:heading>Packaging units</flux:heading><flux:subheading>Set how many base units are in each package (for example, 24 pieces per carton).</flux:subheading></div>
            @foreach ($packagingUnits as $index => $row)
                <div class="grid items-end gap-3 sm:grid-cols-[1fr_1fr_auto]" wire:key="packaging-unit-{{ $index }}">
                    <flux:select wire:model="packagingUnits.{{ $index }}.unit_id" label="Unit"><option value="">Select a unit</option>@foreach ($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->abbreviation }})</option>@endforeach</flux:select>
                    <flux:input wire:model="packagingUnits.{{ $index }}.factor" label="Base units per package" type="number" min="0.001" step="0.001" />
                    <flux:button type="button" variant="ghost" wire:click="removePackagingUnit({{ $index }})">Remove</flux:button>
                </div>
            @endforeach
            <flux:button type="button" variant="filled" icon="plus" wire:click="addPackagingUnit">Add packaging unit</flux:button>
        </section>

        <div class="flex justify-end gap-3 border-t border-zinc-200 pt-5 dark:border-zinc-700"><flux:button type="button" variant="ghost" wire:click="closeForm">Cancel</flux:button><flux:button type="submit" variant="primary">Save product</flux:button></div>
    </form>
</flux:modal>