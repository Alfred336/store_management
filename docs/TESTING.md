# Testing Guide

## Database & Environment
- **Database Engine**: PostgreSQL only (`store_management_test`). No SQLite workarounds.
- **Never Run Migrations or Seeders on Production DB**: The feature test suite automatically runs against `store_management_test` using Laravel's `RefreshDatabase` trait.

## Running Tests
Run all tests via Pest:
```bash
php artisan test
```

Run static analysis:
```bash
./vendor/bin/phpstan analyse
```

Run code formatting and style fixes:
```bash
./vendor/bin/pint
```

## Key Test Suites

### 1. Invoicing Feature Suite (`tests/Feature/Invoicing/InvoiceModuleTest.php`)
- **Draft Creation & Validation**:
  - Computes subtotal, discounts, line VAT, and grand totals with per-line rounding.
  - Multi-unit conversion to base quantity (e.g. box packaging units).
  - Validation failures for empty lines, non-positive quantities, invalid units, inactive products/customers/locations, walk-in customer on channel `invoice`, discount exceeding line subtotal, and due date prior to issue date.
- **Draft Updates & Deletion**:
  - Updating replaces lines and recalculates totals.
  - Deleting cascades item rows.
  - Immutability: direct update or deletion of non-draft invoices is rejected.
- **Invoice Issuance**:
  - Deducts exact stock levels and records `InvoiceItemAllocation` records.
  - Insufficient stock rolls back transaction: no document number consumed, no movements recorded, invoice remains draft.
  - FEFO order across multiple batches with earliest expiry first.
  - Expired batches are excluded from sale.
  - Tax-inclusive and tax-exclusive setting calculations verified.
  - Customer credit-limit evaluation: blocks issuance in `block` mode, allows with warning in `warn` mode.
  - Idempotency: identical idempotency key returns the existing invoice without duplicate stock deductions; different key on an issued invoice is rejected.
  - Document sequence allocation is sequential and rollback-safe.
- **Invoice Voiding**:
  - Reverses stock to exact original batches using recorded allocations.
  - Requires non-empty audit reason and records `voided_by`, `voided_at`, and `void_reason`.
  - Rejects voiding drafts, already-void invoices, or invoices with payment history.
  - Ledger invariant: `SUM(stock_movements.quantity) == SUM(stock_levels.quantity)` holds after issue and after void.
- **Authorization**:
  - Viewer role cannot create, issue, or void invoices.
  - Cashier role can create and issue invoices but cannot void.
  - Policy enforcement via `InvoicePolicy`.
- **Customer Integration**:
  - Customer statement balance includes only `issued`, `partially_paid`, and `paid` invoices; excludes drafts and voids.
  - Customer deletion blocked if any invoice exists.

### 2. Invoicing Screen Suite (`tests/Feature/Invoicing/InvoiceScreenTest.php`)

- **List & Filters**: Invoice list renders for users with `invoices.view`; search by number and customer name; status filter; overdue filter (past-due + non-zero balance).
- **Create/Edit Draft**: Open form, select customer (walk-in excluded), add line item, store draft; edit and update draft.
- **Walk-in Customer Guards**: Walk-in is absent from the customer search results; direct store with walk-in id fails validation.
- **Issue Flow**: Issuing deducts stock via `IssueInvoice`; insufficient stock shows `$issueError` and invoice stays draft; same idempotency key issues only once with no duplicate movements; credit warning shown when customer is over limit in `warn` mode.
- **Void Flow**: Void with a reason succeeds; void without a reason sets `$voidError` and modal stays open.
- **Delete Flow**: Draft can be deleted from the screen.
- **Authorization**: Viewer cannot create, issue, void or delete via direct calls; Cashier can issue but not void; only drafts can be edited or deleted.

### 3. Customer Module Suite (`tests/Feature/Customers/CustomerModuleTest.php`)

- Tests customer creation, search, statement generation, CSV import/export, and walk-in customer protection.

### 4. Invoice Document Suite (`tests/Feature/Invoicing/InvoiceDocumentTest.php`)

