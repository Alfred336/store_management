# Database Design

The schema contains the Laravel/auth tables, Spatie Permission tables, inventory tables `units`, `categories`, `locations`, `products`, `product_units`, `batches`, `stock_levels`, and `stock_movements`, plus application `settings` and `document_sequences` tables. No `business_profiles` table is used; company details are stored as keys in `settings`.

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

Existing migrations are not edited to retrofit already-applied schema. Any future schema correction must use a new reversible migration and must not drop or recreate the real store database. PostgreSQL is the only supported database. The new migrations are exercised through the feature suite against `store_management_test` and must be run separately in deployment.
