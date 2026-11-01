# Business Workflows

## Money and invoice calculation

All money values are decimal strings, stored as `decimal(14,2)` in transactional tables, and calculated using BCMath through `App\Services\Money\Money`. Do not cast money to PHP floats.

`App\Services\Invoicing\InvoiceCalculator` computes each line extension as quantity times unit price, rounds that extension to two decimal places, subtracts the line discount, then computes and rounds standard VAT for that line. Exempt and zero-rated lines have zero VAT. Document subtotal and discount totals are sums of the rounded line amounts; tax total and grand total are sums of the per-line rounded tax and line totals.

For tax-exclusive prices, VAT is calculated as `net line amount × VAT rate / 100`, and added to the discounted net amount. For tax-inclusive prices, VAT is extracted from the discounted amount as `inclusive amount − inclusive amount / (1 + VAT rate / 100)`; the line total remains the discounted inclusive amount. VAT rate defaults to 18%, and the inclusive/exclusive mode comes from Business Settings.

This explicitly uses **per-line rounding** rather than rounding only the final document tax. Any invoice persistence and rendering must use this calculator so printed totals match recorded totals.

## Document numbering

`DocumentNumberingService` allocates customer (`CUS`), invoice (`INV`), receipt (`RCT`), and payment (`PAY`) identifiers from `document_sequences`. It obtains the next number while holding a row lock within the caller's transaction. Prefix, reset cadence, and digit width are read from the Settings service. Customer creation and invoice issuance call it inside their own transactions. A failed enclosing transaction rolls the sequence update back, preventing consumed numbers for failed creation/issuance.

## Invoicing Lifecycle (Phase 3A)

### Draft creation and updates
Invoices start as drafts via `CreateInvoiceDraft`. Drafts have no assigned document sequence number and are assigned channel `invoice` (walk-in customers are rejected on the `invoice` channel; walk-in sales belong to POS receipts). Line items capture entered quantities, unit conversion to base quantities via `StockService::convertToBase`, pricing, discounts, tax types, and immutable snapshots of product name and SKU. While in draft status, `UpdateInvoiceDraft` re-validates and recalculates lines, and `DeleteInvoiceDraft` deletes the draft and cascades line item removal. Non-draft invoices are immutable and cannot be updated or deleted.

### Issue workflow, FEFO, snapshots, credit checks, and idempotency
`IssueInvoice` executes in a single transaction in strict order:
1. Locks the invoice row.
2. Checks idempotency: if already issued with the identical idempotency key, returns the invoice without reposting; if not in draft status, throws an exception.
3. Re-validates active customer, location, and products.
4. Recalculates line taxes and document totals using `InvoiceCalculator`.
5. Evaluates customer credit exposure via `CreditLimitService` using the invoice's calculated total; blocks issuance if the configured mode is `block` and projected balance exceeds credit limit (customers with unconfigured or zero credit limits are skipped).
6. Allocates the sequential invoice number with `DocumentNumberingService::next('invoice')`.
7. Deducts stock per line through `IssueStock` in consistent `product_id` order. For batch-tracked products, `IssueStock` applies First-Expiry-First-Out (FEFO) order, splitting across batches where necessary and rejecting expired stock.
8. Writes an `InvoiceItemAllocation` record for each batch allocation linking the stock movement.
9. Writes customer snapshot details (`name`, `phone`, `address`, `tin`, `vrn`) and updates status to `issued` with audit fields (`issued_by`, `issued_at`, `idempotency_key`).
If insufficient stock or any validation fails, the entire transaction rolls back: no number is consumed, no stock is moved, and no allocations are persisted.

### Void workflow and inventory returns
`VoidInvoice` transitions an issued invoice to `void` status in a single transaction:
- Requires a non-empty audit reason and `invoices.void` permission.
- Verifies the invoice is issued, not already void, and has no recorded payments (`amount_paid == 0`).
- Calls `ReturnStock`, which reads the saved `InvoiceItemAllocation` records and invokes `StockService::returnSale`, restoring the exact quantities to the exact batches and recording `return_movement_id`.
- Records audit fields (`voided_by`, `voided_at`, `void_reason`).
- Ledger invariant holds: sum of stock movements equals stock levels before issue, after issue, and after void.

## Customer statements

Customer balances are calculated at read time from issued, partially paid, and paid invoices (`total − amount_paid`); draft and void invoices are excluded. The in-app details panel lists recent invoices and payments, while the downloadable PDF statement renders full history.