<?php

namespace App\Livewire\Actions\Users;

use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\Authorization\AdminAccountProtection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Permanently delete a user account.
 *
 * Refused when the user has any activity (invoices created/issued/voided,
 * payments, stock movements, POS sessions).  Suggest deactivation instead.
 */
class DeleteUser
{
    public function __construct(private readonly AdminAccountProtection $protection) {}

    public function __invoke(User $target): void
    {
        Gate::authorize('deleteAdmin', $target);

        DB::transaction(function () use ($target): void {
            $lockedAdminCount = $this->protection->lockAdmins();
            $this->protection->assertCanRemove($target, $lockedAdminCount);

            // Refuse if the user has an open POS session.
            $openSession = DB::table('pos_sessions')
                ->where('user_id', $target->id)
                ->where('status', 'open')
                ->first();

            if ($openSession !== null) {
                throw ValidationException::withMessages([
                    'account' => 'This user has an open POS session. Close it before deleting.',
                ]);
            }

            $this->assertNoActivity($target);

            AdminAuditLog::record('user.deleted', User::class, $target->id, [
                'name' => $target->name,
                'email' => $target->email,
            ]);

            $target->deleteSessions();
            $target->delete();
        });
    }

    private function assertNoActivity(User $target): void
    {
        $id = $target->id;

        $invoiceActivity = DB::table('invoices')
            ->where(function ($q) use ($id): void {
                $q->where('created_by', $id)
                    ->orWhere('issued_by', $id)
                    ->orWhere('voided_by', $id);
            })
            ->exists();

        if ($invoiceActivity) {
            throw ValidationException::withMessages([
                'account' => 'This user has invoice history and cannot be deleted. Deactivate the account instead.',
            ]);
        }

        $paymentActivity = DB::table('payments')
            ->where(function ($q) use ($id): void {
                $q->where('received_by', $id)
                    ->orWhere('reversed_by', $id);
            })
            ->exists();

        if ($paymentActivity) {
            throw ValidationException::withMessages([
                'account' => 'This user has payment history and cannot be deleted. Deactivate the account instead.',
            ]);
        }

        $stockActivity = DB::table('stock_movements')
            ->where('user_id', $id)
            ->exists();

        if ($stockActivity) {
            throw ValidationException::withMessages([
                'account' => 'This user has stock movement history and cannot be deleted. Deactivate the account instead.',
            ]);
        }

        $posActivity = DB::table('pos_sessions')
            ->where(function ($q) use ($id): void {
                $q->where('user_id', $id)
                    ->orWhere('closed_by', $id);
            })
            ->exists();

        if ($posActivity) {
            throw ValidationException::withMessages([
                'account' => 'This user has POS session history and cannot be deleted. Deactivate the account instead.',
            ]);
        }
    }
}
