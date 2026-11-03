# Routing

All project routes live in clearly commented sections in `routes/web.php`. `bootstrap/app.php` uses the default Laravel web, console and health route registration, with the setup middleware appended to the web middleware group. Authenticated application pages apply `auth` and `verified` middleware.

First-run setup is registered in the Initial setup section of `routes/web.php`, outside the authenticated route group. The web middleware redirects only when `setup_completed_at` is absent and no user exists. The setup page is blocked when that persistent setting exists, including after user deletion. Setup writes company details and completion state into the Settings table.

| Method | URI | Name | Livewire component |
|---|---|---|---|
| GET | `/setup` | `setup.index` | `App\Livewire\Setup\Index` |
| GET | `/dashboard` | `dashboard` | `App\Livewire\Dashboard\Index` |
| GET | `/business-settings` | `settings.index` | `App\Livewire\Settings\Index` |
| GET | `/units` | `units.index` | `App\Livewire\Units\Index` |
| GET | `/categories` | `categories.index` | `App\Livewire\Categories\Index` |
| GET | `/locations` | `locations.index` | `App\Livewire\Locations\Index` |
| GET | `/products` | `products.index` | `App\Livewire\Products\Index` |
| GET | `/invoices` | `invoices.index` | `App\Livewire\Invoicing\Index` |
| GET | `/customers` | `customers.index` | `App\Livewire\Customers\Index` |
| GET | `/customers/export/{format}` | `customers.export` | Authorized XLSX/CSV download (`format`: `xlsx` or `csv`) |
| GET | `/customers/{customer}/statement` | `customers.statement` | Authorized customer statement PDF |
| GET | `/invoices/{invoice}/pdf` | `invoices.pdf` | Stream/download an invoice PDF (`InvoicePdfController@render`) |
| GET | `/invoices/{invoice}/documents/{document}` | `invoices.document.download` | Download a saved PDF from the private disk (`InvoicePdfController@download`) |

Routes are grouped and commented in `routes/web.php`: setup outside auth, followed by dashboard, inventory, customers, and business settings inside the authenticated group. Create, details, update, and delete interactions are modal/actions on each resource's Index component; there are no separate create/edit/show routes or resource controllers. Every component operation and Action enforces its registered policy.

Inspect current registrations with `php artisan route:list --path=units`, and repeat for `categories`, `locations`, or `products`.
