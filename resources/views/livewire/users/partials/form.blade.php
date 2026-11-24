<flux:modal name="user-form" class="w-full max-w-lg">
    <div class="space-y-6 p-6">
        <flux:heading size="lg">{{ $userId ? 'Edit user' : 'New user' }}</flux:heading>

        <div class="space-y-4">
            <flux:input wire:model="name" label="Full name" placeholder="Jane Doe" required autofocus />
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

            {{-- ── Locations ───────────────────────────────────────────────── --}}
            <div>
                <flux:label>Locations</flux:label>
                @php $assigningAdmin = in_array('Admin', $roles, true); @endphp
                @if ($assigningAdmin)
                    <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">All locations (Admin)</flux:text>
                @else
                    @php
                        // Non-admin actors may only tick their own locations.
                        $assignableLocations = $actorAllowedLocationIds === null
                            ? $activeLocations
                            : $activeLocations->whereIn('id', $actorAllowedLocationIds);
                    @endphp
                    <div class="mt-2 space-y-2">
                        @forelse ($assignableLocations as $location)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="locationIds" value="{{ $location->id }}"
                                       class="rounded border-zinc-300 dark:border-zinc-600" />
                                {{ $location->name }}
                                <span class="text-xs text-zinc-400">{{ $location->type }}</span>
                            </label>
                        @empty
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">No active locations available.</flux:text>
                        @endforelse
                    </div>
                @endif
                @error('locationIds') <flux:error>{{ $message }}</flux:error> @enderror
            </div>
        </div>

        {{-- ── Password section (create) ─────────────────────────────────── --}}
        @if (! $userId)
            <div class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="sm">Password</flux:heading>

                <div class="space-y-2">
                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                        <input type="radio" wire:model.live="passwordMode" value="generate"
                               class="accent-accent" />
                        Generate automatically
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                        <input type="radio" wire:model.live="passwordMode" value="manual"
                               class="accent-accent" />
                        Set manually
                    </label>
                </div>

                @if ($passwordMode === 'manual')
                    <flux:input type="password" wire:model="plainPassword"
                                label="Password" placeholder="Minimum 8 characters" />
                    @error('plainPassword') <flux:error>{{ $message }}</flux:error> @enderror

                    <flux:input type="password" wire:model="plainPasswordConfirmation"
                                label="Confirm password" />
                    @error('plainPasswordConfirmation') <flux:error>{{ $message }}</flux:error> @enderror

                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="emailPassword"
                               class="rounded border-zinc-300 dark:border-zinc-600" />
                        Send password by email
                    </label>
                @else
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        A secure password will be generated and emailed to the user.
                    </flux:text>
                @endif

                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="mustChangePassword"
                           class="rounded border-zinc-300 dark:border-zinc-600" />
                    Require password change on first login
                </label>
                @error('mustChangePassword') <flux:error>{{ $message }}</flux:error> @enderror
            </div>
        @endif

        {{-- ── Reset password section (edit) ────────────────────────────── --}}
        @if ($userId && $userId !== auth()->id())
            @can('users.reset_password')
                <div class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="checkbox" wire:model.live="doResetPassword"
                               class="rounded border-zinc-300 dark:border-zinc-600" />
                        <span class="text-sm font-medium">Reset this user's password</span>
                    </label>

                    @if ($doResetPassword)
                        <div class="space-y-3 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                            <div class="space-y-2">
                                <label class="flex cursor-pointer items-center gap-2 text-sm">
                                    <input type="radio" wire:model.live="passwordMode" value="generate"
                                           class="accent-accent" />
                                    Generate automatically
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 text-sm">
                                    <input type="radio" wire:model.live="passwordMode" value="manual"
                                           class="accent-accent" />
                                    Set manually
                                </label>
                            </div>

                            @if ($passwordMode === 'manual')
                                <flux:input type="password" wire:model="plainPassword"
                                            label="New password" placeholder="Minimum 8 characters" />
                                @error('plainPassword') <flux:error>{{ $message }}</flux:error> @enderror

                                <flux:input type="password" wire:model="plainPasswordConfirmation"
                                            label="Confirm new password" />
                                @error('plainPasswordConfirmation') <flux:error>{{ $message }}</flux:error> @enderror

                                <label class="flex cursor-pointer items-center gap-2 text-sm">
                                    <input type="checkbox" wire:model="emailPassword"
                                           class="rounded border-zinc-300 dark:border-zinc-600" />
                                    Send new password by email
                                </label>
                            @else
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    A secure password will be generated and emailed to the user.
                                </flux:text>
                            @endif

                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="mustChangePassword"
                                       class="rounded border-zinc-300 dark:border-zinc-600" />
                                Require password change on first login
                            </label>
                        </div>
                    @endif
                </div>
            @endcan
        @endif

        @error('error')
            <flux:error>{{ $message }}</flux:error>
        @enderror

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
