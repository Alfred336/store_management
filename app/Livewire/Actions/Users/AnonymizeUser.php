<?php

namespace App\Livewire\Actions\Users;

use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\Authorization\AdminAccountProtection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Permanently anonymize a user account that has activity history.
 *
 * The row is retained so invoices, payments, stock movements, POS sessions,
 * and audit logs keep their foreign-key references. Personal data is wiped:
 * name → "Deleted user #id", email → deleted-<id>@deleted.invalid,
 * credentials cleared, roles removed, sessions invalidated, anonymized_at set.
 *
 * All earlier admin_audit_logs rows whose subject is this user are scrubbed:
 * string values in their decoded changes array that exactly equal the original
 * name (case-sensitive) or the original email (case-insensitive) are replaced
 * with the anonymized sentinel values. Array keys are never modified.
 *
 * The audit log row for this action stores only the subject_id (user id) and
 * actor_id — no PII is written to changes.
 *
 * The freed email address can be reused for a new account immediately.
 *
 * Refused when: actor is the target (self-delete), last active Admin,
 * or target has an open POS session.
 */
class AnonymizeUser
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

            $originalEmail = $target->email;
            $originalName = $target->name;
            $anonName = "Deleted user #{$target->id}";
            $anonEmail = "deleted-{$target->id}@deleted.invalid";

            // Delete password-reset tokens keyed by the original email before changing it.
            DB::table('password_reset_tokens')->where('email', $originalEmail)->delete();

            // Remove all sessions and passkeys.
            $target->deleteSessions();
            DB::table('passkeys')->where('user_id', $target->id)->delete();

            // Revoke all roles.
            $target->syncRoles([]);

            // Overwrite all personally-identifying and authentication data.
            $target->forceFill([
                'name' => $anonName,
                'email' => $anonEmail,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'is_active' => false,
                'must_change_password' => false,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'anonymized_at' => now(),
            ])->save();

            // Scrub PII from previous audit rows about this user.
            // Load each row, walk the decoded changes array recursively, replace
            // only string values that exactly match the original name or email
            // (email compared case-insensitively). Keys are never modified.
            AdminAuditLog::where('subject_type', User::class)
                ->where('subject_id', $target->id)
                ->whereNotNull('changes')
                ->each(function (AdminAuditLog $log) use ($originalName, $originalEmail, $anonName, $anonEmail): void {
                    /** @var array<array-key, mixed> $changes */
                    $changes = $log->changes;
                    $log->changes = $this->scrubChanges($changes, $originalName, $originalEmail, $anonName, $anonEmail);
                    $log->save();
                });

            // Record the action — no PII in changes; subject_id and actor_id are sufficient.
            AdminAuditLog::record('user.anonymized', User::class, $target->id);
        });
    }

    /**
     * Recursively walk a decoded JSON array and replace string values that are
     * an exact match for $originalName or a case-insensitive match for
     * $originalEmail. Array keys are never modified.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function scrubChanges(
        array $data,
        string $originalName,
        string $originalEmail,
        string $anonName,
        string $anonEmail,
    ): array {
        $lowerEmail = strtolower($originalEmail);

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                if ($value === $originalName) {
                    $data[$key] = $anonName;
                } elseif (strtolower($value) === $lowerEmail) {
                    $data[$key] = $anonEmail;
                }
            } elseif (is_array($value)) {
                /** @var array<array-key, mixed> $value */
                $data[$key] = $this->scrubChanges($value, $originalName, $originalEmail, $anonName, $anonEmail);
            }
        }

        return $data;
    }
}
