<flux:modal name="quotation-delete" class="max-w-md">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">Delete draft quotation?</flux:heading>
            <flux:subheading>This action is permanent and cannot be undone.</flux:subheading>
        </div>
        <div class="flex justify-end gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" wire:click="closeDeleteConfirmation">Cancel</flux:button>
            <flux:button type="button" variant="danger" wire:click="deleteDraft">Delete</flux:button>
        </div>
    </div>
</flux:modal>
