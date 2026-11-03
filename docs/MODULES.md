# Application Modules

## 1. Authentication & Authorization
- **Location**: `app/Livewire/Actions/Authorization`, `app/Policies`, `app/Providers/AppServiceProvider.php`
- **Roles**: Admin, Storekeeper, Cashier, Viewer.
- **Permissions**: Resource-based permissions (`<resource>.view`, `<resource>.create`, `<resource>.update`, `<resource>.delete`, `<resource>.issue`, `<resource>.void`, `<resource>.print`).
- **Policy enforcement**: All models enforce authorization via dedicated Policies (`InvoicePolicy`, `CustomerPolicy`, etc.).

## 2. Inventory & Stock Management
- **Location**: `app/Services/StockService.php`, `app/Livewire/Actions/Inventory`
- **Core Operations**:
  - `receive()`: Receives stock for products, locations, and optional batches.
  - `issue()`: Issues stock for sale, transfer, or damage.
  - `adjust()`: Reason-required stock adjustment.
  - `transfer()`: Multi-location stock transfers with deadlock-safe ordering.
  - `returnSale()`: Reverses a sale movement (used on void).
  - `convertToBase()`: Converts quantities between alternate units and base units.
- **Actions**:
  - `IssueStock`: First-Expiry-First-Out (FEFO) batch allocation; rejects expired batches; splits across batches when necessary.
  - `ReturnStock`: Restores exact quantities to original batches using stored allocation records.

## 3. Customer Management
- **Location**: `app/Models/Customer.php`, `app/Livewire/Actions/Customers`, `app/Livewire/Customers`
- **Features**:
  - Customer CRUD with unique transaction-safe code.
  - Protected single walk-in customer record (`is_walk_in = true`).
  - Credit limit configuration and evaluation (`CreditLimitService`).
  - Dynamic statements (`GetCustomerStatement`) computing balance and total spent from real invoices.
  - Deletion guard preventing deletion of customers with invoice or payment records.

## 4. Invoicing (Phase 3A - Business Logic)
- **Location**: `app/Livewire/Actions/Invoicing`, `app/Models/Invoice.php`, `app/Models/InvoiceItem.php`, `app/Models/InvoiceItemAllocation.php`, `app/Policies/InvoicePolicy.php`
- **Actions**:
  - `CreateInvoiceDraft`: Validates items, units, active counterparties, and calculates per-line VAT and document totals in draft status.
  - `UpdateInvoiceDraft`: Atomically replaces items and recalculates totals for drafts only.
  - `DeleteInvoiceDraft`: Deletes draft invoices and cascades line deletions.
  - `IssueInvoice`: Atomically validates, checks credit, reserves sequential document number, issues stock via FEFO, records item allocations, writes customer snapshots, and marks status as `issued`.
  - `VoidInvoice`: Voids issued invoices without payments, returning exact allocations to inventory via `ReturnStock` with a required audit reason.
- **Invariants**:
  - Issued invoices are immutable: non-draft invoices cannot be modified or deleted.
  - Idempotency key guarantees that retry requests return the existing invoice without duplicate stock deductions.
  - Zero stock leakage: inventory ledger movements match stock level balances at all times.

## 5. Invoicing Screen (Phase 3A Part 2)
- **Location**: `app/Livewire/Invoicing/Index.php`, `resources/views/livewire/invoicing/`
- **Features**:
  - Invoice list with search (number/customer name ILIKE), filters (status, date range, customer, location, overdue), sortable columns, and pagination.
  - Overdue badge derived from `Invoice::isOverdue()` (posted status + past due date + non-zero balance).
  - Create/edit draft flyout: searchable customer picker (excludes walk-in and inactive), active location picker, issue/due date with auto-fill from `payment_terms_days`, product search by name/SKU/barcode, line items with unit select/qty/unit price/discount, live totals via `InvoiceCalculator`, per-line non-expired stock hints.
  - Details flyout: customer snapshot (live for drafts, immutable for issued/voided), line items with batch allocations, totals with balance, void reason, and full audit trail.
  - Issue confirmation modal: shows invoice total, `$idempotencyKey` (server-generated, `#[Locked]`), handles `InsufficientStockException` and `ValidationException`, displays `IssueInvoiceResult->creditWarning`.
  - Void confirmation modal: required reason, `ValidationException` error display.
  - Delete confirmation modal: drafts only.
  - Security: `authorize()` in `mount()` and every public action; `#[Locked]` on `$invoiceId` and `$idempotencyKey`; policy-gated action buttons.

## 6. Invoice PDF Generation (Phase 3B)
- **Location**: `app/Enums/InvoiceTemplate.php`, `app/Livewire/Actions/Invoicing/RenderInvoiceDocument.php`, `app/Services/Invoicing/InvoiceDocumentData.php`, `app/Http/Controllers/InvoicePdfController.php`, `app/Models/InvoiceDocument.php`
- **Templates**: `InvoiceTemplate` enum — `classic`, `modern`, `minimal`, `compact` (A4/A5), `thermal` (80mm/58mm). Blade templates live under `resources/views/invoices/templates/`.
- **PDF Engine**: `barryvdh/laravel-dompdf ^3.1` with remote access disabled; DejaVu Sans; logo embedded as base64 data-URI from a local path.
- **Action** `RenderInvoiceDocument`:
  - Authorizes via `invoices.print` policy.
  - For issued (posted) invoices: wraps render + `InvoiceDocument` row creation in a transaction with `lockForUpdate()` on the invoice; the first row per invoice has `is_duplicate = false`, all subsequent have `is_duplicate = true`.
  - For drafts/void invoices: renders with the DRAFT/VOID watermark but persists no record.
  - `save` action stores the file on the private `local` disk at `invoices/{year}/{slug}.pdf` and records `size_bytes` and `sha256`.
- **Fiscal extension**: `FiscalProvider` interface bound to `NullFiscalProvider` in `AppServiceProvider`. Templates show a fiscal block only when `FiscalProvider::getData()` returns non-null.
- **Routes**: `GET /invoices/{invoice}/pdf` (`invoices.pdf`) and `GET /invoices/{invoice}/documents/{document}` (`invoices.document.download`) under the authenticated group.
- **Print panel UI**: Template picker, paper picker (filtered by template's supported papers), preview iframe, Print/Download/Save buttons, and a saved-documents table with original/duplicate badges.

## 7. Settings & Document Sequences

- **Location**: `app/Services/Settings/Settings.php`, `app/Services/Invoicing/DocumentNumberingService.php`
- **Features**:
  - Key-value JSON settings store for company profile, currency, VAT rate, tax inclusion, credit limit enforcement mode, invoice printing defaults (`invoicing.default_template`, `invoicing.default_paper`, `pos.receipt_width`), and document footer (`business.bank_details`, `business.footer_terms`).
  - Atomic sequence allocation per document type (`invoice`, `receipt`, `payment`, `customer`) and year.
