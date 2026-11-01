<flux:modal name="product-delete" class="w-full max-w-lg">
    <div class="space-y-5"><div><flux:heading size="lg">Archive product?</flux:heading><flux:subheading>Products with stock movement history cannot be archived. Deactivate them instead.</flux:subheading></div>
        <div class="flex justify-end gap-3"><flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close><flux:button variant="danger" wire:click="delete">Archive product</flux:button></div>
    </div>
</flux:modal>