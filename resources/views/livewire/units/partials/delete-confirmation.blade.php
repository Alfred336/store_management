<flux:modal name="unit-delete" class="w-full max-w-lg">
    <div class="space-y-5"><div><flux:heading size="lg">Delete unit?</flux:heading><flux:subheading>A unit in use by a product cannot be deleted.</flux:subheading></div>
        <div class="flex justify-end gap-3"><flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close><flux:button variant="danger" wire:click="delete">Delete unit</flux:button></div>
    </div>
</flux:modal>