<?php

namespace App\Livewire\Roles;

use App\Livewire\Actions\Roles\CreateRole;
use App\Livewire\Actions\Roles\DeleteRole;
use App\Livewire\Actions\Roles\RenameRole;
use App\Livewire\Actions\Roles\ResetRolePermissions;
use App\Livewire\Actions\Roles\SaveRolePermissions;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Manage roles and permissions: view matrix, edit, create, rename, delete, reset. */
#[Title('Roles & Permissions')]
class Index extends Component
{
    // ─── Selected role (permission matrix panel) ────────────────────────────

    #[Locked]
    public ?int $selectedRoleId = null;

    /** @var string[] Permission names currently checked in the matrix for the selected role. */
    public array $pendingPermissions = [];

    /** Search query applied to the permission matrix. */
    public string $permissionSearch = '';

    // ─── Save confirmation (when removing permissions from a role with users) ─

    /** @var string[] Labels of permissions that will be removed, used for the confirmation modal. */
    public array $removedPermissionLabels = [];

    // ─── Create role modal ───────────────────────────────────────────────────

    public string $newRoleName = '';

    public string $newRoleDescription = '';

    // ─── Rename role modal ──────────────────────────────────────────────────

    #[Locked]
    public ?int $renameRoleId = null;

    public string $renameName = '';

    // ─── Delete role modal ──────────────────────────────────────────────────

    #[Locked]
    public ?int $deleteRoleId = null;

