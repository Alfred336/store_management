<flux:modal name="user-delete" class="w-full max-w-lg">
    @if ($selectedUser)
        @if (! $hasActivity)
            {{-- ── Hard-delete path: no activity history ── --}}
            <div class="space-y-4 p-6">
                <flux:heading size="lg">Delete user</flux:heading>
                <flux:text>
                    Are you sure you want to permanently delete <strong>{{ $selectedUser->name }}</strong>?
                    This user has no activity history and will be removed completely.
                    This action cannot be undone.
                </flux:text>
                <div class="flex justify-end gap-3">
                    <flux:button wire:click="closeDeleteConfirmation">Cancel</flux:button>
                    <flux:button variant="danger" wire:click="delete" wire:loading.attr="disabled">Delete permanently</flux:button>
                </div>
            </div>
        @else
            {{-- ── Anonymize path: user has activity history ── --}}
            <div class="space-y-4 p-6">
                <flux:heading size="lg">Delete permanently (anonymize)</flux:heading>

                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                    <p class="font-medium">This user has activity history that must be preserved.</p>
                    <p class="mt-1">
                        Permanently deleting this account will <strong>anonymize</strong> it:
                        the name becomes "Deleted user #{{ $selectedUser->id }}",
                        the email is cleared, all credentials and roles are removed,
                        and the account is permanently deactivated.
                        Historical records (invoices, payments, stock movements) will continue
                        to reference this row but will show the anonymized name.
                    </p>
                    <p class="mt-2 font-medium">This action cannot be undone.</p>
                </div>

                <div class="space-y-2">
                    <flux:label for="confirm-email">Type <strong>{{ $selectedUser->email }}</strong> to confirm</flux:label>
                    <flux:input
                        id="confirm-email"
                        wire:model="confirmEmail"
                        type="email"
                        placeholder="{{ $selectedUser->email }}"
                        autocomplete="off"
                    />
                    @error('confirmEmail')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-3">
                    <flux:button wire:click="closeDeleteConfirmation">Cancel</flux:button>
                    <flux:button variant="danger" wire:click="anonymize" wire:loading.attr="disabled">Delete permanently (anonymize)</flux:button>
                </div>
            </div>
        @endif
    @endif
</flux:modal>
