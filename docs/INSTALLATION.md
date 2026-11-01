# Installation Requirements

## Supported platform

- PHP 8.3 or newer.
- PostgreSQL 14 or newer. PostgreSQL 14 is the minimum supported project baseline and the version configured for CI. Application schema and queries intentionally rely on PostgreSQL features such as partial indexes, `ILIKE`, row-level locks, and check constraints.
- Required PHP extensions: `pdo_pgsql`, `bcmath`, `mbstring`, `zip`, and `intl`.
- `gd` or `imagick` for image handling and embedded images in PDF output. GD is enabled in the CI environment.
- Node.js 22 or newer and npm to build Vite assets.
- SQLite is not supported.

## Environment

Set `DB_CONNECTION=pgsql` and configure `DB_HOST`, `DB_PORT` (normally `5432`), `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. The [.env.example](../.env.example) file provides local PostgreSQL defaults/placeholders. Do not change the test database name from `store_management_test` in `phpunit.xml`.

The one-time first-run route creates the initial administrator and stores company profile details in the Settings table. Once a user exists, setup is blocked.

## Tests and migrations

Run the feature suite with `php artisan test`. The test configuration forces PostgreSQL `store_management_test`; `Tests\TestCase` verifies the database name before `RefreshDatabase` can run. Never run `migrate:fresh` against the real `store_management` database. Apply deployment migrations to the intended database only after reviewing the deployment plan.