    // ─── Lifecycle ──────────────────────────────────────────────────────────

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('roles.view'), 403);

        // Default: select the first role in the list.
        $first = Role::orderByRaw("CASE name WHEN 'Admin' THEN 0 WHEN 'Storekeeper' THEN 1 WHEN 'Cashier' THEN 2 WHEN 'Viewer' THEN 3 ELSE 4 END, name")
            ->first();

        if ($first !== null) {
            $this->selectRole((int) $first->id);
        }
    }

    // ─── Role selection ──────────────────────────────────────────────────────

    public function selectRole(int $id): void
    {
        abort_unless(auth()->user()?->can('roles.view'), 403);

        $role = Role::findOrFail($id);
        $this->selectedRoleId = (int) $role->id;
        $this->pendingPermissions = $role->permissions->pluck('name')->all();
        $this->permissionSearch = '';
        $this->removedPermissionLabels = [];
        $this->resetValidation();
    }

    // ─── Save permissions ───────────────────────────────────────────────────

    /**
     * Called when the user clicks "Save changes".
     * Shows a confirmation modal if permissions are being removed from a role with users.
     */
    public function confirmOrSave(SaveRolePermissions $saveRolePermissions): void
    {
        abort_unless(auth()->user()?->can('roles.update'), 403);

        $role = Role::findOrFail($this->requiredSelectedRoleId());

        $oldPermissions = $role->permissions->pluck('name')->all();
        $removed = array_diff($oldPermissions, $this->pendingPermissions);

        $userCount = $role->users()->count();

        if ($userCount > 0 && ! empty($removed)) {
            // Build human-readable labels for the confirmation modal.
            $allPerms = $this->buildPermissionLabelMap();
            $this->removedPermissionLabels = array_values(
                array_map(fn (string $p) => $allPerms[$p] ?? $p, $removed)
            );

            $this->dispatch('modal-show', name: 'save-confirm');

            return;
        }

        $this->doSave($saveRolePermissions);
    }

    /** Called from the confirmation modal when the user accepts the removal. */
    public function saveConfirmed(SaveRolePermissions $saveRolePermissions): void
    {
        abort_unless(auth()->user()?->can('roles.update'), 403);
        $this->dispatch('modal-close', name: 'save-confirm');
        $this->doSave($saveRolePermissions);
    }

    public function cancelSave(): void
    {
        $this->removedPermissionLabels = [];
        $this->dispatch('modal-close', name: 'save-confirm');
    }

    private function doSave(SaveRolePermissions $saveRolePermissions): void
    {
        $role = Role::findOrFail($this->requiredSelectedRoleId());
        $saveRolePermissions($role, $this->pendingPermissions);
        $this->removedPermissionLabels = [];
        Flux::toast(variant: 'success', text: 'Permissions for "'.$role->name.'" saved.');
    }

    // ─── Create role ────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        abort_unless(auth()->user()?->can('roles.create'), 403);
        $this->newRoleName = '';
        $this->newRoleDescription = '';
        $this->resetValidation();
        $this->dispatch('modal-show', name: 'create-role');
    }

    public function storeRole(CreateRole $createRole): void
    {
        abort_unless(auth()->user()?->can('roles.create'), 403);

        $this->newRoleName = trim($this->newRoleName);

        $this->validate([
            'newRoleName' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')],
            'newRoleDescription' => ['nullable', 'string', 'max:500'],
        ]);

        $role = $createRole($this->newRoleName, $this->newRoleDescription);

        $this->dispatch('modal-close', name: 'create-role');
        $this->selectRole((int) $role->id);
        Flux::toast(variant: 'success', text: 'Role "'.$role->name.'" created.');
    }

    public function closeCreate(): void
    {
        $this->dispatch('modal-close', name: 'create-role');
    }

    // ─── Rename role ────────────────────────────────────────────────────────

    public function openRename(int $id): void
    {
        abort_unless(auth()->user()?->can('roles.update'), 403);

        $role = Role::findOrFail($id);
        $systemRoles = ['Admin', 'Storekeeper', 'Cashier', 'Viewer'];
        abort_if(in_array($role->name, $systemRoles, true), 403);

        $this->renameRoleId = (int) $role->id;
        $this->renameName = $role->name;
        $this->resetValidation();
        $this->dispatch('modal-show', name: 'rename-role');
    }

    public function renameRole(RenameRole $renameRole): void
    {
        abort_unless(auth()->user()?->can('roles.update'), 403);

        $role = Role::findOrFail($this->requiredRenameRoleId());

        $this->renameName = trim($this->renameName);

        $this->validate([
            'renameName' => [
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
        ]);

        $renameRole($role, $this->renameName);

        // Update pending state if the renamed role is currently selected.
        $this->dispatch('modal-close', name: 'rename-role');
        $this->renameRoleId = null;
        Flux::toast(variant: 'success', text: 'Role renamed to "'.$this->renameName.'".');
    }

    public function closeRename(): void
    {
        $this->renameRoleId = null;
        $this->dispatch('modal-close', name: 'rename-role');
    }

    // ─── Delete role ────────────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        abort_unless(auth()->user()?->can('roles.delete'), 403);

        $role = Role::findOrFail($id);
        $systemRoles = ['Admin', 'Storekeeper', 'Cashier', 'Viewer'];
        abort_if(in_array($role->name, $systemRoles, true), 403);

        $this->deleteRoleId = (int) $role->id;
        $this->dispatch('modal-show', name: 'delete-role');
    }

    public function deleteRole(DeleteRole $deleteRole): void
    {
        abort_unless(auth()->user()?->can('roles.delete'), 403);

        $role = Role::findOrFail($this->requiredDeleteRoleId());

        try {
            $deleteRole($role);
        } catch (HttpException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
            $this->dispatch('modal-close', name: 'delete-role');
            $this->deleteRoleId = null;

            return;
        }

        $this->dispatch('modal-close', name: 'delete-role');
        $this->deleteRoleId = null;

        // If we deleted the selected role, reset selection.
        if ($this->selectedRoleId === $role->id) {
            $this->selectedRoleId = null;
            $this->pendingPermissions = [];
        }

        Flux::toast(variant: 'success', text: 'Role "'.$role->name.'" deleted.');
    }

    public function closeDelete(): void
    {
        $this->deleteRoleId = null;
        $this->dispatch('modal-close', name: 'delete-role');
    }

    // ─── Reset to defaults ──────────────────────────────────────────────────

    public function resetToDefault(ResetRolePermissions $resetRolePermissions): void
    {
        abort_unless(auth()->user()?->can('roles.update'), 403);

        $role = Role::findOrFail($this->requiredSelectedRoleId());
        $resetRolePermissions($role);

        // Reload matrix state.
        $this->pendingPermissions = $role->fresh()->permissions->pluck('name')->all();
        Flux::toast(variant: 'success', text: '"'.$role->name.'" reset to defaults.');
    }

    // ─── Render ─────────────────────────────────────────────────────────────

    public function render(): View
    {
        abort_unless(auth()->user()?->can('roles.view'), 403);

        $systemOrder = "CASE name WHEN 'Admin' THEN 0 WHEN 'Storekeeper' THEN 1 WHEN 'Cashier' THEN 2 WHEN 'Viewer' THEN 3 ELSE 4 END, name";

        $availableRoles = Role::withCount('users')
            ->orderByRaw($systemOrder)
            ->get()
            ->map(function (Role $r): array {
                $customisedAt = DB::table('roles')->where('id', $r->id)->value('customised_at');

                return [
                    'id' => $r->id,
                    'name' => $r->name,
                    'description' => DB::table('roles')->where('id', $r->id)->value('description'),
                    'users_count' => $r->users_count,
                    'is_system' => in_array($r->name, ['Admin', 'Storekeeper', 'Cashier', 'Viewer'], true),
                    'is_customised' => $customisedAt !== null,
                ];
            });

        $selectedRole = $this->selectedRoleId !== null ? Role::find($this->selectedRoleId) : null;

        $availablePermissions = $this->buildFilteredModules();

        $roleUsers = [];
        if ($selectedRole !== null) {
            $roleUsers = User::whereHas('roles', fn ($q) => $q->where('roles.id', $selectedRole->id))
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        $selectedRoleIsSystem = $selectedRole !== null
            && in_array($selectedRole->name, ['Admin', 'Storekeeper', 'Cashier', 'Viewer'], true);

        $deleteRole = $this->deleteRoleId !== null ? Role::find($this->deleteRoleId) : null;

        return view('livewire.roles.index', compact(
            'availableRoles',
            'selectedRole',
            'availablePermissions',
            'roleUsers',
            'selectedRoleIsSystem',
            'deleteRole',
        ));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function requiredSelectedRoleId(): int
    {
        abort_if($this->selectedRoleId === null, 422);

        return $this->selectedRoleId;
    }

    private function requiredRenameRoleId(): int
    {
        abort_if($this->renameRoleId === null, 422);

        return $this->renameRoleId;
    }

    private function requiredDeleteRoleId(): int
    {
        abort_if($this->deleteRoleId === null, 422);

        return $this->deleteRoleId;
    }

    /**
     * Return catalogue modules with permissions filtered by $permissionSearch.
     * Each module has a 'permissions' array and a 'match_count'.
     *
     * @return array<string, array{permissions: array<int, array{name: string, label: string, description: string}>, match_count: int}>
     */
    private function buildFilteredModules(): array
    {
        $modules = config('permissions.modules', []);
        $search = strtolower(trim($this->permissionSearch));
        $result = [];

        foreach ($modules as $moduleName => $module) {
            $perms = $module['permissions'];

            if ($search !== '') {
                $perms = array_filter($perms, function (array $p) use ($search): bool {
                    return str_contains(strtolower($p['label']), $search)
                        || str_contains(strtolower($p['description']), $search)
                        || str_contains(strtolower($p['name']), $search);
                });
            }

            $perms = array_values($perms);

            $result[$moduleName] = [
                'permissions' => $perms,
                'match_count' => count($perms),
            ];
        }

        return $result;
    }

    /**
     * Build a name → label map for all catalogue permissions.
     *
     * @return array<string, string>
     */
    private function buildPermissionLabelMap(): array
    {
        $map = [];
        foreach (config('permissions.modules', []) as $module) {
            foreach ($module['permissions'] as $perm) {
                $map[$perm['name']] = $perm['label'];
            }
        }

        return $map;
    }
}
