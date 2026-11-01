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

### 2. Customer Module Suite (`tests/Feature/Customers/CustomerModuleTest.php`)
- Tests customer creation, search, statement generation, CSV import/export, and walk-in customer protection.

### 3. Inventory & Stock Suite (`tests/Feature/Inventory/`)
- Tests CRUD operations for units, categories, locations, products, batch tracking, transfers, adjustments, and receipts.
