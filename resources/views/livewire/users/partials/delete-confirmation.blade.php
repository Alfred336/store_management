<flux:modal name="user-delete" class="w-full max-w-md">
    @if ($selectedUser)
        <div class="space-y-4 p-6">
            <flux:heading size="lg">Delete user</flux:heading>
            <flux:text>
                Are you sure you want to permanently delete <strong>{{ $selectedUser->name }}</strong>?
                This action cannot be undone. Users with any activity (invoices, payments, stock movements,
                POS sessions) cannot be deleted.
            </flux:text>
            <div class="flex justify-end gap-3">
                <flux:button wire:click="closeDeleteConfirmation">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Delete permanently</flux:button>
            </div>
        </div>
    @endif
</flux:modal>
