<div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
        <thead class="bg-zinc-50 dark:bg-zinc-800">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Name</th>
                <th class="px-4 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Email</th>
                <th class="px-4 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Roles</th>
                <th class="px-4 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Last login</th>
                <th class="px-4 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900">
            @forelse ($users as $user)
                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                    <td class="px-4 py-3 font-medium">
                        <button wire:click="show({{ $user->id }})" class="text-left hover:underline">
                            {{ $user->name }}
                        </button>
                    </td>
                    <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">{{ $user->email }}</td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-1">
                            @foreach ($user->roles as $role)
                                <flux:badge variant="outline" size="sm">{{ $role->name }}</flux:badge>
                            @endforeach
                        </div>
                    </td>
                    <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">
                        {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : '—' }}
                    </td>
                    <td class="px-4 py-3">
                        @if ($user->is_active)
                            <flux:badge color="green" size="sm">Active</flux:badge>
                        @else
                            <flux:badge color="red" size="sm">Inactive</flux:badge>
                        @endif
                        @if ($user->must_change_password)
                            <flux:badge color="yellow" size="sm" class="ml-1">Pwd reset</flux:badge>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                            @can('users.update')
                                @if ($user->id !== auth()->id())
                                    <flux:button size="sm" icon="pencil" wire:click="openEdit({{ $user->id }})">Edit</flux:button>
                                @endif
                            @endcan
                            @can('users.deactivate')
                                @if ($user->id !== auth()->id())
                                    @if ($user->is_active)
                                        <flux:button size="sm" variant="danger" icon="no-symbol" wire:click="deactivate({{ $user->id }})" wire:confirm="Deactivate {{ $user->name }}?">Deactivate</flux:button>
                                    @else
                                        <flux:button size="sm" icon="check-circle" wire:click="reactivate({{ $user->id }})">Reactivate</flux:button>
                                    @endif
                                @endif
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-zinc-400">No users found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $users->links() }}
</div>
