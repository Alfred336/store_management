<div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
    <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
        <flux:heading size="sm">Roles</flux:heading>
    </div>

    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
        @foreach ($availableRoles as $role)
            <li>
                <button
                    wire:click="selectRole({{ $role['id'] }})"
                    class="w-full px-4 py-3 text-left transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800 {{ $selectedRoleId === $role['id'] ? 'bg-zinc-100 dark:bg-zinc-800' : '' }}"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $role['name'] }}
                        </span>
                        <div class="flex shrink-0 items-center gap-1.5">
                            @if ($role['is_system'])
                                <flux:badge size="sm" color="blue">System</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">Custom</flux:badge>
                            @endif
                        </div>
                    </div>
                    <div class="mt-0.5 flex items-center gap-2">
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $role['users_count'] }} {{ Str::plural('user', $role['users_count']) }}
                        </span>
                        @if ($role['is_customised'])
                            <span class="text-xs text-amber-600 dark:text-amber-400">· customised</span>
                        @endif
                    </div>
                </button>
            </li>
        @endforeach
    </ul>
</div>
