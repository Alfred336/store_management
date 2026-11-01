<flux:modal name="customer-delete" class="w-full max-w-lg">
    <div class="space-y-5">
        <div><flux:heading size="lg">Delete customer?</flux:heading><flux:subheading>Customers with invoice or payment history cannot be deleted; deactivate them instead.</flux:subheading></div>
        <div class="flex justify-end gap-3"><flux:button variant="ghost" wire:click="closeDeleteConfirmation">Cancel</flux:button><flux:button variant="danger" wire:click="delete">Delete customer</flux:button></div>
    </div>
</flux:modal>