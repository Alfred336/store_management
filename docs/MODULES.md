# Application Modules

## 1. Authentication & Authorization
- **Location**: `app/Livewire/Actions/Authorization`, `app/Policies`, `app/Providers/AppServiceProvider.php`
- **Roles**: Owner/Admin, Admin, Storekeeper, Cashier, Accountant, Viewer.
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

## 5. Settings & Document Sequences
- **Location**: `app/Services/Settings/Settings.php`, `app/Services/Invoicing/DocumentNumberingService.php`
- **Features**:
  - Key-value JSON settings store for company profile, currency, VAT rate, tax inclusion, credit limit enforcement mode.
  - Atomic sequence allocation per document type (`invoice`, `receipt`, `payment`, `customer`) and year.
