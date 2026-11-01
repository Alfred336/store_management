<flux:modal name="location-form" class="w-full max-w-xl">
    <form wire:submit="{{ $recordId ? 'update' : 'store' }}" class="space-y-6">
        <div><flux:heading size="lg">{{ $recordId ? 'Edit location' : 'Create location' }}</flux:heading><flux:subheading>Deactivate locations to preserve stock history.</flux:subheading></div>
        <flux:input wire:model="name" label="Name" required />
        <flux:select wire:model="type" label="Type" required><option value="store">Store</option><option value="warehouse">Warehouse</option><option value="branch">Branch</option></flux:select>
        <flux:checkbox wire:model="isActive" label="Location is active" />
        <div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="closeForm">Cancel</flux:button><flux:button type="submit" variant="primary">Save location</flux:button></div>
    </form>
</flux:modal>