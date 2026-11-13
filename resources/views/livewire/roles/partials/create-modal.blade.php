<flux:modal name="create-role" class="w-full max-w-md">
    <div class="space-y-6 p-6">
        <flux:heading size="lg">New role</flux:heading>

        <div class="space-y-4">
            <flux:input wire:model="newRoleName" label="Role name" placeholder="e.g. Supervisor" required />
            @error('newRoleName') <flux:error>{{ $message }}</flux:error> @enderror

            <flux:textarea wire:model="newRoleDescription" label="Description" placeholder="Optional — describe what this role is for." rows="2" />
            @error('newRoleDescription') <flux:error>{{ $message }}</flux:error> @enderror
        </div>

        <div class="flex justify-end gap-3">
            <flux:button wire:click="closeCreate">Cancel</flux:button>
            <flux:button variant="primary" wire:click="storeRole">Create role</flux:button>
        </div>
    </div>
</flux:modal>
