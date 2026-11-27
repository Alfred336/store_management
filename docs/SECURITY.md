# Security Reference

## Authentication & Session

- Authentication is handled by Laravel Fortify (starter kit).
- Sessions are stored in the database (`sessions` table).
- Password hashing uses bcrypt (Laravel default).
- Remember-me tokens are rotated on each login.

## Authorization Model

Authorization uses **Spatie Permission** (role/permission guards):

| Permission | Who holds it | What it gates |
|---|---|---|
| `customers.view` | Staff, Cashier, Manager | View customer list and statement |
| `customers.create` | Manager | Create and import customers |
| `customers.update` | Manager | Edit customer profiles |
| `customers.delete` | Manager | Delete customers with no history |
| `invoices.view` | Staff, Cashier, Manager | List and view invoices |
| `invoices.create` | Cashier, Manager | Create draft invoices |
| `invoices.issue` | Cashier, Manager | Issue (post) draft invoices |
| `invoices.void` | Manager | Void issued invoices with reason |
| `invoices.print` | Cashier, Manager | Render and download invoice PDFs |
| `payments.view` | Staff, Cashier, Manager | View payment list and details |
| `payments.create` | Cashier, Manager | Record payments |
| `payments.reverse` | Manager | Reverse active payments with reason |
| `inventory.*` | Manager, Warehouse Staff | Inventory CRUD and stock operations |
| `pos.use` | Cashier, Manager | Access POS screen and perform sales |
| `pos.open_session` | Cashier, Manager | Open a POS session |
| `pos.close_session` | Cashier, Manager | Close own POS session |
| `pos.close_any_session` | Manager | Force-close another user's POS session |
| `pos.discount` | Cashier, Manager | Apply line-item discounts in POS |
| `pos.credit_sale` | Cashier, Manager | Issue a POS invoice on credit (no cash collected) |

Each Livewire action calls `$this->authorize(...)` or `Gate::authorize(...)` as the **first line** of every public method. All record IDs exposed in components are marked `#[Locked]` to prevent client-side tampering.

### Location-Based Access Control

A second authorization axis scopes data by **location** in addition to permissions:

- **`LocationAccess` service** (`app/Services/Access/LocationAccess.php`): Returns `null` (Admin — unrestricted), `[]` (no access), or `int[]` (allowed location IDs). Results are cached per user; flushed when the user–location pivot changes.
- **`ensure(User, locationId)`**: Throws `AuthorizationException` immediately if the user has no access to the specified location. Called inside every POS and payment Action that targets a specific location.
- **`allowedLocationIds(User)`**: Returns the allowed set for use as a query guard (`whereIn / whereRaw('1=0')`).
- **`allowedLocations(User)`**: Returns an Eloquent collection for populating dropdowns — users never see locations they cannot access.

**Covered surfaces (groups 3B, 4, 5):**

| Surface | Mechanism |
|---|---|
| `RecordPayment` / `ReversePayment` | `ensure()` on target location |
| Payments list (Livewire) | `allowedLocationIds` → `whereIn` scope |
| Customer statement | Payments scoped to allowed locations |
| All POS Actions | `ensure()` before every mutation |
| `ClosePosSession` | `ensure()` for `close_any_session`; own session always closable |
| POS sessions list & PDF | `allowedIds` guard + 403 for forged location |
| All 6 report services | `?array $allowedIds` parameter, `whereIn / 1=0` guard |
| All 6 report exports | Same `allowedIds` passed from Livewire component |
| All 6 Livewire report components | `allowedLocations()` dropdown + `allowedIds` to service/export |
| `ReportPdfController` | `ensure()` for specific location + `allowedIds` to all services |
| Dashboard KPIs, charts, lists | `allowedIds` guard on every query; cache key includes allowed-set hash |
| Dashboard location dropdown | `allowedLocations()` — users cannot filter to forbidden locations |

**URL forgery protection**: if a user manually sets `?locationId=X` for a location they cannot access, the `allowedIds` guard on the query returns no data (services) or the controller calls `ensure()` and returns 403 (PDF endpoint). The Dashboard silently ignores a forged location ID.

### Roles & Permissions Screen — Privilege Escalation Rules

Three additional rules enforced by `SaveRolePermissions`:

1. **Own-role lock**: a user cannot modify a role they hold (prevents self-escalation).
2. **Grant ceiling**: a non-admin cannot grant a permission they themselves don't hold.
3. **Sensitive-role guard**: only Admin can edit roles that hold any `users.*` or `roles.*` permission.

The `roles.view/create/update/delete` permissions are granted to Admin only by default. The `roles.view` permission gates the `/admin/roles` route; all actions re-check in `mount()` and every public method.

## Financial Data Integrity

- **Decimal arithmetic only**: all monetary values use PostgreSQL `NUMERIC` columns and PHP BCMath. Floating-point arithmetic is never used for money.
- **Issued invoices are immutable**: once issued, invoice lines, totals, and customer snapshots cannot be edited. Corrections go through `VoidInvoice` + a new draft.
- **Recorded payments are immutable**: once an active payment is recorded, the amount and customer cannot be changed. Corrections go through `ReversePayment` with a mandatory audit reason.
- **Reversal is audited**: `reversed_by`, `reversed_at`, and `reverse_reason` are stored on the payment row.
- **Void is audited**: `voided_by`, `voided_at`, and `void_reason` are stored on the invoice row.
- **Idempotency keys** on `RecordPayment` prevent double-posting if a network retry re-submits the same form.
- **Stock changes only via `StockService`**: `stock_levels` and `stock_movements` are never written to directly outside `app/Services/Inventory/StockService.php`.

