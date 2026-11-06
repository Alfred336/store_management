<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group heading="Inventory" class="grid">
                    @can('inventory.view')
                        <flux:sidebar.item icon="archive-box" :href="route('inventory.index')" :current="request()->routeIs('inventory.index')" wire:navigate>Stock on Hand</flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('inventory.ledger')" :current="request()->routeIs('inventory.ledger')" wire:navigate>Stock Ledger</flux:sidebar.item>
                    @endcan
                    @can('viewAny', App\Models\Product::class)
                        <flux:sidebar.item icon="cube" :href="route('products.index')" :current="request()->routeIs('products.*')" wire:navigate>Products</flux:sidebar.item>
                    @endcan
                    @can('viewAny', App\Models\Category::class)
                        <flux:sidebar.item icon="tag" :href="route('categories.index')" :current="request()->routeIs('categories.*')" wire:navigate>Categories</flux:sidebar.item>
                    @endcan
                    @can('viewAny', App\Models\Unit::class)
                        <flux:sidebar.item icon="scale" :href="route('units.index')" :current="request()->routeIs('units.*')" wire:navigate>Units</flux:sidebar.item>
                    @endcan
                    @can('viewAny', App\Models\Location::class)
                        <flux:sidebar.item icon="map-pin" :href="route('locations.index')" :current="request()->routeIs('locations.*')" wire:navigate>Locations</flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
                @can('reports.view')
                    <flux:sidebar.group heading="Reports" class="grid">
                        <flux:sidebar.item icon="chart-bar" :href="route('reports.sales')" :current="request()->routeIs('reports.sales')" wire:navigate>Sales Summary</flux:sidebar.item>
                        <flux:sidebar.item icon="document-chart-bar" :href="route('reports.vat')" :current="request()->routeIs('reports.vat')" wire:navigate>VAT Summary</flux:sidebar.item>
                        <flux:sidebar.item icon="clock" :href="route('reports.receivables')" :current="request()->routeIs('reports.receivables')" wire:navigate>Receivables Aging</flux:sidebar.item>
                        <flux:sidebar.item icon="banknotes" :href="route('reports.payments')" :current="request()->routeIs('reports.payments')" wire:navigate>Payments by Method</flux:sidebar.item>
                        <flux:sidebar.item icon="computer-desktop" :href="route('reports.pos_sessions')" :current="request()->routeIs('reports.pos_sessions')" wire:navigate>POS Sessions</flux:sidebar.item>
                        <flux:sidebar.item icon="archive-box" :href="route('reports.inventory')" :current="request()->routeIs('reports.inventory')" wire:navigate>Inventory</flux:sidebar.item>
                        <flux:sidebar.item icon="x-circle" :href="route('reports.voided_invoices')" :current="request()->routeIs('reports.voided_invoices')" wire:navigate>Voided Invoices</flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan
                @can('viewAny', App\Models\Setting::class)
                    <flux:sidebar.group heading="Administration" class="grid">
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('settings.index')" :current="request()->routeIs('settings.*')" wire:navigate>Business settings</flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan
                @if(auth()->user()?->can('viewAny', App\Models\Invoice::class) || auth()->user()?->can('viewAny', App\Models\Customer::class) || auth()->user()?->can('viewAny', App\Models\Payment::class) || auth()->user()?->can('pos.use'))
                    <flux:sidebar.group heading="Sales" class="grid">
                        @can('pos.use')
                            <flux:sidebar.item icon="computer-desktop" :href="route('pos.index')" :current="request()->routeIs('pos.*')" wire:navigate>Point of Sale</flux:sidebar.item>
                        @endcan
                        @can('viewAny', App\Models\Invoice::class)
                            <flux:sidebar.item icon="document-text" :href="route('invoices.index')" :current="request()->routeIs('invoices.*')" wire:navigate>Invoices</flux:sidebar.item>
                        @endcan
                        @can('viewAny', App\Models\Payment::class)
                            <flux:sidebar.item icon="banknotes" :href="route('payments.index')" :current="request()->routeIs('payments.*')" wire:navigate>Payments</flux:sidebar.item>
                        @endcan
                        @can('viewAny', App\Models\Customer::class)
                            <flux:sidebar.item icon="users" :href="route('customers.index')" :current="request()->routeIs('customers.*')" wire:navigate>Customers</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                    {{ __('Repository') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                    {{ __('Documentation') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