- **PDF rendering**: Each `InvoiceTemplate` × supported paper combination renders bytes starting with `%PDF`.
- **Customer snapshot**: View-model uses immutable snapshot fields, not live customer data, after a post-issue customer update.
- **Original/duplicate logic**: First render sets `is_duplicate = false`; all subsequent renders set `is_duplicate = true`; sequential renders produce exactly one original.
- **Draft / void watermarks**: Draft produces DRAFT watermark and no `InvoiceDocument` row; void produces VOID watermark and no row.
- **Save action**: Stores file on the private `local` disk (via `Storage::fake`); verifies `size_bytes` and `sha256` match the returned bytes; path starts with `invoices/{year}/`.
- **Auth**: Download and render routes return 403 for users missing `invoices.print`.
- **Input validation**: Unknown template returns 422; paper unsupported by the template returns 422.
- **Logo**: Embedded as `data:image/png;base64,…` when `business.logo_path` points to a readable file; null when path is empty.
- **Remote access**: `config('dompdf.options.enable_remote')` is `false`.
- **Amount in words**: `1,250,000.00 TZS` → "One million two hundred and fifty thousand Shillings Only"; `18,500.50 TZS` → "Eighteen thousand five hundred Shillings and Fifty Cents"; `0.00 TZS` → "Zero Shillings Only".
- **Fiscal block**: `fiscal` key is `null` when `fiscal_number` is null; returns structured data when fiscal columns are populated.

### 5. Payments Suite (`tests/Feature/Payments/PaymentModuleTest.php`)

- **RecordPayment Action**:
  - Full payment marks invoice `paid`; partial payment marks it `partially_paid`.
  - Single payment split across multiple invoices allocates amounts and updates each.
  - Overpayment against one invoice leaves unallocated credit on the payment.
  - Allocation exceeding invoice balance is rejected; allocation to draft or another customer's invoice is rejected.
  - Idempotent replay (same idempotency key) returns the existing payment without re-posting or double-allocating.
- **AllocateCredit Action**:
  - Applies unallocated credit from an active payment to a new open invoice.
- **ReversePayment Action**:
  - Reversal restores `invoice.amount_paid` and invoice status to their pre-payment values.
  - Partial-payment reversal returns a `partially_paid` invoice to `issued`.
  - Reversing an already-reversed payment throws `ValidationException`.
  - Empty reason string is rejected.
- **VoidInvoice interaction**:
  - Voiding is blocked while the invoice has active payment allocations.
  - Voiding succeeds after the payment is reversed.
- **Customer Balance**:
  - `CustomerBalance::outstanding()` reflects unallocated credit from active payments.
  - Credit limit headroom accounts for net outstanding (open invoices minus unallocated credit).
- **Authorization**:
  - Cashier without `payments.reverse` cannot reverse a payment.
  - Viewer without `payments.create` cannot record a payment.
  - Unauthenticated request to `payments.index` redirects to login.
- **Livewire Screen**: List loads; customer-name filter hides other customers; totals bar sums active payments.
- **Receipt PDF**: Authorized user receives `application/pdf`; user without `payments.view` gets 403.
- **Validation edge cases**: Zero amount, missing `received_at`, invalid method all throw `ValidationException`.

### 6. Customer Balance Net-Balance Suite (in `CustomerModuleTest.php`)

- Overpayment credit (unallocated portion of an active payment) reduces the net outstanding balance below the open invoice sum.
- After a payment is reversed its credit contribution is removed: balance returns to the raw invoice sum.

### 7. Inventory & Stock Suite (`tests/Feature/Inventory/`)

