# Coding Standards

- Use one class-based Livewire `Index` component per implemented CRUD resource; preserve the existing Livewire starter kit and auth/settings screens.
- Place each module's views under `resources/views/livewire/<module>/` and divide substantial table, form, details, and confirmation markup into `partials/`.
- Authorize reads and mutations in the Livewire component. Actions repeat authorization for writes and own business constraints; sidebar hiding is not authorization.
- Validate all component properties on the server. Use unique rules that ignore the current persisted record, normalize product SKUs to uppercase, and whitelist sortable columns before ordering queries.
- Use Eloquent bindings/scopes for searches; do not concatenate untrusted input into SQL.
- Wrap writes spanning tables in `DB::transaction`. Keep inventory ledger and quantity writes exclusive to `StockService`.
- Use decimal database columns and BCMath for stock quantities; do not perform stock arithmetic with floating-point values.
- Do not provide ordinary deletion for referenced lookup data or products with stock movement history; block the delete and offer deactivation where appropriate.
- Test Livewire actions, validation, authorization, relationships, and business invariants using PostgreSQL `store_management_test` only.
- Run Pint and PHPStan before considering a change complete.
