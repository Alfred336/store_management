<?php

namespace App\Livewire\Settings;

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use App\Models\User;
use App\Services\Authorization\AdminAccountProtection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class DeleteUserForm extends Component
{
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     * Uses the `delete` policy (self-deletion) — not the admin `deleteAdmin` policy.
     * Last-Admin check runs first so the user gets a clear validation message.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        $userId = Auth::id();

        DB::transaction(function () use ($userId): void {
            /** @var User $user */
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $lockedAdminCount = app(AdminAccountProtection::class)->lockAdmins();
            app(AdminAccountProtection::class)->assertCanRemove($user, $lockedAdminCount);
            Gate::authorize('delete', $user);
            $this->assertNoActivity($user);
            $user->deleteSessions();
            $user->delete();
        });

        $logout();

        $this->redirect('/', navigate: true);
    }

    private function assertNoActivity(User $user): void
    {
        $id = $user->id;
        $message = 'Your account has activity history and cannot be deleted. Ask an administrator to deactivate your account instead.';

        if (DB::table('invoices')->where(fn ($q) => $q->where('created_by', $id)->orWhere('issued_by', $id)->orWhere('voided_by', $id))->exists()) {
            throw ValidationException::withMessages(['account' => $message]);
        }

        if (DB::table('payments')->where(fn ($q) => $q->where('received_by', $id)->orWhere('reversed_by', $id))->exists()) {
            throw ValidationException::withMessages(['account' => $message]);
        }

        if (DB::table('stock_movements')->where('user_id', $id)->exists()) {
            throw ValidationException::withMessages(['account' => $message]);
        }

        if (DB::table('pos_sessions')->where(fn ($q) => $q->where('user_id', $id)->orWhere('closed_by', $id))->exists()) {
            throw ValidationException::withMessages(['account' => $message]);
        }
    }
}
