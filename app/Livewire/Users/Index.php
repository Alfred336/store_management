<?php

namespace App\Livewire\Users;

use App\Livewire\Actions\Users\CreateUser;
use App\Livewire\Actions\Users\DeactivateUser;
use App\Livewire\Actions\Users\DeleteUser;
use App\Livewire\Actions\Users\ReactivateUser;
use App\Livewire\Actions\Users\ResetTwoFactor;
use App\Livewire\Actions\Users\ResetUserPassword;
use App\Livewire\Actions\Users\UpdateUser;
use App\Models\AdminAuditLog;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/** Manage user accounts: create, edit, deactivate, reset password, 2FA and delete. */
#[Title('Users')]
class Index extends Component
{
    use WithPagination;

    // ─── Filters ────────────────────────────────────────────────────────────

    public string $search = '';

    public string $roleFilter = '';

    public string $statusFilter = 'active';

    // ─── Modal state ────────────────────────────────────────────────────────

    public bool $showForm = false;

    public bool $showDetails = false;

    public bool $showDeleteConfirmation = false;

    public bool $showPasswordReveal = false;

    /** Plain-text temporary password shown once after create/reset. */
    public string $revealedPassword = '';

    // ─── Form fields ────────────────────────────────────────────────────────

    #[Locked]
    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    /** @var string[] */
    public array $roles = [];

    public string $password = '';

