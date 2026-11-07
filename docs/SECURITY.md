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
- **Forced password change**: `EnforceMustChangePassword` middleware redirects `must_change_password` users to `/password/change` (exempt: profile, security, logout, Livewire wire-requests). On successful change, `must_change_password` is cleared.
- **Last active Admin protection**: `AdminAccountProtection::assertCanRemove()` refuses deactivation or deletion of the last active Admin. Counted with `is_active = true` filter.
- **Self-protection**: `UserPolicy` methods `update`, `deactivateAdmin`, `resetPassword`, `deleteAdmin` reject `$actor->is($target)`.
- **Privilege escalation**: `CreateUser` and `UpdateUser` validate that (a) only Admin can assign Admin role, (b) non-admins cannot assign roles whose permissions exceed their own.
- **Sessions invalidated** on: deactivate, role change, password reset, 2FA reset.
- **Temporary passwords** returned from `CreateUser`/`ResetUserPassword` as plain-text array element — shown once in UI, never logged, never stored plain.

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
| User deleted | `admin_audit_logs` before deletion |
