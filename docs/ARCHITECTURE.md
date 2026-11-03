# Architecture

**Project:** Agribusiness Store Management System  
**Technology:** Laravel 13, Livewire 4, Flux UI Free, Tailwind CSS, PostgreSQL, Spatie Laravel Permission. The application is a desktop-first web UI deployed behind Nginx on Linux; it is not packaged as a native desktop app.

## Current application boundaries

The active inventory CRUD modules are products, categories, units, and locations; the customer module is also implemented. Each CRUD module has one class-based Livewire `Index.php` component with module-scoped views. Existing Fortify and starter-kit settings/auth components remain in place. Suppliers, purchasing, invoices, payments and POS functionality are not scaffolded with placeholder behavior before their phases.

## Request and write flow

- Project routes are registered in commented sections of `routes/web.php`; `bootstrap/app.php` uses Laravel's default web/console/health routing and only adds the setup middleware.
- Livewire components authorize every read and operation with the corresponding model policy.
- Components validate user input, coordinate modal state, and call dedicated Actions.
- Project Actions are under `app/Livewire/Actions/<Module>` and repeat sensitive authorization checks while implementing data integrity rules. `app/Actions/` retains only the original starter-kit Fortify Actions.
- Product plus packaging-unit writes are wrapped in a database transaction.
- Stock balances and ledger entries are changed only by `App\Services\StockService`; inventory CRUD screens never write stock quantities or movements.

## Authorization

The `RoleSeeder` idempotently creates inventory, dashboard, settings, customer, invoice, payment and POS permissions. Four canonical roles are provisioned: `Admin` receives the full permission set; `Storekeeper`, `Cashier`, and `Viewer` receive role-scoped grants. Permissions for modules not yet implemented are provisioned as foundations only. Model policies are explicitly registered in `AppServiceProvider`. Navigation visibility is a convenience only; Livewire operations enforce policies server-side.

## First-run and settings foundation

- A fresh database is routed to `/setup` only while `setup_completed_at` is absent and no users exist. Setup writes the persistent setting with the first administrator and company details in one transaction. Existing databases are backfilled idempotently; deleting the final user does not reopen setup unless the operator explicitly clears the setting with `setup:reopen --force`.
- Company details, VAT defaults, currency and numbering preferences have one source of truth: the `settings` table, accessed through the cached `App\Services\Settings\Settings` service.
- The dashboard displays live inventory KPIs only. Sales and receivables metrics are intentionally not fabricated and will be wired when those modules exist.
- PostgreSQL is the only supported database. PostgreSQL-specific SQL features such as partial indexes, `ILIKE`, and check constraints are intentionally used.
- The positive setup-completed state is cached using Laravel's configured default cache store. Creating/deleting users invalidates it; cache flushes fall back to the persistent setting and users table.
- The last `Admin` account cannot be deleted through the supported account policy and transactional delete Action; the policy also denies deactivation of the final Admin if a deactivation flow is introduced.

## Customers

- Customer CRUD is implemented in one `App\Livewire\Customers\Index` component, with its create/edit/details/delete-confirmation partials under `resources/views/livewire/customers/partials/`.
- Customer codes are allocated by the document numbering service with the `CUS` prefix inside the customer-creation transaction. The database unique constraint is the final integrity guard.
- A single `Walk-in Customer` record is protected by policy/action checks and a PostgreSQL partial unique index. `WalkInCustomerSeeder` and first-run setup both ensure it idempotently.
- Customer statements calculate outstanding invoice balance, total spent and last purchase date, and fetch recent invoices/payments when those tables and required columns are present. They can be downloaded as PDFs. Until those modules are deployed, the statement is valid and empty with a `0.00` balance. No customer balance column is stored.
- Customer CSV/Excel import and CSV/XLSX export are available. Credit limit handling is a reusable service with a Settings option to warn or block; future sale/invoice completion actions must call it before extending customer credit.

## Money, tax and numbering

- Monetary values and operations use decimal strings and BCMath through `App\Services\Money\Money`; binary floating point is not used.
- `App\Services\Invoicing\InvoiceCalculator` calculates line extensions, discounts, VAT and totals using the configured VAT rate and tax-inclusive/exclusive mode. See `docs/BUSINESS_WORKFLOWS.md` for the rounding rule.
- `document_sequences` stores each document type's annual or continuous sequence. `DocumentNumberingService` allocates within the caller's transaction, locks the sequence row, and honors prefix/width settings.

## UI conventions

Use the existing Flux UI components and `x-layouts::app` shell. Keep forms, tables, details, and destructive confirmation markup in each module's `partials/` view directory. Prefer accessible controls, explicit empty states, server-side validation, and confirmation before destructive actions.

## Tests

Feature tests run against PostgreSQL `store_management_test`. The shared `Tests\TestCase` checks the connected database name before `RefreshDatabase` can run. Never point destructive test traits at the real `store_management` database.

## How to add a module

1. Add one class-based Livewire `Index` component at `app/Livewire/<Module>/Index.php` and module views/partials under `resources/views/livewire/<module>/`.
2. Put each business operation in its own Action under `app/Livewire/Actions/<Module>/`; keep reusable rules in services.
3. Add the model and policy, register the policy, and extend `RoleSeeder` permissions/roles idempotently.
4. Add the module endpoints to a clearly marked section in `routes/web.php`; do not add a per-module route file. Keep the starter settings require unchanged.
5. Add module feature tests under `tests/Feature/<Module>/` for permissions, validation, and business invariants.
6. Update database/workflow/routing documentation as appropriate, then run tests, Pint, and PHPStan.
