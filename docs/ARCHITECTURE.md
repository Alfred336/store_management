# Architecture

**Project:** Agribusiness Store Management System  
**Technology:** Laravel 13, Livewire 4, Flux UI Free, Tailwind CSS, PostgreSQL, Spatie Laravel Permission. The application is a desktop-first web UI deployed behind Nginx on Linux; it is not packaged as a native desktop app.

## Current application boundaries

The active inventory CRUD modules are products, categories, units, and locations. Each module has exactly one class-based Livewire component at `app/Livewire/<Module>/Index.php`, with module-scoped views under `resources/views/livewire/<module>/`. Existing Fortify and starter-kit settings/auth components remain in place. Modules that have no implemented business requirements (suppliers, customers, purchasing, sales, invoices, and others) are intentionally not scaffolded with placeholder behavior.

## Request and write flow

- Each module's routes are registered from its own file under `routes/` by `bootstrap/app.php`.
- Livewire components authorize every read and operation with the corresponding model policy.
- Components validate user input, coordinate modal state, and call dedicated Actions.
- Actions repeat sensitive authorization checks and implement data integrity rules.
- Product plus packaging-unit writes are wrapped in a database transaction.
- Stock balances and ledger entries are changed only by `App\Services\StockService`; inventory CRUD screens never write stock quantities or movements.

## Authorization

The `RoleSeeder` idempotently creates inventory, dashboard, settings, customer, invoice, payment and POS permissions. `Owner/Admin`, backwards-compatible `Admin`, and `Manager` receive the current full permission set; `Storekeeper`, `Cashier`, `Accountant`, and `Viewer` receive role-scoped grants. Permissions for modules not yet implemented are provisioned as foundations only. Model policies are explicitly registered in `AppServiceProvider`. Navigation visibility is a convenience only; Livewire operations enforce policies server-side.

## First-run and settings foundation

- A fresh database is routed to `/setup` until its first administrator exists. Setup creates only that user and writes any entered company details into the same `settings` table used by the Settings module. Setup is blocked when any user exists.
- Company details, VAT defaults, currency and numbering preferences have one source of truth: the `settings` table, accessed through the cached `App\Services\Settings\Settings` service.
- The dashboard displays live inventory KPIs only. Sales and receivables metrics are intentionally not fabricated and will be wired when those modules exist.
- PostgreSQL is the only supported database. PostgreSQL-specific SQL features such as partial indexes, `ILIKE`, and check constraints are intentionally used.

## Money, tax and numbering

- Monetary values and operations use decimal strings and BCMath through `App\Services\Money\Money`; binary floating point is not used.
- `App\Services\Invoicing\InvoiceCalculator` calculates line extensions, discounts, VAT and totals using the configured VAT rate and tax-inclusive/exclusive mode. See `docs/BUSINESS_WORKFLOWS.md` for the rounding rule.
- `document_sequences` stores each document type's annual or continuous sequence. `DocumentNumberingService` allocates within the caller's transaction, locks the sequence row, and honors prefix/width settings.

## UI conventions

Use the existing Flux UI components and `x-layouts::app` shell. Keep forms, tables, details, and destructive confirmation markup in each module's `partials/` view directory. Prefer accessible controls, explicit empty states, server-side validation, and confirmation before destructive actions.

## Tests

Feature tests run against PostgreSQL `store_management_test`. The shared `Tests\TestCase` checks the connected database name before `RefreshDatabase` can run. Never point destructive test traits at the real `store_management` database.
