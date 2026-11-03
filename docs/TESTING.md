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

### 5. Inventory & Stock Suite (`tests/Feature/Inventory/`)

- Tests CRUD operations for units, categories, locations, products, batch tracking, transfers, adjustments, and receipts.
