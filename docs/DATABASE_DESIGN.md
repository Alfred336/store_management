# Database Design

The schema contains the Laravel/auth tables, Spatie Permission tables, inventory tables `units`, `categories`, `locations`, `products`, `product_units`, `batches`, `stock_levels`, and `stock_movements`, plus application `settings`, `document_sequences`, and invoicing tables `invoices`, `invoice_items`, and `invoice_item_allocations`. No `business_profiles` table is used; company details are stored as keys in `settings`.

## Customers

- `customers` stores generated unique code, name, individual/business type, contact fields, TIN, notes, decimal credit limit, payment terms in days, active status, and a protected walk-in marker.
- A PostgreSQL partial unique index allows at most one row with `is_walk_in = true`. The Walk-in Customer is created by a seeder or first-run setup, never by a migration.
- `invoices()` relationship on `Customer` links customer sales history. Customer balance is calculated dynamically from issued/partially-paid/paid invoice totals and `amount_paid`; it is not stored on customers.
- Customers with invoices in any status cannot be deleted (`DeleteCustomer` enforces this rule).
- `numbering.customer_prefix` is inserted by the customer migration only when missing, defaulting to `CUS` while retaining any existing setting.
- `customers.credit_limit_mode` defaults to `warn`; it configures the reusable credit evaluation service and is not duplicated as a customer balance/transaction field.

## Invoicing Schema (Phase 3A)

### `invoices`
- Primary document table storing sales headers for back-office invoices and POS receipts (`channel IN ('invoice', 'pos')`).
- Lifecycle status constraint: `status IN ('draft', 'issued', 'partially_paid', 'paid', 'void')`.
- `number` is nullable unique (PostgreSQL allows multiple NULL values, ensuring drafts never conflict; document numbers are assigned upon issuance).
- Monetary totals (`subtotal`, `discount_total`, `tax_total`, `total`, `amount_paid`) use `numeric(14,2)` with `CHECK (total >= 0 AND amount_paid >= 0 AND amount_paid <= total)`.
- Customer snapshots (`customer_name`, `customer_phone`, `customer_address`, `customer_tin`, `customer_vrn`) preserve historical counterparty information even if the customer profile is updated or deactivated later.
- Idempotency is enforced with a unique `idempotency_key` constraint.
- Audit foreign keys track `created_by`, `issued_by`, `issued_at`, `voided_by`, `voided_at`, and `void_reason`.

### `invoice_items`
- Line items cascade-deleted on draft invoice deletion (`cascadeOnDelete`).
- Snapshots product details (`description`, `sku`, `unit_id`, `tax_type`, `tax_rate`) to ensure invoice immutability.
- Stores entered `quantity` and normalized `base_quantity` in `numeric(14,3)` with check constraint `CHECK (quantity > 0 AND base_quantity > 0)`.
- Stores `unit_price`, `discount_amount`, `line_tax`, and `line_total` in `numeric(14,2)` with non-negative check constraints.

### `invoice_item_allocations`
- Maps line item quantities to inventory sources: records `location_id`, nullable `batch_id`, and `base_quantity` (`numeric(14,3)`).
- When an invoice is issued, one allocation record is created per batch part selected by `IssueStock`, referencing `stock_movement_id`.
- When an invoice is voided, `ReturnStock` reads these exact allocations to return stock to the original batches and records `return_movement_id`.
- Ensures zero inventory leakage and guarantees that FEFO batch distributions are accurately reversed on void.

### `invoice_documents` (Phase 3B)

- Tracks every print, download, or save of an invoice PDF.
- `template_key` (string) and `paper` (string) record what was rendered.
- `action CHECK (action IN ('print', 'download', 'save'))`.
- `is_duplicate` (boolean): the first row per invoice is `false`; all subsequent rows are `true`. Determined inside a transaction with `lockForUpdate()` on the invoice row to prevent two simultaneous renders both claiming to be the original.
- `path` (nullable): populated only for `save` action; stores the file at `invoices/{year}/{slug}.pdf` on the private `local` disk.
- `size_bytes` and `sha256` (nullable): populated together with `path`.
- `generated_by` FK → `users` with `nullOnDelete`; `invoice_id` FK → `invoices` with `restrictOnDelete`.

### Fiscal columns on `invoices` (Phase 3B)

- Nullable columns `fiscal_number`, `fiscal_verification_code`, `fiscal_qr_payload`, `fiscal_status`, `fiscal_submitted_at` added via additive migration.
- Populated by a `FiscalProvider` integration; the default `NullFiscalProvider` reads and returns them as-is.
- Invoice PDF templates show the fiscal block only when `fiscal_number` is non-null.

## Settings and document numbering

- `settings` is a key/value table. Values are JSON-encoded text so booleans and numeric settings retain scalar types; all company profile, VAT, currency and numbering preferences live here.
- `App\Services\Settings\Settings` caches reads and invalidates affected keys after the enclosing transaction commits.
- `document_sequences` has a composite primary key `(type, year)` and a `last_number`; year `0` is used for continuous sequences. Prefix, reset policy and number width are settings.
- First-run setup locks the seeded currency setting row, rejects setup if any user exists, then creates the initial administrator and writes the company fields into the same settings service/table.
- `products` gains nullable unique `barcode` and `tax_type` (`standard`, `exempt`, `zero_rated`) through additive migrations.

## Inventory relationships

- A product has a required base unit, optional category, optional packaging conversions, zero or more batches, stock levels, and stock movements.
- `product_units.factor` stores the number of base units represented by the alternate unit.
- Stock quantities and movement quantities use `numeric(14,3)`. Movements store signed base-unit quantities.
- `stock_levels` has PostgreSQL partial unique indexes for the unbatched key (`product_id`, `location_id` where `batch_id IS NULL`) and batched key (`product_id`, `location_id`, `batch_id` where `batch_id IS NOT NULL`). It also has a `quantity >= 0` check constraint.
- Stock is adjusted only by `App\Services\StockService`, which updates a locked stock-level row and records its movement in one transaction.
- Products are soft-deleted. Lookup records referenced by products or inventory history are protected from deletion by application Actions; related foreign keys remain database constraints.

Existing migrations are not edited to retrofit already-applied schema. Any future schema correction must use a new reversible migration and must not drop or recreate the real store database. PostgreSQL is the only supported database. The migrations are exercised through the feature suite against `store_management_test` and must be run separately in deployment.
