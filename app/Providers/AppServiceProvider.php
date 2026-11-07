<?php

namespace App\Providers;

use App\Http\Middleware\EnforceActiveUser;
use App\Http\Middleware\EnforceMustChangePassword;
use App\Listeners\RecordLastLogin;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LocationPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SettingPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use App\Services\Fiscal\FiscalProvider;
use App\Services\Fiscal\NullFiscalProvider;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

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

        // Apply auth-enforcement middleware to Livewire wire-update calls so that
        // deactivations and forced password changes take effect even mid-session.
        // Livewire uses the signed snapshot memo path for the route check, so the
        // exemptions in each middleware work correctly without relying on Referer.
        Livewire::addPersistentMiddleware([
            EnforceMustChangePassword::class,
            EnforceActiveUser::class,
        ]);

        Event::listen(Login::class, RecordLastLogin::class);

        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
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