    // ─── Lifecycle ──────────────────────────────────────────────────────────

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('users.view'), 403);
    }

    public function updatedSearch(): void
    {
        abort_unless(auth()->user()?->can('users.view'), 403);
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        abort_unless(auth()->user()?->can('users.view'), 403);
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        abort_unless(auth()->user()?->can('users.view'), 403);
        $this->resetPage();
    }

    // ─── Form open/close ────────────────────────────────────────────────────

    public function openCreate(): void
    {
        abort_unless(auth()->user()?->can('users.create'), 403);
        $this->clearFormState();
        $this->showForm = true;
        $this->showDetails = false;
        $this->dispatch('modal-show', name: 'user-form');
    }

    public function openEdit(int $id): void
    {
        $user = User::query()->findOrFail($id);
        $this->authorize('update', $user);
        $this->clearFormState();
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->roles = $user->roles->pluck('name')->all();
        $this->password = '';
        $this->showForm = true;
        $this->showDetails = false;
        $this->dispatch('modal-show', name: 'user-form');
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->clearFormState();
        $this->dispatch('modal-close', name: 'user-form');
    }

    // ─── Create / Update ────────────────────────────────────────────────────

    public function store(CreateUser $createUser): void
    {
        abort_unless(auth()->user()?->can('users.create'), 403);

        $this->name = trim($this->name);
        $this->email = strtolower(trim($this->email));

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ]);

        $result = $createUser(
            $data['name'],
            $data['email'],
            $data['roles'],
            $data['password'] ?: null,
        );

        $this->closeForm();
        $this->revealedPassword = $result['password'];
        $this->showPasswordReveal = true;
        $this->dispatch('modal-show', name: 'user-password-reveal');
        Flux::toast(variant: 'success', text: 'User created. Share the temporary password shown.');
    }

    public function update(UpdateUser $updateUser): void
    {
        $user = User::query()->findOrFail($this->requiredUserId());
        $this->authorize('update', $user);

        $this->name = trim($this->name);
        $this->email = strtolower(trim($this->email));

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        $updateUser($user, $data['name'], $data['email'], $data['roles']);
        $this->closeForm();
        Flux::toast(variant: 'success', text: 'User updated.');
    }

    // ─── Details panel ──────────────────────────────────────────────────────

    public function show(int $id): void
    {
        abort_unless(auth()->user()?->can('users.view'), 403);
        $this->userId = $id;
        $this->showDetails = true;
        $this->showForm = false;
        $this->dispatch('modal-show', name: 'user-details');
    }

    public function closeDetails(): void
    {
        abort_unless(auth()->user()?->can('users.view'), 403);
        $this->showDetails = false;
        $this->userId = null;
        $this->dispatch('modal-close', name: 'user-details');
    }

    // ─── Deactivate / Reactivate ─────────────────────────────────────────────

    public function deactivate(int $id, DeactivateUser $deactivateUser): void
    {
        $user = User::query()->findOrFail($id);
        $this->authorize('deactivateAdmin', $user);
        $deactivateUser($user);
        Flux::toast(variant: 'success', text: 'User deactivated.');
    }

    public function reactivate(int $id, ReactivateUser $reactivateUser): void
    {
        $user = User::query()->findOrFail($id);
        $this->authorize('deactivateAdmin', $user);
        $reactivateUser($user);
        Flux::toast(variant: 'success', text: 'User reactivated.');
    }

    // ─── Reset password ─────────────────────────────────────────────────────

    public function resetPassword(int $id, ResetUserPassword $resetUserPassword): void
    {
        $user = User::query()->findOrFail($id);
        $this->authorize('resetPassword', $user);
        $result = $resetUserPassword($user);
        $this->revealedPassword = $result['password'];
        $this->showPasswordReveal = true;
        $this->dispatch('modal-show', name: 'user-password-reveal');
        Flux::toast(variant: 'success', text: 'Password reset. Share the temporary password shown.');
    }

    public function closePasswordReveal(): void
    {
        $this->showPasswordReveal = false;
        $this->revealedPassword = '';
        $this->dispatch('modal-close', name: 'user-password-reveal');
    }

    // ─── Reset 2FA ──────────────────────────────────────────────────────────

    public function resetTwoFactor(int $id, ResetTwoFactor $resetTwoFactor): void
    {
        $user = User::query()->findOrFail($id);
        $this->authorize('resetPassword', $user);
        $resetTwoFactor($user);
        Flux::toast(variant: 'success', text: 'Two-factor authentication cleared.');
    }

    // ─── Delete ─────────────────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $user = User::query()->findOrFail($id);
        $this->authorize('deleteAdmin', $user);
        $this->userId = $user->id;
        $this->showDeleteConfirmation = true;
        $this->dispatch('modal-show', name: 'user-delete');
    }

    public function delete(DeleteUser $deleteUser): void
    {
        $user = User::query()->findOrFail($this->requiredUserId());
        $this->authorize('deleteAdmin', $user);
        $deleteUser($user);
        $this->closeDeleteConfirmation();
        Flux::toast(variant: 'success', text: 'User deleted.');
    }

    public function closeDeleteConfirmation(): void
    {
        $this->showDeleteConfirmation = false;
        $this->userId = null;
        $this->dispatch('modal-close', name: 'user-delete');
    }

    // ─── Render ─────────────────────────────────────────────────────────────

    public function render(): View
    {
        abort_unless(auth()->user()?->can('users.view'), 403);

        $term = $this->search !== '' ? '%'.$this->search.'%' : null;
        $statusFilter = in_array($this->statusFilter, ['all', 'active', 'inactive'], true) ? $this->statusFilter : 'active';

        $users = User::query()
            ->with('roles')
            ->when($term !== null, fn (Builder $q) => $q->where(function (Builder $q) use ($term): void {
                $q->where('name', 'ILIKE', $term)->orWhere('email', 'ILIKE', $term);
            }))
            ->when($this->roleFilter !== '', fn (Builder $q) => $q->whereHas('roles', fn (Builder $r) => $r->where('name', $this->roleFilter)))
            ->when($statusFilter !== 'all', fn (Builder $q) => $q->where('is_active', $statusFilter === 'active'))
            ->orderBy('name')
            ->paginate(20);

        $roles = Role::orderBy('name')->pluck('name')->all();

        $selectedUser = null;
        $activityCounts = [];
        $recentAudit = [];

        if (($this->showDetails || $this->showDeleteConfirmation) && $this->userId !== null) {
            $selectedUser = User::query()->with('roles')->find($this->userId);

            if ($selectedUser !== null) {
                $activityCounts = $this->getActivityCounts($selectedUser->id);
                $recentAudit = AdminAuditLog::query()
                    ->where('subject_type', User::class)
                    ->where('subject_id', $selectedUser->id)
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->get();
            }
        }

        return view('livewire.users.index', compact('users', 'roles', 'selectedUser', 'activityCounts', 'recentAudit'));
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function clearFormState(): void
    {
        $this->userId = null;
        $this->name = '';
        $this->email = '';
        $this->roles = [];
        $this->password = '';
        $this->resetValidation();
    }

    private function requiredUserId(): int
    {
        abort_if($this->userId === null, 404);

        return $this->userId;
    }

    /** @return array<string, int> */
    private function getActivityCounts(int $id): array
    {
        return [
            'invoices' => DB::table('invoices')
                ->where(fn ($q) => $q->where('created_by', $id)->orWhere('issued_by', $id)->orWhere('voided_by', $id))
                ->count(),
            'payments' => DB::table('payments')
                ->where(fn ($q) => $q->where('received_by', $id)->orWhere('reversed_by', $id))
                ->count(),
            'stock_movements' => DB::table('stock_movements')
                ->where('user_id', $id)
                ->count(),
            'pos_sessions' => DB::table('pos_sessions')
                ->where(fn ($q) => $q->where('user_id', $id)->orWhere('closed_by', $id))
                ->count(),
        ];
    }
}
