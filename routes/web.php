<?php

use App\Http\Controllers\Customers\CustomerExportController;
use App\Http\Controllers\Customers\CustomerStatementController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\PaymentReceiptController;
use App\Livewire\Categories\Index as CategoriesIndex;
use App\Livewire\Customers\Index as CustomersIndex;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Inventory\Index as InventoryIndex;
use App\Livewire\Inventory\Ledger\Index as InventoryLedgerIndex;
use App\Livewire\Invoicing\Index as InvoicingIndex;
use App\Livewire\Locations\Index as LocationsIndex;
use App\Livewire\Payments\Index as PaymentsIndex;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\Sales\Pos\Index as PosIndex;
use App\Livewire\Settings\Index as BusinessSettingsIndex;
use App\Livewire\Setup\Index as SetupIndex;
use App\Livewire\Units\Index as UnitsIndex;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

// Initial setup: deliberately outside the authenticated route group.
Route::livewire('setup', SetupIndex::class)->name('setup.index');

Route::middleware(['auth', 'verified'])->group(function (): void {
    // Dashboard.
    Route::livewire('dashboard', DashboardIndex::class)->name('dashboard');

    // Inventory: units, categories, locations, and products.
    Route::livewire('units', UnitsIndex::class)->name('units.index');
    Route::livewire('categories', CategoriesIndex::class)->name('categories.index');
    Route::livewire('locations', LocationsIndex::class)->name('locations.index');
    Route::livewire('products', ProductsIndex::class)->name('products.index');

    // Invoicing: invoice list, PDF render, and saved document download.
    Route::livewire('invoices', InvoicingIndex::class)->name('invoices.index');
    Route::get('invoices/{invoice}/pdf', [InvoicePdfController::class, 'render'])->name('invoices.pdf');
    Route::get('invoices/{invoice}/documents/{document}', [InvoicePdfController::class, 'download'])->name('invoices.document.download');

    // Customers: list, export, and statement.
    Route::get('customers/export/{format}', CustomerExportController::class)->name('customers.export');
    Route::get('customers/{customer}/statement', CustomerStatementController::class)->name('customers.statement');
    Route::livewire('customers', CustomersIndex::class)->name('customers.index');

    // Business settings.
    Route::livewire('business-settings', BusinessSettingsIndex::class)->name('settings.index');

    // Payments: list, receive, and receipt.
    Route::livewire('payments', PaymentsIndex::class)->name('payments.index');
    Route::get('payments/{payment}/receipt', PaymentReceiptController::class)->name('payments.receipt');

    // Inventory operations: stock on hand and stock ledger.
    Route::livewire('inventory', InventoryIndex::class)->name('inventory.index');
    Route::livewire('inventory/ledger', InventoryLedgerIndex::class)->name('inventory.ledger');

    // Point of Sale.
    Route::livewire('pos', PosIndex::class)->name('pos.index');
});

require __DIR__.'/settings.php';
