<flux:modal name="user-form" class="w-full max-w-lg">
    <div class="space-y-6 p-6">
        <flux:heading size="lg">{{ $userId ? 'Edit user' : 'New user' }}</flux:heading>

        <div class="space-y-4">
            <flux:input wire:model="name" label="Full name" placeholder="Jane Doe" required />
            <flux:input wire:model="email" type="email" label="Email address" placeholder="jane@example.com" required />

            <div>
                <flux:label>Roles</flux:label>
                <div class="mt-2 space-y-2">
                    @foreach ($availableRoles as $role)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="roles" value="{{ $role }}"
                                   class="rounded border-zinc-300 dark:border-zinc-600" />
                            {{ $role }}
                        </label>
                    @endforeach
                </div>
                @error('roles') <flux:error>{{ $message }}</flux:error> @enderror
                @error('roles.*') <flux:error>{{ $message }}</flux:error> @enderror
            </div>

            @if (!$userId)
                <flux:input wire:model="password" type="password" label="Temporary password"
                            description="Leave blank to auto-generate." placeholder="Min. 8 characters" />
            @endif
        </div>

        @if ($errors->any())
            <div class="space-y-1">
                @foreach ($errors->all() as $error)
                    @if (!in_array($error, $errors->get('roles') ?? [], true))
                        <flux:error>{{ $error }}</flux:error>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="flex justify-end gap-3">
            <flux:button wire:click="closeForm">Cancel</flux:button>
            @if ($userId)
                <flux:button variant="primary" wire:click="update">Save changes</flux:button>
            @else
                <flux:button variant="primary" wire:click="store">Create user</flux:button>
            @endif
        </div>
    </div>
</flux:modal>
