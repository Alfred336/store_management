# Changelog

## [Phase 3B] - 2026-11-04

### Added

- **`InvoiceTemplate` enum** (`app/Enums/InvoiceTemplate.php`): five values (`classic`, `modern`, `minimal`, `compact`, `thermal`) with `label()`, `supportedPapers()`, `defaultPaper()`, `isThermal()`, and `default()`.
- **Five Blade templates** (`resources/views/invoices/templates/`): classic, modern, minimal, compact (A5), thermal (receipt). All use DejaVu Sans, tables-only CSS for dompdf, watermark `position:fixed` rotated div, and conditional fiscal block.
- **`InvoiceDocumentData` service** (`app/Services/Invoicing/InvoiceDocumentData.php`): builds the complete view-model (company, header, customer snapshot, lines, totals, fiscal, watermark, notes, terms). Logo embedded as base64 data-URI from a local file path. `amountInWords()` converts TZS (and other currencies) to English words.
- **`RenderInvoiceDocument` action** (`app/Livewire/Actions/Invoicing/RenderInvoiceDocument.php`): authorizes via `invoices.print`; wraps render + `InvoiceDocument` creation in a locked transaction for posted invoices; draft/void invoices render only without DB record; `save` action stores to `invoices/{year}/{slug}.pdf`.
- **`InvoiceDocument` model and migration** (`database/migrations/2026_11_04_000001_create_invoice_documents_table.php`): tracks template, paper, action, is_duplicate, path, size_bytes, sha256, generated_by per render.
- **Fiscal extension point**: `FiscalProvider` interface and `NullFiscalProvider` (reads nullable fiscal columns); bound in `AppServiceProvider`; fiscal columns added to `invoices` via `database/migrations/2026_11_04_000002_add_fiscal_columns_to_invoices_table.php`.
- **`InvoicePdfController`** (`app/Http/Controllers/InvoicePdfController.php`): `render` streams PDF inline or as attachment; `download` streams a saved file from the private disk.
- **Routes**: `GET /invoices/{invoice}/pdf` (`invoices.pdf`) and `GET /invoices/{invoice}/documents/{document}` (`invoices.document.download`) added to the authenticated group in `routes/web.php`.
- **Print panel UI**: template picker, paper picker (filtered to template's `supportedPapers()`), preview iframe, Print/Download/Save buttons, and a saved-documents table with original/duplicate badges in the invoice details flyout.
- **Settings additions**: `invoicing.default_template`, `invoicing.default_paper`, `pos.receipt_width`, `business.bank_details`, `business.footer_terms` — added to `Settings\Index`, `UpdateBusinessSettings`, and the settings view.
- **`InvoiceDocumentTest` suite** (`tests/Feature/Invoicing/InvoiceDocumentTest.php`): 27 tests covering template/paper rendering, customer snapshot immutability, original/duplicate logic, draft/void watermarks, save-to-disk, auth, input validation, logo embedding, remote-access config, amount-in-words, and fiscal data.

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
