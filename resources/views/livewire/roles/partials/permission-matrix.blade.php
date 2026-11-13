<div class="space-y-4">
    @if ($selectedRole === null)
        <div class="flex h-48 items-center justify-center rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-zinc-400">Select a role to view its permissions.</flux:text>
        </div>
    @else
        {{-- Role header --}}
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ $selectedRole->name }}</flux:heading>
                    @php
                        $desc = \Illuminate\Support\Facades\DB::table('roles')->where('id', $selectedRole->id)->value('description');
                    @endphp
                    @if ($desc)
                        <flux:text class="mt-1 text-sm text-zinc-500">{{ $desc }}</flux:text>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    @if ($selectedRole->name !== 'Admin')
                        @can('roles.update')
                            @if ($selectedRoleIsSystem)
                                <flux:button size="sm" wire:click="resetToDefault" wire:confirm="Reset '{{ $selectedRole->name }}' to its default permissions?">
                                    Reset to default
                                </flux:button>
                            @else
                                <flux:button size="sm" wire:click="openRename({{ $selectedRole->id }})">
                                    Rename
                                </flux:button>
                            @endif
                            <flux:button variant="primary" size="sm" wire:click="confirmOrSave">
                                Save changes
                            </flux:button>
                        @endcan
                        @can('roles.delete')
                            @if (! $selectedRoleIsSystem)
                                <flux:button variant="danger" size="sm" wire:click="confirmDelete({{ $selectedRole->id }})">
                                    Delete role
                                </flux:button>
                            @endif
                        @endcan
                    @else
                        <flux:badge color="blue">Read-only — Admin always holds all permissions</flux:badge>
                    @endif
                </div>
            </div>
        </div>

        {{-- Permission search --}}
        <div>
            <flux:input
                wire:model.live="permissionSearch"
                placeholder="Search permissions…"
                icon="magnifying-glass"
                clearable
            />
        </div>

        {{-- Permission matrix --}}
        @php
            $isAdmin = $selectedRole->name === 'Admin';
            $totalVisible = collect($availablePermissions)->sum('match_count');
        @endphp

        @if ($totalVisible === 0)
            <flux:text class="text-zinc-400">No permissions match your search.</flux:text>
        @endif

        @foreach ($availablePermissions as $moduleName => $module)
            @if ($module['match_count'] > 0)
                <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"
                     data-module="{{ $moduleName }}">
                    {{-- Module header with select-all --}}
                    <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-2.5 dark:border-zinc-700">
                        <flux:heading size="sm">{{ $moduleName }}</flux:heading>
                        @if (! $isAdmin)
                            @php
                                $modulePermNames = array_column($module['permissions'], 'name');
                                $moduleCheckedCount = count(array_intersect($modulePermNames, $pendingPermissions));
                                $allChecked = $moduleCheckedCount === count($modulePermNames);
                            @endphp
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-zinc-500">{{ $moduleCheckedCount }} / {{ count($modulePermNames) }}</span>
                                @can('roles.update')
                                    @if ($allChecked)
                                        <button
                                            wire:click="$set('pendingPermissions', array_values(array_diff($pendingPermissions, {{ json_encode($modulePermNames) }})))"
                                            class="text-xs text-blue-600 hover:underline dark:text-blue-400"
                                        >Deselect all</button>
                                    @else
                                        <button
                                            wire:click="$set('pendingPermissions', array_values(array_unique(array_merge($pendingPermissions, {{ json_encode($modulePermNames) }}))))"
                                            class="text-xs text-blue-600 hover:underline dark:text-blue-400"
                                        >Select all</button>
                                    @endif
                                @endcan
                            </div>
                        @endif
                    </div>

                    {{-- Permission rows --}}
                    <ul class="divide-y divide-zinc-50 dark:divide-zinc-800">
                        @foreach ($module['permissions'] as $perm)
                            <li class="flex items-start gap-3 px-4 py-3">
                                @if ($isAdmin)
                                    <input type="checkbox" checked disabled
                                           class="mt-0.5 rounded border-zinc-300 opacity-50 dark:border-zinc-600" />
                                @else
                                    @can('roles.update')
                                        <input
                                            type="checkbox"
                                            wire:model="pendingPermissions"
                                            value="{{ $perm['name'] }}"
                                            class="mt-0.5 rounded border-zinc-300 dark:border-zinc-600"
                                        />
                                    @else
                                        <input
                                            type="checkbox"
                                            {{ in_array($perm['name'], $pendingPermissions) ? 'checked' : '' }}
                                            disabled
                                            class="mt-0.5 rounded border-zinc-300 opacity-50 dark:border-zinc-600"
                                        />
                                    @endcan
                                @endif
                                <div class="min-w-0 flex-1">
                                    <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $perm['label'] }}</span>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $perm['description'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach

        {{-- Users holding this role --}}
        @if (count($roleUsers) > 0)
            <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-4 py-2.5 dark:border-zinc-700">
                    <flux:heading size="sm">Users with this role ({{ count($roleUsers) }})</flux:heading>
                </div>
                <ul class="divide-y divide-zinc-50 dark:divide-zinc-800">
                    @foreach ($roleUsers as $u)
                        <li class="flex items-center justify-between px-4 py-2.5">
                            <div>
                                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $u->name }}</span>
                                <span class="ml-2 text-xs text-zinc-500">{{ $u->email }}</span>
                            </div>
                            <a href="{{ route('users.index') }}" wire:navigate
                               class="text-xs text-blue-600 hover:underline dark:text-blue-400">
                                Manage users →
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>
