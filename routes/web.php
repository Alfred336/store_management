<?php

use App\Http\Controllers\Customers\CustomerExportController;
use App\Http\Controllers\Customers\CustomerStatementController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\Reports\ReportPdfController;
use App\Livewire\Auth\ChangePassword;
use App\Livewire\Auth\VerifyEmailCode;
use App\Livewire\Categories\Index as CategoriesIndex;
use App\Livewire\Customers\Index as CustomersIndex;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Inventory\Index as InventoryIndex;
use App\Livewire\Inventory\Ledger\Index as InventoryLedgerIndex;
use App\Livewire\Invoicing\Index as InvoicingIndex;
use App\Livewire\Locations\Index as LocationsIndex;
use App\Livewire\Payments\Index as PaymentsIndex;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\Reports\Inventory\Index as ReportInventoryIndex;
use App\Livewire\Reports\Payments\Index as ReportPaymentsIndex;
use App\Livewire\Reports\PosSessions\Index as ReportPosSessionsIndex;
use App\Livewire\Reports\Receivables\Index as ReportReceivablesIndex;
use App\Livewire\Reports\Sales\Index as ReportSalesIndex;
use App\Livewire\Reports\Vat\Index as ReportVatIndex;
use App\Livewire\Reports\VoidedInvoices\Index as ReportVoidedInvoicesIndex;
use App\Livewire\Sales\Pos\Index as PosIndex;
use App\Livewire\Settings\Index as BusinessSettingsIndex;
use App\Livewire\Setup\Index as SetupIndex;
use App\Livewire\Units\Index as UnitsIndex;
use App\Livewire\Users\Index as UsersIndex;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

// Initial setup: deliberately outside the authenticated route group.
Route::livewire('setup', SetupIndex::class)->name('setup.index');

// Routes that require authentication but NOT email verification.
// password.change and email/verify-code must not sit inside the 'verified'
// middleware group — doing so would create an infinite redirect loop for
// admin-created users who have must_change_password=true + email_verified_at=null.
Route::middleware(['auth'])->group(function (): void {
    Route::livewire('password/change', ChangePassword::class)->name('password.change');

    // This route name is the target of Laravel's EnsureEmailIsVerified middleware.
    Route::livewire('email/verify-code', VerifyEmailCode::class)->name('verification.notice');
});

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

    // Administration: user management.
    Route::livewire('admin/users', UsersIndex::class)->name('users.index');

    // Reports: view screens and PDF export.
    Route::livewire('reports/sales', ReportSalesIndex::class)->name('reports.sales');
    Route::livewire('reports/vat', ReportVatIndex::class)->name('reports.vat');
    Route::livewire('reports/receivables', ReportReceivablesIndex::class)->name('reports.receivables');
    Route::livewire('reports/payments', ReportPaymentsIndex::class)->name('reports.payments');
    Route::livewire('reports/pos-sessions', ReportPosSessionsIndex::class)->name('reports.pos_sessions');
    Route::livewire('reports/inventory', ReportInventoryIndex::class)->name('reports.inventory');
    Route::livewire('reports/voided-invoices', ReportVoidedInvoicesIndex::class)->name('reports.voided_invoices');
    Route::get('reports/{report}/pdf', ReportPdfController::class)->name('reports.pdf');
});

require __DIR__.'/settings.php';
