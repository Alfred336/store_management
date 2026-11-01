# Application Setup

## Deployment target

This is a desktop-first web application intended to run behind Nginx on a Linux VPS. It is not a native desktop/Electron application. Build the front end with the existing Vite scripts and configure Laravel's web server, queue, cache, database, and storage according to the deployment environment.

## First administrator

1. Configure the application environment and run the project's deployment migrations against the intended database.
2. On a fresh database, requests are directed to `/setup` until the first user exists.
3. Enter the initial company profile and administrator credentials. The business data is stored in the same `settings` table used by the Business Settings screen. Setup creates only the administrator; it does not seed sample units, locations, products, or transactions.
4. Setup is blocked after any user exists. The administrator receives the `Owner/Admin` role and can continue configuring the application in Business Settings.

## Database and tests

- PostgreSQL is the only supported database. Configure `DB_CONNECTION=pgsql` and the PostgreSQL host, port, database, username, and password in the environment.
- The feature suite forces PostgreSQL `store_management_test` through `phpunit.xml`, and `Tests\TestCase` rejects other database names before destructive test traits run.
- New migrations in this phase are ready for deployment but have only been exercised via the feature suite against `store_management_test`. Do not run `migrate:fresh` against the real `store_management` database.