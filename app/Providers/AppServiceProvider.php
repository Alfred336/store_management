<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LocationPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SettingPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use App\Services\Fiscal\FiscalProvider;
use App\Services\Fiscal\NullFiscalProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FiscalProvider::class, NullFiscalProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
