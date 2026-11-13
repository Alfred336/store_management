<flux:modal name="delete-role" class="w-full max-w-md">
    <div class="space-y-6 p-6">
        <flux:heading size="lg">Delete role</flux:heading>

        @if ($deleteRole !== null)
            <flux:text>
                Are you sure you want to delete the role <strong>{{ $deleteRole->name }}</strong>?
                This action cannot be undone.
            </flux:text>
        @endif

        <div class="flex justify-end gap-3">
            <flux:button wire:click="closeDelete">Cancel</flux:button>
            <flux:button variant="danger" wire:click="deleteRole">Delete</flux:button>
        </div>
    </div>
</flux:modal>
