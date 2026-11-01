# Architecture

**Project:** Agribusiness Store Management System  
**Technology:** Laravel 13, Livewire 4, Flux UI, Tailwind CSS, PostgreSQL, Spatie Laravel Permission

## Current application boundaries

The active inventory CRUD modules are products, categories, units, and locations. Each module has exactly one class-based Livewire component at `app/Livewire/<Module>/Index.php`, with module-scoped views under `resources/views/livewire/<module>/`. Existing Fortify and starter-kit settings/auth components remain in place. Modules that have no implemented business requirements (suppliers, customers, purchasing, sales, invoices, and others) are intentionally not scaffolded with placeholder behavior.

## Request and write flow

- Inventory pages are loaded through `Route::livewire` from `routes/inventory.php`.
- Livewire components authorize every read and operation with the corresponding model policy.
- Components validate user input, coordinate modal state, and call dedicated Actions.
- Actions repeat sensitive authorization checks and implement data integrity rules.
- Product plus packaging-unit writes are wrapped in a database transaction.
- Stock balances and ledger entries are changed only by `App\Services\StockService`; inventory CRUD screens never write stock quantities or movements.

## Authorization

The `RoleSeeder` creates the `*.view`, `*.create`, `*.update`, and `*.delete` permissions for units, categories, locations, and products, then synchronizes them to the Admin, Storekeeper, and Viewer roles. Model policies are explicitly registered in `AppServiceProvider`. Navigation visibility is a convenience only; Livewire operations enforce policies server-side.

## UI conventions

Use the existing Flux UI components and `x-layouts::app` shell. Keep forms, tables, details, and destructive confirmation markup in each module's `partials/` view directory. Prefer accessible controls, explicit empty states, server-side validation, and confirmation before destructive actions.

## Tests

Feature tests run against PostgreSQL `store_management_test`. The shared `Tests\TestCase` checks the connected database name before `RefreshDatabase` can run. Never point destructive test traits at the real `store_management` database.
