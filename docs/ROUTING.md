# Routing

Inventory routes are explicitly loaded from `routes/inventory.php` by the routing callback in `bootstrap/app.php`. The module file is registered inside Laravel's `web` middleware group and applies `auth` plus `verified` middleware.

| Method | URI | Name | Livewire component |
|---|---|---|---|
| GET | `/units` | `units.index` | `App\Livewire\Units\Index` |
| GET | `/categories` | `categories.index` | `App\Livewire\Categories\Index` |
| GET | `/locations` | `locations.index` | `App\Livewire\Locations\Index` |
| GET | `/products` | `products.index` | `App\Livewire\Products\Index` |

Create, details, update, and delete interactions are modal/actions on each resource's Index component; there are no separate create/edit/show routes or resource controllers. Every component operation and Action re-fetches records as needed and enforces the registered model policy.

Inspect current registrations with `php artisan route:list --path=units`, and repeat for `categories`, `locations`, or `products`.
