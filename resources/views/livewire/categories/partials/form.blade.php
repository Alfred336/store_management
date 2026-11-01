<flux:modal name="category-form" class="w-full max-w-xl">
    <form wire:submit="{{ $recordId ? 'update' : 'store' }}" class="space-y-6">
        <div><flux:heading size="lg">{{ $recordId ? 'Edit category' : 'Create category' }}</flux:heading><flux:subheading>Keep product categories easy to browse.</flux:subheading></div>
        <flux:input wire:model="name" label="Name" required />
        <div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="closeForm">Cancel</flux:button><flux:button type="submit" variant="primary">Save category</flux:button></div>
    </form>
</flux:modal>