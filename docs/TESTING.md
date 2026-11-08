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
