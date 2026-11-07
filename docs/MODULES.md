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

## 7. Payments (Phase 4)

- **Location**: `app/Livewire/Payments/Index.php`, `app/Livewire/Actions/Payments`, `app/Models/Payment.php`, `app/Models/PaymentAllocation.php`, `app/Policies/PaymentPolicy.php`
- **Actions**:
  - `RecordPayment`: Idempotent payment recording with optional allocation to open invoices. Locks customer + invoices in ascending id order.
  - `AllocateCredit`: Applies unallocated credit from an active payment to open invoices.
  - `ReversePayment`: Reverses an active payment (reason required), restoring invoice balances and statuses.
- **Screen** (`app/Livewire/Payments/Index.php`): Payment list with ILIKE search, method/status/date/customer filters, sortable columns, filtered totals bar, pagination. "Receive payment" form with customer picker, open invoices with balances, auto-allocate button. Details modal with allocation list, reversal info, and inline reverse confirmation.
- **Receipt PDF**: `PaymentReceiptController` renders A4 or 80mm thermal receipt via DomPDF; route `GET /payments/{payment}/receipt` (`payments.receipt`).
- **Permissions**: `payments.view` (Admin, Cashier, Viewer), `payments.create` (Admin, Cashier), `payments.reverse` (Admin only).
- **Invariants**:
  - Payments are immutable; use `ReversePayment` to undo.
  - `VoidInvoice` is blocked while active payment allocations exist on the invoice.
  - Customer outstanding balance subtracts unallocated credit from active payments.
  - `DeleteCustomer` is blocked when the customer has any payment records.

## 8. Point of Sale (Phase 5)

- **Location**: `app/Livewire/Sales/Pos/Index.php`, `app/Livewire/Actions/Sales/`, `app/Models/PosSession.php`, `app/Models/PosHeldSale.php`
- **Layout**: Full-screen (no sidebar) using `resources/views/layouts/pos.blade.php`.
- **Actions**:
  - `OpenPosSession`: Creates a session for the authenticated user at a given location; enforces one open session per user via partial unique index.
  - `ClosePosSession`: Closes the session and computes `expected_cash` (opening + cash payments) and `difference`; cashier closes own session, manager can close any.
  - `CompletePosSale`: Creates a POS draft invoice inline (bypasses walk-in guard in `ValidateAndPrepareInvoiceData`), issues it via `IssueInvoice` (uses RCT sequence), and records each tender as a `Payment` allocated to the invoice; `change_given` stored on the cash payment.
  - `HoldSale`: Saves a cart as a `PosHeldSale` row without touching stock, numbering, or payments.
  - `ResumeSale`: Restores the cart from a held sale and deletes the held record.
- **Permissions**: `pos.use`, `pos.open_session`, `pos.close_session`, `pos.close_any_session`, `pos.discount`, `pos.credit_sale`.
- **Credit sales**: Allowed only for named (non-walk-in) customers with `pos.credit_sale`; `warn` outcome is treated as `block` at POS.
- **Products**: `selling_price` field added; used as default `unit_price` in both the invoice form and the POS cart.
- **Invariants**:
  - Only one open session per user (partial unique index).
  - Held sales never touch stock or document sequences.
  - Tenders sum must ≥ invoice total for cash sales; credit sales require the named customer to be within credit limit.

## 9. Inventory Operations Screens

- **Location**: `app/Livewire/Inventory/Index.php`, `app/Livewire/Inventory/Ledger/Index.php`, `app/Livewire/Actions/Inventory/`
- **Screens**:
  - **Stock on Hand** (`/inventory`): Flat per-location/per-batch rows from `stock_levels`; filters by location, category, out-of-stock, low-stock, expiring-within-N-days; ILIKE search by name/SKU/barcode. Alert badges computed from product totals across all locations.
  - **Stock Ledger** (`/inventory/ledger`): Read-only movement history with date-range, product, location, type, and user filters; paginates at 50 per page.
- **Actions** (in `app/Livewire/Actions/Inventory/`):
  - `ReceiveStock`: Authorizes `inventory.receive`. For batch-tracked products, resolves an existing batch or creates a new one (unique per product) in the same DB transaction.
  - `AdjustStock`: Authorizes `inventory.adjust`. Delegates signed-quantity adjustment to `StockService::adjust()`.
  - `TransferStock`: Authorizes `inventory.transfer`. Delegates to `StockService::transfer()`.
- **Stock Alert Rule** (defined as `Product` model scopes):
  - `outOfStock()`: total qty across all locations = 0.
  - `lowStock()`: total qty > 0, `reorder_level > 0`, total qty <= `reorder_level`. PostgreSQL subquery, not column alias, so it is safe in WHERE.
- **Permissions**: `inventory.view` (all roles except unauthenticated), `inventory.receive` / `inventory.adjust` / `inventory.transfer` (Admin + Storekeeper only).

## 10. Reports (Phase 6A)