- **CRUD** (`tests/Feature/InventoryCrudTest.php`): units, categories, locations, products (batch tracking, packaging units, SKU uniqueness, soft-delete guard).
- **Inventory Operations Screen** (`tests/Feature/Inventory/InventoryScreenTest.php`):
  - **Authorization**: Viewer can view but cannot receive/adjust/transfer; Storekeeper can open all operation modals; unauthenticated redirected to login.
  - **Receive**: Non-batch product creates stock level and movement; batch product with new batch creates batch record; existing-batch receive adds to that batch; missing batch fails validation; packaging unit conversion multiplies by factor.
  - **Adjust**: Positive/negative quantities update stock level; missing reason fails validation; negative adjustment exceeding stock returns `adjustQuantity` error.
  - **Transfer**: Moves stock between locations; same-location fails validation; insufficient stock returns `transferQuantity` error.
  - **Filters**: Location filter, category filter, and out-of-stock filter restrict rows correctly.
  - **Stock alert scopes**: `outOfStock()` returns only zero-qty products; `lowStock()` returns products at/below reorder level with stock; at-exactly-reorder-level counts as low; zero-qty is out-of-stock, not low-stock; `reorder_level = 0` products are never low-stock.
  - **Ledger**: Movements visible in reverse order; type filter restricts; date-from filter hides older records; ledger invariant `SUM(movements) = SUM(stock_levels)` holds.

### 8. Reports Suite (`tests/Feature/Reports/ReportsModuleTest.php`)

- **Authorization**: Cashier (no `reports.view`) gets 403 on all 6 tested screens; viewer with `reports.view` gets 200; viewer without `reports.export` gets 403 on export actions; admin with `reports.export` gets 200 from exportXlsx.
- **EnsureSystemRoles**: `reports.view` granted to Admin, Storekeeper, Viewer; `reports.export` granted to Admin only; Cashier gets neither.
- **Sales Summary**: Posted invoices (issued/partially_paid/paid) totals correct; drafts and voids excluded; date-range filter hides out-of-range invoices; location filter includes only matching location.
- **VAT Summary**: Correctly separates standard taxable amount, exempt amount, VAT, and gross total from invoice items.
- **Receivables Aging**: Buckets by days overdue (current/31-60/61-90/90+); paid invoices excluded; invoice due exactly on as-of date goes into current bucket.
- **Voided Invoices**: Only void invoices within date range; outside-range voids excluded; issued invoices excluded.
- **Inventory Report**: Stock value = quantity × selling_price computed correctly.
- **PDF endpoint**: 200 + `Content-Type: application/pdf` + `%PDF` prefix for `sales` and `inventory` slugs; 403 for cashier; 404 for unknown slug.

### 9. User Management Suite (`tests/Feature/Users/UserManagementTest.php`)

- **Registration**: `/register` GET and POST return 404 (registration disabled).
- **CreateUser**: Admin creates user with role; `must_change_password = true`; audit log written without password; non-admin cannot assign Admin role; at least one role required; duplicate email rejected.
- **Login guards**: Inactive user POST to `/login` returns session errors and stays unauthenticated; active user logs in.
- **Session invalidation**: Deactivating a user deletes their `sessions` rows immediately.
- **Last Admin protection**: Deactivating or deleting the sole active Admin throws `ValidationException`; a second active Admin allows deactivation of the first.
- **Self-protection**: Admin cannot update, reset password, or delete themselves via admin Actions.
- **Privilege escalation**: Cashier cannot assign a role (Storekeeper) with permissions the Cashier does not hold.
- **ResetUserPassword**: Generates a password, sets `must_change_password = true`, deletes sessions, writes audit log without password in `changes`.
- **ResetTwoFactor**: Clears 2FA columns, deletes sessions, writes audit log.
- **DeleteUser with activity**: Delete refused when user has invoice rows; deleted without activity.
- **Reactivate**: Admin reactivates an inactive user; audit log written.
- **last_login_at**: Recorded when `Illuminate\Auth\Events\Login` is dispatched.
- **Route authorization**: Admin gets 200 on `/admin/users`; Cashier, Storekeeper, Viewer get 403.
- **Audit log cleanliness**: `user.created` log `changes` never contains `password` key or plain text value.

### 10. Email Verification Code Suite (`tests/Feature/Auth/EmailVerificationCodeTest.php`)

