# Database Design

The PostgreSQL schema contains the Laravel/auth tables, Spatie Permission tables, and inventory tables `units`, `categories`, `locations`, `products`, `product_units`, `batches`, `stock_levels`, and `stock_movements`.

## Inventory relationships

- A product has a required base unit, optional category, optional packaging conversions, zero or more batches, stock levels, and stock movements.
- `product_units.factor` stores the number of base units represented by the alternate unit.
- Stock quantities and movement quantities use `numeric(14,3)`. Movements store signed base-unit quantities.
- `stock_levels` has PostgreSQL partial unique indexes for the unbatched key (`product_id`, `location_id` where `batch_id IS NULL`) and batched key (`product_id`, `location_id`, `batch_id` where `batch_id IS NOT NULL`). It also has a `quantity >= 0` check constraint.
- Stock is adjusted only by `App\Services\StockService`, which updates a locked stock-level row and records its movement in one transaction.
- Products are soft-deleted. Lookup records referenced by products or inventory history are protected from deletion by application Actions; related foreign keys remain database constraints.

Existing migrations are not edited to retrofit already-applied schema. Any future schema correction must use a new reversible migration and must not drop or recreate the real store database.
