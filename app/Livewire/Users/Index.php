<?php

namespace App\Livewire\Users;

use App\Livewire\Actions\Users\AnonymizeUser;
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
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/** Manage user accounts: create, edit, deactivate, reset password, 2FA, delete and anonymize. */
#[Title('Users')]
class Index extends Component
{
    use WithPagination;

    // ─── Filters ────────────────────────────────────────────────────────────

    #[Url(as: 'q')]
    public string $search = '';

    public string $roleFilter = '';

    public string $statusFilter = 'active';

    // ─── Modal state ────────────────────────────────────────────────────────

    public bool $showForm = false;

    public bool $showDetails = false;

    public bool $showDeleteConfirmation = false;

    /** True when the selected user has activity history (anonymize path). */
    public bool $hasActivity = false;

    // ─── Form fields ────────────────────────────────────────────────────────

    #[Locked]
    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    /** @var string[] */
    public array $roles = [];

    // ─── Anonymize email confirmation ────────────────────────────────────────

    public string $confirmEmail = '';

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

    public function clearSearch(): void
    {
        abort_unless(auth()->user()?->can('users.view'), 403);
        $this->search = '';
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

        if ($user->anonymized_at !== null) {
            Flux::toast(variant: 'danger', text: 'Anonymized users cannot be edited.');

            return;
        }

        $this->clearFormState();
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->roles = $user->roles->pluck('name')->all();
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
        ]);

        try {
            $createUser($data['name'], $data['email'], $data['roles']);
        } catch (\Throwable $e) {
            report($e);
            $this->addError('error', 'Could not send the welcome email. Please try again or check your mail settings.');

            return;
        }

        $this->closeForm();
        Flux::toast(variant: 'success', text: 'User created. A welcome email has been sent with their temporary password.');
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

        if ($user->anonymized_at !== null) {
            Flux::toast(variant: 'danger', text: 'Anonymized users cannot be reactivated.');

            return;
        }

        $reactivateUser($user);
        Flux::toast(variant: 'success', text: 'User reactivated.');
    }

    // ─── Reset password ─────────────────────────────────────────────────────

    public function resetPassword(int $id, ResetUserPassword $resetUserPassword): void
    {
        $user = User::query()->findOrFail($id);
        $this->authorize('resetPassword', $user);

        try {
            $resetUserPassword($user);
        } catch (\Throwable $e) {
            report($e);
            Flux::toast(variant: 'danger', text: 'Could not send the password reset email. Please try again or check your mail settings.');

            return;
        }

        Flux::toast(variant: 'success', text: 'Password reset. A new temporary password has been emailed to the user.');
    }

    // ─── Reset 2FA ──────────────────────────────────────────────────────────

    public function resetTwoFactor(int $id, ResetTwoFactor $resetTwoFactor): void
    {
        $user = User::query()->findOrFail($id);
        $this->authorize('resetPassword', $user);
        $resetTwoFactor($user);
        Flux::toast(variant: 'success', text: 'Two-factor authentication cleared.');
    }

    // ─── Delete / Anonymize ─────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $user = User::query()->findOrFail($id);
        $this->authorize('deleteAdmin', $user);

        if ($user->anonymized_at !== null) {
            Flux::toast(variant: 'danger', text: 'This user has already been anonymized.');

            return;
        }

        $this->userId = $user->id;
        $this->hasActivity = $this->userHasActivity($id);
        $this->confirmEmail = '';
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

    public function anonymize(AnonymizeUser $anonymizeUser): void
    {
        $user = User::query()->findOrFail($this->requiredUserId());
        $this->authorize('deleteAdmin', $user);

        if (strtolower(trim($this->confirmEmail)) !== strtolower($user->email)) {
            $this->addError('confirmEmail', 'The email address does not match.');

            return;
        }

        $anonymizeUser($user);
        $this->closeDeleteConfirmation();
        Flux::toast(variant: 'success', text: 'User permanently anonymized. Historical records are preserved.');
    }

    public function closeDeleteConfirmation(): void
    {
        $this->showDeleteConfirmation = false;
        $this->userId = null;
        $this->hasActivity = false;
        $this->confirmEmail = '';
        $this->dispatch('modal-close', name: 'user-delete');
    }

    // ─── Render ─────────────────────────────────────────────────────────────

    public function render(): View
    {
        abort_unless(auth()->user()?->can('users.view'), 403);

        $term = $this->search !== '' ? '%'.$this->search.'%' : null;

        $validStatusFilters = ['all', 'active', 'inactive', 'deleted'];
        $statusFilter = in_array($this->statusFilter, $validStatusFilters, true) ? $this->statusFilter : 'active';

        $users = User::query()
            ->with('roles')
            ->when($term !== null, fn (Builder $q) => $q->where(function (Builder $q) use ($term): void {
                $q->where('name', 'ILIKE', $term)->orWhere('email', 'ILIKE', $term);
            }))
            ->when($this->roleFilter !== '', fn (Builder $q) => $q->whereHas('roles', fn (Builder $r) => $r->where('name', $this->roleFilter)))
            ->when($statusFilter === 'deleted', fn (Builder $q) => $q->whereNotNull('anonymized_at'))
            ->when($statusFilter !== 'deleted', fn (Builder $q) => $q->whereNull('anonymized_at'))
            ->when($statusFilter === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when($statusFilter === 'inactive', fn (Builder $q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(20);

        $availableRoles = Role::orderBy('name')->pluck('name')->all();

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

        return view('livewire.users.index', compact('users', 'availableRoles', 'selectedUser', 'activityCounts', 'recentAudit'));
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function clearFormState(): void
    {
        $this->userId = null;
        $this->name = '';
        $this->email = '';
        $this->roles = [];
        $this->resetValidation();
    }

    private function requiredUserId(): int
    {
        abort_if($this->userId === null, 404);

        return $this->userId;
    }

    private function userHasActivity(int $id): bool
    {
        return array_sum($this->getActivityCounts($id)) > 0;
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
            'audit_logs' => DB::table('admin_audit_logs')
                ->where('actor_id', $id)
                ->count(),
        ];
    }
}
