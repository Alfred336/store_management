<flux:modal name="rename-role" class="w-full max-w-md">
    <div class="space-y-6 p-6">
        <flux:heading size="lg">Rename role</flux:heading>

        <div class="space-y-4">
            <flux:input wire:model="renameName" label="New role name" required />
            @error('renameName') <flux:error>{{ $message }}</flux:error> @enderror
        </div>

        <div class="flex justify-end gap-3">
            <flux:button wire:click="closeRename">Cancel</flux:button>
            <flux:button variant="primary" wire:click="renameRole">Save name</flux:button>
        </div>
    </div>
</flux:modal>
