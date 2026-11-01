<flux:modal name="category-delete" class="w-full max-w-lg">
    <div class="space-y-5"><div><flux:heading size="lg">Delete category?</flux:heading><flux:subheading>Categories with products are retained and cannot be deleted.</flux:subheading></div>
        <div class="flex justify-end gap-3"><flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close><flux:button variant="danger" wire:click="delete">Delete category</flux:button></div>
    </div>
</flux:modal>