## Database

- **PostgreSQL only**: partial indexes, `ILIKE`, `CHECK` constraints, and `restrictOnDelete` FK policies are used throughout. No SQLite workarounds are present.
- **Row locking**: transactional actions lock relevant rows with `lockForUpdate()` in ascending PK order to prevent deadlocks.
- **Cascades are explicit**: FK policies on `payment_allocations` are `CASCADE` (deleting a payment/invoice row deletes its allocations); payment rows themselves use `restrictOnDelete` on customers.

## Input Validation

- All Livewire form submissions pass through dedicated Action classes with explicit validation.
- `customer_id`, `invoice_id`, and `payment_id` exposed to the browser are locked (`#[Locked]`) or validated server-side before use.
- File uploads (CSV import) are validated for MIME type (`text/csv`, `application/vnd.ms-excel`, `text/plain`) and maximum size (10 MB).
- CSV/XLSX exports sanitize formula-leading characters (`=`, `+`, `-`, `@`) with a `'` prefix to prevent CSV injection.

## PDF Security

- DomPDF is configured with `enable_remote = false` to prevent server-side request forgery via embedded URLs in PDF templates.
- Invoice and receipt PDF routes require `invoices.print` / `payments.view` authorization before rendering.

## Walk-in Customer Protection

- The system walk-in customer record (`is_walk_in = true`) cannot be edited, deleted, or duplicated through any path — policy, action validation, and seeder idempotency all enforce this.

## User Management Security

- **Registration disabled**: `Features::registration()` removed from `config/fortify.php`. `/register` returns 404.
- **Inactive users**: `Fortify::authenticateUsing()` throws `ValidationException` before issuing a session. `EnforceActiveUser` middleware terminates active sessions if `is_active` changes to `false` mid-session.
- **Forced password change**: `EnforceMustChangePassword` middleware redirects `must_change_password` users to `/password/change` (exempt: profile, security, logout, Livewire wire-requests). On successful change, `must_change_password` is cleared and `email_verified_at` is set to `now()` if it was null.
- **Onboarding flow** (admin-created users): Admin fills name, email, roles, and password options. Two modes: **generate** (16-char random via `Str::password(16)`, always emailed) or **manual** (admin types a password, optional email). `must_change_password` defaults to `true` but can be unchecked. Mail sent synchronously inside the transaction; failure rolls back user creation. Plaintext password is never logged or stored; audit records `pwd_mode`, `must_change`, `emailed` flags only.
- **Password reset flow**: Admin opens the edit form and ticks "Reset this user's password". Same generate/manual/must-change/email options as create. Guards: cannot reset own password; cannot reset inactive or anonymized users; `password_reset_tokens` for the target are deleted on reset. Sessions invalidated. Mail failure rolls back the reset. After next login the user is forced to change password if `must_change_password = true`.
- **The `password.change` route** sits in an `auth`-only group (no `verified` middleware) to prevent redirect loops.
- **Last active Admin protection**: `AdminAccountProtection::assertCanRemove()` refuses deactivation or deletion of the last active Admin. Counted with `is_active = true` filter.
- **Self-protection**: `UserPolicy` methods `update`, `deactivateAdmin`, `resetPassword`, `deleteAdmin` reject `$actor->is($target)`.
- **Privilege escalation**: `CreateUser` and `UpdateUser` validate that (a) only Admin can assign Admin role, (b) non-admins cannot assign roles whose permissions exceed their own.
- **Sessions invalidated** on: deactivate, role change, password reset, 2FA reset.
- **Temporary passwords** are generated internally, emailed to the user, and never shown on screen or stored in plain text.
- **Permanent deletion (anonymize)**: Users with no activity history are hard-deleted. Users with any activity (invoices, payments, stock movements, POS sessions, audit log rows as actor) are anonymized instead: name set to "Deleted user #id", email to `deleted-<id>@deleted.invalid`, random unusable password, `is_active = false`, 2FA and passkeys cleared, all roles removed, sessions and password-reset tokens deleted, `anonymized_at` timestamp set. The row is kept so historical FK references remain valid. The freed email address can be reused immediately. Anonymized users cannot log in, be reactivated, or be edited. They are hidden from all list filters except "Deleted (anonymized)". Anonymize requires the actor to type the target's email address to confirm, as it cannot be undone. Refused if: actor is the target, target is the last active Admin, or target has an open POS session.

| Permission | Who holds it |
|---|---|
| `users.view` | Admin |
| `users.create` | Admin |
| `users.update` | Admin |
| `users.deactivate` | Admin |
| `users.reset_password` | Admin |
| `users.delete` | Admin |
| `audit.view` | Admin |

## Audit Trail

| Event | Recorded fields |
|---|---|
| Invoice issued | `issued_by`, `issued_at` |
| Invoice voided | `voided_by`, `voided_at`, `void_reason` |
| Payment recorded | `received_by`, `received_at`, `idempotency_key` |
| Payment reversed | `reversed_by`, `reversed_at`, `reverse_reason` |
| User created | `admin_audit_logs`: `actor_id`, `action=user.created`, name/email/roles (no password) |
| User updated | `admin_audit_logs`: old and new name/email/roles |
| User deactivated/reactivated | `admin_audit_logs` |
| Password reset | `admin_audit_logs` (no password stored) |
| 2FA reset | `admin_audit_logs` |
| User deleted (hard) | `admin_audit_logs`: `action=user.deleted`, name/email snapshot before deletion |
| User anonymized | `admin_audit_logs`: `action=user.anonymized`, `original_name`, `original_email` |