- **`EmailVerificationService`**: `sendCode` stores a hashed code, sends mail, invalidates previous pending codes; `verifyCode` returns `true` on correct code and sets `email_verified_at`; returns `false` on wrong code and increments attempts; returns `false` when attempts are exhausted (5 wrong); returns `false` for expired codes; resend rate-limiting enforced; `deletePendingCodes` removes unused codes.
- **`VerifyEmailCode` component**: accessible for unverified user; verified user redirected to dashboard; `must_change_password` user redirected to password-change; correct code verifies email and redirects; wrong code shows generic error; non-digits fail validation; `resend()` sends new code when cooldown elapsed; `resend()` sets cooldown when too soon; `logout()` ends session.
- **`ChangePassword` integration**: unverified email after password change triggers code send and redirect to `verification.notice`; already-verified email redirects to dashboard.
- **Routing**: `password.change` accessible without email verification (no loop); `verification.notice` accessible without email verification; dashboard redirects unverified user to `verification.notice`.
- **Action side-effects**: `ResetUserPassword` deletes pending codes; `DeactivateUser` deletes pending codes.

### 11. POS Suite (`tests/Feature/Pos/PosModuleTest.php`)

- **Session Management**: Cashier opens a session; duplicate open session is blocked; closing computes `expected_cash` and `difference`; manager force-closes another user's session; non-owner without `pos.close_any_session` is blocked.
- **Sale Completion**: `CompletePosSale` issues RCT-numbered invoice; uses `selling_price` as default unit price; records `amount_tendered` and `change_given` on cash payments; aggregates multiple tenders across separate `Payment` rows.
- **Credit Sales**: Requires `pos.credit_sale` permission; `warn` outcome treated as `block` at POS; walk-in customer cannot take a credit sale.
- **Stock**: Insufficient stock throws `InsufficientStockException`.
- **Held Sales**: `HoldSale` saves the cart without touching stock or numbering; `ResumeSale` restores the cart and deletes the held record; `voidHeldSale` deletes the record via Livewire.
- **Authorization**: Route returns 403 for user without `pos.use`.

### 12. Quotations Suite (`tests/Feature/Quotations/`)

#### `QuotationModuleTest.php`

- **Draft creation**: Totals correct (subtotal, discount, VAT, total); packaging-unit `base_quantity` = qty × factor; `revision = 1`; number null; `created_by` set.
- **Edited price kept**: Explicitly provided `unit_price` is stored verbatim on create and update.
- **Prospect-only quotation**: Allowed when `customer_id` is null and `prospect_name` is provided; customer-less draft without prospect_name throws `ValidationException`.
- **Discount above limit**: Exceeds `pos.max_discount_percent` without `quotations.approve_discount` → `AuthorizationException`. With the permission → succeeds.
- **Send**: Assigns a `QUO-…` sequence number, transitions to `sent`, records `sent_by` / `sent_at`, snapshots customer or prospect details. Sending a sent quotation is impossible (policy blocks with `AuthorizationException`).
- **Immutability**: Policy returns `false` for update/delete on non-draft quotations → `AuthorizationException` (not `ValidationException`).
- **Expiry (computed)**: `valid_until` yesterday → `isExpired()` true, `displayStatus()` = `'expired'`. Accept or convert refused via `AuthorizationException` (policy guards).
- **Accept / Reject**: Transitions verified; `accepted_at` / `rejected_at` / `reject_reason` stored.
- **Revise**: Creates revision 2 with `revised_from_id`; original unchanged at revision 1 and status `sent`. Works from rejected and expired quotations. When original `valid_until` is in the past, revision gets `today + validity_days` to satisfy the DB CHECK constraint.
- **Convert**: Creates one `Invoice` draft with channel `invoice`, `quotation_id` set, quoted prices verbatim, customer carried. Second convert refused (`AuthorizationException`). Prospect quotation refuses without a registered customer; succeeds with one.
- **No stock movement**: Stock levels and movement count are unchanged through draft → send → convert steps; only `IssueInvoice` deducts stock.
- **Issue converted invoice**: Deducts stock and runs credit check; insufficient stock throws `InsufficientStockException`.
- **Permissions**: Viewer cannot create; Storekeeper cannot send; Viewer cannot convert — all via `AuthorizationException`.

