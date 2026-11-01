# Application Setup

## Deployment target

This is a desktop-first web application intended to run behind Nginx on a Linux VPS. It is not a native desktop/Electron application. Build the front end with the existing Vite scripts and configure Laravel's web server, queue, cache, database, and storage according to the deployment environment.

## First administrator

1. Configure the application environment and run the project's deployment migrations against the intended database.
2. On a fresh database, requests are directed to `/setup` until the first user exists.
3. Enter the initial company profile and administrator credentials. The business data is stored in the same `settings` table used by the Business Settings screen. Setup creates the administrator and ensures the protected Walk-in Customer; it does not seed sample units, locations, products, or transactions.
4. Setup writes a persistent `setup_completed_at` setting in the same transaction. The wizard is blocked whenever that marker exists, even if all user rows are later deleted. Existing databases with users receive the marker from an idempotent backfill migration. The administrator receives the `Owner/Admin` role and can continue configuring the application in Business Settings.
5. To intentionally clear the completion marker, run `php artisan setup:reopen --force` from an authorized operator shell. The command warns and refuses to act without `--force`; it does not delete or create users. The setup page remains blocked while any user exists, and becomes available only when both the marker is absent and the users table is empty.

The completed setup state is cached using Laravel's configured default cache store. Only the positive completed state is cached; a flushed/missing cache causes the persistent setting and then users table to be checked again. User model creation/deletion invalidates the cache.

## Database and tests

- PostgreSQL is the only supported database. Configure `DB_CONNECTION=pgsql` and the PostgreSQL host, port, database, username, and password in the environment.
- The feature suite forces PostgreSQL `store_management_test` through `phpunit.xml`, and `Tests\TestCase` rejects other database names before destructive test traits run.
- The application prevents self-deletion of the last `Admin` or `Owner/Admin` account. Assign another administrator first; the account deletion UI reports this clearly.
- The additive setup backfill migration writes `setup_completed_at` when it detects existing user rows; it is idempotent, does not use schema-existence guards, and its rollback deliberately preserves this persistent marker. Only `setup:reopen --force` clears it.
- New migrations in this phase are ready for deployment but have only been exercised via the feature suite against `store_management_test`. Do not run `migrate:fresh` against the real `store_management` database.