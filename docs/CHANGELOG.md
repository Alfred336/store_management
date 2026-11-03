# Changelog

## [Phase 3A Part 2] - 2026-11-03

### Added

- **Invoicing Screen** (`app/Livewire/Invoicing/Index.php`, `resources/views/livewire/invoicing/`):
  - Invoice list with ILIKE search, status/date/customer/location/overdue filters, sortable columns, pagination, overdue badges, and balance column.
  - Create/edit draft flyout: searchable customer picker (excludes walk-in/inactive), location picker, issue/due dates with auto-fill from `payment_terms_days`, product search by name/SKU/barcode, line items with unit/qty/price/discount, live totals via `InvoiceCalculator`, per-line non-expired stock hints via LEFT JOIN on `stock_levels`+`batches`.
  - Details flyout: live customer data for drafts, immutable snapshots for issued/voided, batch allocation rows per line item, balance, void reason, and full audit trail (creator/issuer/voider with timestamps).
  - Issue confirmation modal: `$idempotencyKey` generated server-side (`#[Locked]`), catches `InsufficientStockException` and `ValidationException`, displays `IssueInvoiceResult->creditWarning` with acknowledge-and-close option.
  - Void confirmation modal: required reason, shows `ValidationException` messages.
  - Delete confirmation modal: drafts only.
- **Route**: `GET /invoices` → `invoices.index` (`App\Livewire\Invoicing\Index`).
- **Sidebar**: Sales group now visible when user has `invoices.view` OR `customers.view`; Invoices link shown with `invoices.view`.
- **`Invoice::voider()` relationship**: `BelongsTo<User>` via `voided_by` column, used for details panel eager loading.
- **Invoicing Screen Test Suite** (`tests/Feature/Invoicing/InvoiceScreenTest.php`): 19 tests covering list/filters, draft CRUD, walk-in guards, issue/void/delete flows, idempotency, credit warning, and role-based authorization.
- **Customer VRN field**: Added optional `vrn` field (max 50) to the customer form, details panel, CSV/Excel import and export, and `UpdateCustomer`/`CreateCustomer` validation.

### Changed

- `DeleteInvoiceDraft` and `UpdateInvoiceDraft` now throw `ValidationException::withMessages` instead of `InvalidArgumentException` for non-draft invoices (user-facing error, not a programmer error).

## [Phase 3A] - 2026-11-01

### Added
- **Invoicing Actions** (`app/Livewire/Actions/Invoicing`):
  - `CreateInvoiceDraft`: Creates draft invoice with per-line tax/discount calculation, unit conversions, and validation.
  - `UpdateInvoiceDraft`: Replaces items and recalculates totals for drafts; enforces immutability for issued invoices.
  - `DeleteInvoiceDraft`: Deletes draft invoices with cascading item deletion.
  - `IssueInvoice`: Atomically validates, checks credit exposure, assigns document numbers from `DocumentNumberingService`, deducts stock via FEFO using `IssueStock`, persists batch allocations in `invoice_item_allocations`, writes customer snapshots, and marks invoice as issued with audit fields and idempotency key.
  - `VoidInvoice`: Voids issued invoices without payment history, returning exact quantities to original batches via `ReturnStock` and recording void audit fields.
  - `ValidateAndPrepareInvoiceData`: Encapsulates input validation, unit checking, product active checks, and calculator preparation.
- **Invoice Authorization**:
  - `InvoicePolicy`: Enforces permissions `invoices.view`, `invoices.create`, `invoices.update`, `invoices.issue`, `invoices.void`, `invoices.print`, with draft-only constraints on update and delete.
  - Registered `InvoicePolicy` in `AppServiceProvider`.
- **System Roles & Permissions**:
  - Updated `EnsureSystemRoles` to assign `invoices.view`, `invoices.create`, `invoices.update`, `invoices.issue`, and `invoices.print` to Cashier (excluding `invoices.void`).
  - Assigned `invoices.view` and `invoices.print` to Storekeeper.
  - Assigned `invoices.view` to Viewer.
- **Invoicing Test Suite** (`tests/Feature/Invoicing/InvoiceModuleTest.php`):
  - 28 new tests covering draft creation, updates, deletes, validations, FEFO stock deduction, batch allocation sums, insufficient stock rollback, expired batch protection, inclusive/exclusive VAT, credit limit modes, idempotency, numbering rollbacks, void stock restoration, ledger invariants, role authorization, and customer statement/deletion integration.

### Changed
- **Customer Module Cleanup**:
  - Added `invoices()` `HasMany` relationship on `Customer` model.
  - Cleaned up `GetCustomerStatement`: removed `Schema::hasTable('invoices')` and column guards, querying the relationship directly for posted statuses (`issued`, `partially_paid`, `paid`) to compute outstanding balance and total spent. Retained payments guard for Phase 4.
  - Cleaned up `DeleteCustomer`: replaced invoice schema guards with direct `$locked->invoices()->exists()` check, preventing deletion of customers with sales history. Retained payments guard for Phase 4.
  - Updated `tests/Feature/InventoryCrudTest.php` permission count assertions to reflect the new invoice permissions on Storekeeper and Viewer.
  - Fixed outdated customer statement tests in `tests/Feature/Customers/CustomerModuleTest.php` that assumed invoices table did not exist.
