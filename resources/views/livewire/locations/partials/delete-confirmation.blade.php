<flux:modal name="location-delete" class="w-full max-w-lg">
    <div class="space-y-5"><div><flux:heading size="lg">Delete location?</flux:heading><flux:subheading>Locations with stock levels or movements cannot be deleted. Deactivate them instead.</flux:subheading></div>
        <div class="flex justify-end gap-3"><flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close><flux:button variant="danger" wire:click="delete">Delete location</flux:button></div>
    </div>
</flux:modal>