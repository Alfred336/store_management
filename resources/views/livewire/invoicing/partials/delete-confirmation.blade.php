<flux:modal name="invoice-delete" class="w-full max-w-lg">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">Delete draft invoice?</flux:heading>
            <flux:subheading>Only draft invoices can be deleted. Issued invoices must be voided instead.</flux:subheading>
        </div>
        <div class="flex justify-end gap-3">
            <flux:button variant="ghost" wire:click="closeDeleteConfirmation">Cancel</flux:button>
            <flux:button variant="danger" wire:click="deleteDraft">Delete draft</flux:button>
        </div>
    </div>
</flux:modal>
