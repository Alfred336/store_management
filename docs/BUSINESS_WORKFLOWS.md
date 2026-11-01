# Business Workflows

## Money and invoice calculation

All money values are decimal strings, stored as `decimal(14,2)` in future transactional tables, and calculated using BCMath through `App\Services\Money\Money`. Do not cast money to PHP floats.

`App\Services\Invoicing\InvoiceCalculator` computes each line extension as quantity times unit price, rounds that extension to two decimal places, subtracts the line discount, then computes and rounds standard VAT for that line. Exempt and zero-rated lines have zero VAT. Document subtotal and discount totals are sums of the rounded line amounts; tax total and grand total are sums of the per-line rounded tax and line totals.

For tax-exclusive prices, VAT is calculated as `net line amount × VAT rate / 100`, and added to the discounted net amount. For tax-inclusive prices, VAT is extracted from the discounted amount as `inclusive amount − inclusive amount / (1 + VAT rate / 100)`; the line total remains the discounted inclusive amount. VAT rate defaults to 18%, and the inclusive/exclusive mode comes from Business Settings.

This explicitly uses **per-line rounding** rather than rounding only the final document tax. Any future invoice persistence and rendering must use this calculator so printed totals match recorded totals.

## Document numbering

`DocumentNumberingService` allocates customer (`CUS`), invoice (`INV`), receipt (`RCT`), and payment (`PAY`) identifiers from `document_sequences`. It obtains the next number while holding a row lock within the caller's transaction. Prefix, reset cadence, and digit width are read from the Settings service. Customer creation calls it inside its own transaction and `customers.code` has a unique constraint. A failed enclosing transaction rolls the sequence update back, preventing consumed numbers for failed creation/issuance.

## Customer statements

Customer balances are calculated at read time from issued/partially-paid/paid invoice totals minus `amount_paid`; the customer row does not store a balance. The in-app details panel lists up to ten most recent invoices and payments, while the downloadable PDF includes all available invoice and payment history. Until those schemas exist, both views return empty histories and a `0.00` balance.