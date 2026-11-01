<flux:modal name="unit-form" class="w-full max-w-xl">
    <form wire:submit="{{ $recordId ? 'update' : 'store' }}" class="space-y-6">
        <div><flux:heading size="lg">{{ $recordId ? 'Edit unit' : 'Create unit' }}</flux:heading><flux:subheading>Provide a unique name and short label.</flux:subheading></div>
        <flux:input wire:model="name" label="Name" required />
        <flux:input wire:model="abbreviation" label="Abbreviation" maxlength="10" required />
        <div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="closeForm">Cancel</flux:button><flux:button type="submit" variant="primary">Save unit</flux:button></div>
    </form>
</flux:modal>