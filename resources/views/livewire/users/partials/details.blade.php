<flux:modal name="user-details" class="w-full max-w-lg">
    @if ($selectedUser)
        <div class="space-y-6 p-6">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="lg">{{ $selectedUser->name }}</flux:heading>
                    <flux:text class="text-zinc-500">{{ $selectedUser->email }}</flux:text>
                </div>
                <div class="flex flex-wrap gap-1">
                    @if ($selectedUser->is_active)
                        <flux:badge color="green">Active</flux:badge>
                    @else
                        <flux:badge color="red">Inactive</flux:badge>
                    @endif
                    @if ($selectedUser->must_change_password)
                        <flux:badge color="yellow">Must change password</flux:badge>
                    @endif
                    @if ($selectedUser->two_factor_confirmed_at)
                        <flux:badge color="blue">2FA enabled</flux:badge>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <flux:text class="font-medium">Roles</flux:text>
                    <div class="mt-1 flex flex-wrap gap-1">
                        @foreach ($selectedUser->roles as $role)
                            <flux:badge variant="outline">{{ $role->name }}</flux:badge>
                        @endforeach
                    </div>
                </div>
                <div>
                    <flux:text class="font-medium">Last login</flux:text>
                    <flux:text class="mt-1 text-zinc-500">
                        {{ $selectedUser->last_login_at ? $selectedUser->last_login_at->format('d M Y H:i') : 'Never' }}
                    </flux:text>
                </div>
                <div>
                    <flux:text class="font-medium">Created</flux:text>
                    <flux:text class="mt-1 text-zinc-500">{{ $selectedUser->created_at->format('d M Y') }}</flux:text>
                </div>
            </div>

            @if (!empty($activityCounts))
                <div>
                    <flux:heading size="sm" class="mb-2">Activity</flux:heading>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        @foreach ([
                            'invoices'        => 'Invoices',
                            'payments'        => 'Payments',
                            'stock_movements' => 'Stock movements',
                            'pos_sessions'    => 'POS sessions',
                        ] as $key => $label)
                            <div class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-800">
                                <span class="text-zinc-600 dark:text-zinc-400">{{ $label }}</span>
                                <span class="font-medium">{{ $activityCounts[$key] ?? 0 }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($recentAudit->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="mb-2">Recent audit log</flux:heading>
                    <ul class="space-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                        @foreach ($recentAudit as $log)
                            <li class="flex items-center justify-between">
                                <span>{{ $log->action }}</span>
                                <span class="text-xs">{{ $log->created_at->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($selectedUser->id !== auth()->id())
                <div class="flex flex-wrap gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    @can('users.update')
                        <flux:button size="sm" icon="pencil" wire:click="openEdit({{ $selectedUser->id }})">Edit</flux:button>
                    @endcan
                    @can('users.reset_password')
                        <flux:button size="sm" icon="key" wire:click="resetPassword({{ $selectedUser->id }})" wire:confirm="Reset password for {{ $selectedUser->name }}?">Reset password</flux:button>
                        @if ($selectedUser->two_factor_confirmed_at)
                            <flux:button size="sm" icon="shield-exclamation" wire:click="resetTwoFactor({{ $selectedUser->id }})" wire:confirm="Clear 2FA for {{ $selectedUser->name }}?">Reset 2FA</flux:button>
                        @endif
                    @endcan
                    @can('users.deactivate')
                        @if ($selectedUser->is_active)
                            <flux:button size="sm" variant="danger" icon="no-symbol" wire:click="deactivate({{ $selectedUser->id }})" wire:confirm="Deactivate {{ $selectedUser->name }}?">Deactivate</flux:button>
                        @else
                            <flux:button size="sm" icon="check-circle" wire:click="reactivate({{ $selectedUser->id }})">Reactivate</flux:button>
                        @endif
                    @endcan
                    @can('users.delete')
                        <flux:button size="sm" variant="danger" icon="trash" wire:click="confirmDelete({{ $selectedUser->id }})">Delete</flux:button>
                    @endcan
                </div>
            @endif
        </div>
    @endif
</flux:modal>