- **Location**: `app/Livewire/Reports/<Area>/Index.php`, `app/Services/Reports/`, `app/Exports/Reports/`, `app/Http/Controllers/Reports/ReportPdfController.php`
- **Reports**:
  - **Sales Summary** (`/reports/sales`): Aggregated posted invoices (issued / partially_paid / paid) grouped by day (≤31-day range) or month. Columns: period, invoice count, subtotal, discounts, VAT, total.
  - **VAT Summary** (`/reports/vat`): Per-day breakdown of taxable vs exempt amounts and VAT from invoice line items. Excludes drafts and voids.
  - **Receivables Aging** (`/reports/receivables`): Outstanding customer balances bucketed as Current (0–30 d), 31–60 d, 61–90 d, 90+ d measured from an as-of date (default today). Excludes fully paid and void invoices.
  - **Payments by Method** (`/reports/payments`): Count and total amount for completed payments grouped by method over a date range.
  - **POS Sessions** (`/reports/pos-sessions`): Paginated closed sessions with opening/expected/counted cash and variance, filtered by date and location.
  - **Inventory** (`/reports/inventory`): Current stock on hand with estimated stock value (quantity × selling_price), filterable by location and category.
  - **Voided Invoices Audit** (`/reports/voided-invoices`): Paginated voided invoices within a date range, with who voided and why.
- **Exports**: Excel (XLSX) and CSV via `maatwebsite/excel ^4.0` using `FromCollection`, `WithHeadings`, `WithMapping`. All text values sanitized against formula injection (`safeText()`).
- **PDF Export**: `ReportPdfController` renders any of the 7 reports as A4 landscape via DomPDF with company header from `Settings` and filter summary on the document.
- **Timezone**: Date boundaries use Africa/Dar_es_Salaam for `voided_at` and POS session `opened_at` date casting; `issue_date` and `received_at` are stored as dates (no TZ conversion needed).
- **Permissions**: `reports.view` (Admin, Storekeeper, Viewer), `reports.export` (Admin only). Authorized in `mount()` and every export action.

## 11. User Management (Phase 7A)

- **Location**: `app/Livewire/Users/Index.php`, `app/Livewire/Actions/Users/`, `app/Models/AdminAuditLog.php`, `app/Policies/UserPolicy.php`, `app/Services/Authorization/AdminAccountProtection.php`
- **Actions** (all in `app/Livewire/Actions/Users/`):
  - `CreateUser`: Creates user with role(s), auto-generates or accepts a plain temporary password, sets `must_change_password = true`, writes audit log. Returns `['user', 'password']` — plain password shown once in UI.
  - `UpdateUser`: Updates name, email, roles. Deletes sessions on role change. Validates privilege escalation.
  - `DeactivateUser`: Locks active admins, checks last-admin rule, checks for open POS session, sets `is_active = false`, deletes sessions, writes audit log.
  - `ReactivateUser`: Sets `is_active = true`, writes audit log.
  - `ResetUserPassword`: Sets new hashed password (auto-generated or given), sets `must_change_password = true`, deletes sessions, writes audit log. Returns `['user', 'password']`.
  - `ResetTwoFactor`: Clears `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, deletes sessions, writes audit log.
  - `DeleteUser`: Checks last-admin rule, refuses if user has any activity (invoices, payments, stock movements, POS sessions) with suggestion to deactivate instead, writes audit log, deletes sessions, deletes user.
- **Screen** (`/admin/users`): search (ILIKE name/email), role filter, active/inactive filter, paginated table with last login and role badges, details panel with activity counts + recent audit log, action buttons per permission.
- **Temporary password**: shown once in `password-reveal` modal after create or reset; never logged or stored plain.
- **Permissions**: `users.view`, `users.create`, `users.update`, `users.deactivate`, `users.reset_password`, `users.delete`, `audit.view` — Admin gets all, other roles none.
- **Middleware**:
  - `EnforceActiveUser`: logs out inactive authenticated users on each request.
  - `EnforceMustChangePassword`: redirects `must_change_password` users to `/password/change`.
- **Login hooks**: `Fortify::authenticateUsing()` blocks inactive users. `RecordLastLogin` listener captures `last_login_at` on `Illuminate\Auth\Events\Login`.
- **Registration disabled**: `Features::registration()` removed from `config/fortify.php`; `/register` returns 404.
- **Audit log** (`admin_audit_logs`): every Action writes `actor_id`, `action`, `subject_type`, `subject_id`, `changes` (JSON, never includes passwords), `ip_address`, `created_at`.
- **Safety rules**: cannot deactivate/delete/remove Admin from LAST active Admin; cannot act on own account (self-protection in `UserPolicy`); only Admin can assign Admin role; non-admin cannot assign roles with permissions they don't hold.

## 12. Settings & Document Sequences

- **Location**: `app/Services/Settings/Settings.php`, `app/Services/Invoicing/DocumentNumberingService.php`
- **Features**:
  - Key-value JSON settings store for company profile, currency, VAT rate, tax inclusion, credit limit enforcement mode, invoice printing defaults (`invoicing.default_template`, `invoicing.default_paper`, `pos.receipt_width`), and document footer (`business.bank_details`, `business.footer_terms`).
  - Atomic sequence allocation per document type (`invoice`, `receipt`, `payment`, `customer`) and year.