#### `QuotationDocumentTest.php`

- **Classic template**: Renders `%PDF` bytes and HTML contains "This is not a tax invoice.".
- **All 4 templates × paper**: `classic/A4`, `modern/A4`, `minimal/A4`, `compact/A5` — all render valid PDF and include the not-a-tax-invoice notice.
- **DRAFT watermark**: Draft quotation view-model has `watermark = 'DRAFT'`; template renders "DRAFT".
- **EXPIRED watermark**: Expired sent quotation view-model has `watermark = 'EXPIRED'`.
- **PDF controller**: Returns `200 application/pdf` starting with `%PDF`; rejects `thermal` template with 422; returns 403 without `quotations.print`.
- **Email**: `Mail::fake()` confirms one `QuotationMail` sent with the correct PDF filename as attachment.
- **Envelope subject**: Contains the QUO sequence number (not "Draft") after the quotation is sent.

#### `QuotationScreenTest.php`

- **List**: Renders customer name and status badge; denied without `quotations.view`; search and status filters work.
- **Modal dispatches** (via `assertDispatched`): `openForm` → `modal-show quotation-form`; `closeForm` → `modal-close quotation-form`; `show` → `modal-show quotation-details`; `confirmDelete` → `modal-show quotation-delete`; `openSendConfirmation` → `modal-show quotation-send`; `openEmailPanel` → `modal-show quotation-email`; `openConvertPanel` → `modal-show quotation-convert`.
- **Store**: Creates a draft via the Livewire form; dispatches `modal-close`; empty items array shows validation errors.
- **Delete**: `deleteDraft` removes quotation and dispatches `modal-close quotation-delete`.
- **Send**: `sendQuotation` transitions to sent and dispatches `modal-close quotation-send`.
- **Accept / Reject**: `applyStatus('accept')` sets status `accepted`; `applyStatus('reject')` with reason stores `reject_reason`.
- **Revise**: `revise` creates a new draft with `revised_from_id` pointing to the sent original.
- **Convert**: `convertToInvoice` on a registered-customer quotation sets status to `converted`.

### 13. Roles & Permissions Suite (`tests/Feature/Roles/RolesModuleTest.php`)

- **Catalogue**: 13 module groups present; all permission names unique; every default permission exists in the catalogue.
- **EnsureSystemRoles**: creates all four system roles; Admin holds every catalogue permission; re-running does NOT undo a screen change to Cashier (`customised_at` respected).
- **New permissions**: a new catalogue permission reaches Admin on the next run; reaches uncustomised system roles additively; does NOT reach customised system roles.
- **System role restrictions**: system roles cannot be renamed or deleted (403); Admin role cannot be edited via `SaveRolePermissions` (403).
- **SaveRolePermissions**: removing `payments.create` from Cashier takes effect immediately; `customised_at` is set.
- **Reset to defaults**: restores permissions and clears `customised_at`.
- **Custom role CRUD**: create, rename, delete; delete blocked when users hold the role.
- **Privilege escalation**: actor cannot change permissions of a role they hold; non-admin cannot grant permissions they don't hold; non-admin cannot edit roles that hold `users.*` permissions.
- **Audit log**: `SaveRolePermissions`, `CreateRole`, `DeleteRole` each write an `admin_audit_logs` row with correct `action` and `changes`.
- **Access control**: Viewer gets 403 on the route; Cashier cannot call `CreateRole`.
- **Livewire screen**: all 13 module groups visible; all four system roles visible.
- **Modal dispatches**: `openCreate` → `modal-show create-role`; `openRename` → `modal-show rename-role`; `confirmDelete` → `modal-show delete-role`; `confirmOrSave` on role with users and removed permissions → `modal-show save-confirm`.
- **storeRole**: creates role and dispatches `modal-close create-role`.
