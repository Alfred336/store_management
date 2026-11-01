# Routing

Each application module route file is explicitly loaded by `bootstrap/app.php` inside Laravel's `web` middleware group. Authenticated application pages apply `auth` and `verified` middleware.

First-run setup is defined in `routes/setup.php`; the web middleware redirects a fresh install to that route until any administrator/user exists. The setup page then becomes unavailable. Business profile values are written into the Settings module's `settings` table.

| Method | URI | Name | Livewire component |
|---|---|---|---|
| GET | `/setup` | `setup.index` | `App\Livewire\Setup\Index` |
| GET | `/dashboard` | `dashboard` | `App\Livewire\Dashboard\Index` |
| GET | `/business-settings` | `settings.index` | `App\Livewire\Settings\Index` |
| GET | `/units` | `units.index` | `App\Livewire\Units\Index` |
| GET | `/categories` | `categories.index` | `App\Livewire\Categories\Index` |
| GET | `/locations` | `locations.index` | `App\Livewire\Locations\Index` |
| GET | `/products` | `products.index` | `App\Livewire\Products\Index` |

Inventory CRUD routes are separated into `routes/units.php`, `routes/categories.php`, `routes/locations.php`, and `routes/products.php`. Create, details, update, and delete interactions are modal/actions on each resource's Index component; there are no separate create/edit/show routes or resource controllers. Settings are managed at `/business-settings`; setup is a one-time bootstrap flow. Every inventory and settings component operation and Action enforces its registered policy.

Inspect current registrations with `php artisan route:list --path=units`, and repeat for `categories`, `locations`, or `products`.
