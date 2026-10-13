<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CashSessionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', DashboardController::class)->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::resource('customers', CustomerController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    Route::get('/cash-sessions', [CashSessionController::class, 'index'])->name('cash-sessions.index');
    Route::post('/cash-sessions', [CashSessionController::class, 'store'])->name('cash-sessions.store');
    Route::patch('/cash-sessions/{cashSession}/close', [CashSessionController::class, 'close'])->name('cash-sessions.close');
    Route::resource('sales', SaleController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('/sales/{sale}/returns', [SaleController::class, 'returnSale'])->name('sales.returns.store');
    Route::post('/sales/{sale}/payments', [PaymentController::class, 'store'])->name('sales.payments.store');
    Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/{report}/export', [ReportController::class, 'exportDetail'])->name('reports.detail.export');
    Route::get('/reports/{report}', [ReportController::class, 'detail'])->name('reports.detail');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/reorder-report', [InventoryController::class, 'reorderReport'])->name('inventory.reorder-report');
    Route::get('/inventory/{branchStock}/edit', [InventoryController::class, 'edit'])->name('inventory.edit');
    Route::put('/inventory/{branchStock}', [InventoryController::class, 'update'])->name('inventory.update');
});

Route::middleware(['auth', 'super-admin'])->group(function () {
    Route::get('/settings/backup', [SettingsController::class, 'createBackup'])->name('settings.backup');
    Route::get('/settings/backups/{fileName}', [SettingsController::class, 'downloadBackup'])->name('settings.backups.download');
    Route::get('/settings/export/products', [SettingsController::class, 'exportProducts'])->name('settings.products.export');
    Route::post('/settings/import/products', [SettingsController::class, 'importProducts'])->name('settings.products.import');
    Route::post('/settings/reset-operational-data', [SettingsController::class, 'resetOperationalData'])->name('settings.reset-operational-data');

    Route::resource('employees', EmployeeController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::resource('suppliers', SupplierController::class)->except(['show', 'destroy']);
    Route::patch('/suppliers/{supplier}/status', [SupplierController::class, 'toggleStatus'])
        ->name('suppliers.status');

    Route::resource('branches', BranchController::class)->except(['show', 'destroy']);
    Route::patch('/branches/{branch}/status', [BranchController::class, 'toggleStatus'])
        ->name('branches.status');

    Route::resource('categories', CategoryController::class)->except(['show', 'destroy']);
    Route::patch('/categories/{category}/status', [CategoryController::class, 'toggleStatus'])
        ->name('categories.status');

    Route::resource('products', ProductController::class)->except(['show', 'destroy']);
    Route::patch('/products/{product}/status', [ProductController::class, 'toggleStatus'])
        ->name('products.status');

});

require __DIR__.'/auth.php';
