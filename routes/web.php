<?php

use App\Http\Controllers\LicenseController;
use App\Http\Controllers\AppSearchController;
use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerTransactionController;
use App\Http\Controllers\AccountantDashboardController;
use App\Http\Controllers\BranchDashboardController;
use App\Http\Controllers\CashierDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseBackupController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StockDashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\InvestmentProductAllocationController;
use App\Http\Controllers\InvestmentPurchaseAllocationController;
use App\Http\Controllers\InvestorController;
use App\Http\Controllers\InvestorDashboardController;
use App\Http\Controllers\InvestorProfitEntryController;
use App\Http\Controllers\InvestorTransactionController;
use App\Http\Controllers\InvestorWithdrawalController;
use App\Http\Controllers\ProductBatchController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductPriceHistoryController;
use App\Http\Controllers\ProductSearchController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\SplashController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfitReportController;
/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [SplashController::class, 'index'])->name('splash');

/*
|--------------------------------------------------------------------------
| License activation (public — machine lock)
|--------------------------------------------------------------------------
*/
Route::get('/license', [LicenseController::class, 'show'])->name('license.show');
Route::post('/license', [LicenseController::class, 'activate'])
    ->middleware('throttle:10,1')
    ->name('license.activate');

/*
|--------------------------------------------------------------------------
| Authenticated + Verified Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'lock.records'])->group(function () {

    /* ═══════════════════════════════════════════════════════
     |  DASHBOARD
     ═══════════════════════════════════════════════════════ */
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('module:dashboard')
        ->name('dashboard');

    Route::get('/dashboard/cashier', [CashierDashboardController::class, 'index'])
        ->middleware(['module:dashboard', 'role:cashier,salesperson'])
        ->name('dashboard.cashier');

    Route::get('/dashboard/stock', [StockDashboardController::class, 'index'])
        ->middleware(['module:dashboard', 'role:stock_keeper'])
        ->name('dashboard.stock');

    Route::get('/dashboard/accountant', [AccountantDashboardController::class, 'index'])
        ->middleware(['module:dashboard', 'role:accountant'])
        ->name('dashboard.accountant');

    Route::get('/dashboard/branch', [BranchDashboardController::class, 'index'])
        ->middleware(['module:dashboard', 'role:branch_manager'])
        ->name('dashboard.branch');

    Route::get('/dashboard/investor', [InvestorDashboardController::class, 'index'])
        ->middleware(['module:dashboard', 'role:investor'])
        ->name('dashboard.investor');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');

    /* ═══════════════════════════════════════════════════════
     |  GLOBAL SEARCH
     ═══════════════════════════════════════════════════════ */
    Route::get('/search', [AppSearchController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('search');

    /* ═══════════════════════════════════════════════════════
     |  BRANCHES
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:super_admin', 'module:branches'])
        ->prefix('branches')->name('branches.')->group(function () {

        Route::get('/', [BranchController::class, 'index'])->name('index');
        Route::get('/create', [BranchController::class, 'create'])->name('create');
        Route::post('/', [BranchController::class, 'store'])->name('store');
        Route::post('/{branch}/deactivate', [BranchController::class, 'deactivate'])->name('deactivate');
        Route::post('/{branch}/activate', [BranchController::class, 'activate'])->name('activate');
        Route::get('/{branch}', [BranchController::class, 'show'])->name('show');
        Route::get('/{branch}/edit', [BranchController::class, 'edit'])->name('edit');
        Route::put('/{branch}', [BranchController::class, 'update'])->name('update');
    });

    /* ═══════════════════════════════════════════════════════
     |  USERS
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:super_admin,admin,branch_manager', 'module:users'])
        ->prefix('users')->name('users.')->group(function () {

        Route::get('/', [UserController::class, 'index'])
            ->middleware('permission:users.view')->name('index');

        Route::get('/create', [UserController::class, 'create'])
            ->middleware('permission:users.create')->name('create');

        Route::post('/', [UserController::class, 'store'])
            ->middleware('permission:users.create')->name('store');

        Route::post('/{user}/block', [UserController::class, 'block'])
            ->middleware('permission:users.block')->name('block');

        Route::post('/{user}/unblock', [UserController::class, 'unblock'])
            ->middleware('permission:users.unblock')->name('unblock');

        Route::get('/trash', [UserController::class, 'trash'])
            ->middleware('role:super_admin')->name('trash');

        Route::post('/{id}/restore', [UserController::class, 'restore'])
            ->middleware('role:super_admin')->whereNumber('id')->name('restore');

        Route::delete('/{id}/force-delete', [UserController::class, 'forceDelete'])
            ->middleware('role:super_admin')->whereNumber('id')->name('forceDelete');

        Route::get('/{user}', [UserController::class, 'show'])
            ->middleware('permission:users.view')->name('show');

        Route::get('/{user}/edit', [UserController::class, 'edit'])
            ->middleware('permission:users.update')->name('edit');

        Route::put('/{user}', [UserController::class, 'update'])
            ->middleware('permission:users.update')->name('update');

        Route::delete('/{user}', [UserController::class, 'destroy'])
            ->middleware('role:super_admin')->name('destroy');
    });

    /* ═══════════════════════════════════════════════════════
     |  APP SETTINGS
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin', 'module:settings'])
        ->prefix('app-settings')->name('app-settings.')->group(function () {

        Route::get('/', [AppSettingController::class, 'index'])->name('index');
        Route::get('/company', [AppSettingController::class, 'company'])->name('company');
        Route::put('/company', [AppSettingController::class, 'updateCompany'])->name('updateCompany');
        Route::get('/create', [AppSettingController::class, 'create'])->name('create');
        Route::post('/', [AppSettingController::class, 'store'])->name('store');
        Route::get('/{appSetting}', [AppSettingController::class, 'show'])->name('show');
        Route::get('/{appSetting}/edit', [AppSettingController::class, 'edit'])->name('edit');
        Route::put('/{appSetting}', [AppSettingController::class, 'update'])->name('update');
        Route::delete('/{appSetting}', [AppSettingController::class, 'destroy'])->name('destroy');
    });

    /* ═══════════════════════════════════════════════════════
     |  DATABASE BACKUPS (create / download / restore upload)
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:super_admin,admin', 'module:settings'])
        ->prefix('backups')->name('backups.')->group(function () {
            Route::get('/', [DatabaseBackupController::class, 'index'])->name('index');
            Route::post('/', [DatabaseBackupController::class, 'store'])->name('store');
            Route::get('/{filename}/download', [DatabaseBackupController::class, 'download'])
                ->where('filename', '.*\.sql')
                ->name('download');
            Route::post('/restore', [DatabaseBackupController::class, 'restore'])->name('restore');
            Route::delete('/{filename}', [DatabaseBackupController::class, 'destroy'])
                ->where('filename', '.*\.sql')
                ->name('destroy');
        });

    /* ═══════════════════════════════════════════════════════
     |  CURRENCIES
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:super_admin,admin,accountant', 'module:currencies'])
        ->prefix('currencies')->name('currencies.')->group(function () {

            Route::get('/', [CurrencyController::class, 'index'])
                ->middleware('permission:currencies.view')->name('index');
            Route::get('/create', [CurrencyController::class, 'create'])
                ->middleware('permission:currencies.create')->name('create');
            Route::post('/', [CurrencyController::class, 'store'])
                ->middleware('permission:currencies.create')->name('store');

            Route::get('/trash', [CurrencyController::class, 'trash'])
                ->middleware('permission:currencies.view')->name('trash');
            Route::post('/{id}/restore', [CurrencyController::class, 'restore'])
                ->middleware('permission:currencies.update')->whereNumber('id')->name('restore');
            Route::delete('/{id}/force-delete', [CurrencyController::class, 'forceDelete'])
                ->middleware('permission:currencies.update')->whereNumber('id')->name('forceDelete');
            Route::patch('/{currency}/toggle-status', [CurrencyController::class, 'toggleStatus'])
                ->middleware('permission:currencies.update')->name('toggleStatus');
            Route::patch('/{currency}/set-as-base', [CurrencyController::class, 'setAsBase'])
                ->middleware('permission:currencies.update')->name('setAsBase');

            Route::get('/{currency}', [CurrencyController::class, 'show'])
                ->middleware('permission:currencies.view')->name('show');
            Route::get('/{currency}/edit', [CurrencyController::class, 'edit'])
                ->middleware('permission:currencies.update')->name('edit');
            Route::put('/{currency}', [CurrencyController::class, 'update'])
                ->middleware('permission:currencies.update')->name('update');
            Route::delete('/{currency}', [CurrencyController::class, 'destroy'])
                ->middleware('permission:currencies.update')->name('destroy');
        });

    /* ═══════════════════════════════════════════════════════
     |  BRANDS
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin,branch_manager,stock_keeper', 'module:brands'])
        ->prefix('brands')->name('brands.')->group(function () {

        Route::get('/', [BrandController::class, 'index'])
            ->middleware('permission:brands.view')->name('index');
        Route::get('/create', [BrandController::class, 'create'])
            ->middleware('permission:brands.create')->name('create');
        Route::post('/', [BrandController::class, 'store'])
            ->middleware('permission:brands.create')->name('store');

        Route::get('/trash', [BrandController::class, 'trash'])->name('trash');
        Route::post('/{id}/restore', [BrandController::class, 'restore'])->whereNumber('id')->name('restore');
        Route::delete('/{id}/force-delete', [BrandController::class, 'forceDelete'])->whereNumber('id')->name('forceDelete');
        Route::patch('/{brand}/toggle-status', [BrandController::class, 'toggleStatus'])->name('toggleStatus');

        Route::get('/{brand}', [BrandController::class, 'show'])
            ->middleware('permission:brands.view')->name('show');
        Route::get('/{brand}/edit', [BrandController::class, 'edit'])
            ->middleware('permission:brands.update')->name('edit');
        Route::put('/{brand}', [BrandController::class, 'update'])
            ->middleware('permission:brands.update')->name('update');
        Route::delete('/{brand}', [BrandController::class, 'destroy'])
            ->middleware('permission:brands.delete')->name('destroy');
    });

    /* ═══════════════════════════════════════════════════════
     |  PRODUCTS
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin,branch_manager,stock_keeper,accountant,salesperson', 'module:products'])
        ->prefix('products')->name('products.')->group(function () {

        Route::get('/', [ProductController::class, 'index'])
            ->middleware('permission:products.view')->name('index');
        Route::get('/receive/lookup-barcode', [ProductController::class, 'lookupReceiveBarcode'])
            ->middleware('permission:products.create')->name('receive.lookup');
        Route::get('/receive', [ProductController::class, 'receive'])
            ->middleware('permission:products.create')->name('receive');
        Route::post('/receive', [ProductController::class, 'receiveStore'])
            ->middleware('permission:products.create')->name('receive.store');
        Route::get('/create', [ProductController::class, 'create'])
            ->middleware('permission:products.create')->name('create');
        Route::post('/', [ProductController::class, 'store'])
            ->middleware('permission:products.create')->name('store');

        Route::get('/check-barcode', [ProductController::class, 'checkBarcode'])
            ->middleware('permission:products.view')->name('checkBarcode');

        Route::get('/trash', [ProductController::class, 'trash'])
            ->middleware('permission:products.view')->name('trash');
        Route::get('/search', [ProductSearchController::class, 'search'])->name('search');

        Route::post('/{id}/restore', [ProductController::class, 'restore'])
            ->middleware('permission:products.delete')->whereNumber('id')->name('restore');
        Route::delete('/{id}/force-delete', [ProductController::class, 'forceDelete'])
            ->middleware('permission:products.delete')->whereNumber('id')->name('forceDelete');
        Route::patch('/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->middleware('role:super_admin')->name('toggleStatus');

        Route::get('/{product}', [ProductController::class, 'show'])
            ->middleware('permission:products.view')->name('show');
        Route::get('/{product}/edit', [ProductController::class, 'edit'])
            ->middleware('permission:products.update')->name('edit');
        Route::put('/{product}', [ProductController::class, 'update'])
            ->middleware('permission:products.update')->name('update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])
            ->middleware('permission:products.delete')->name('destroy');
    });

    /* ═══════════════════════════════════════════════════════
     |  SUPPLIERS
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin,branch_manager,stock_keeper', 'module:suppliers'])
        ->prefix('suppliers')->name('suppliers.')->group(function () {

        Route::get('/', [SupplierController::class, 'index'])
            ->middleware('permission:suppliers.view')->name('index');
        Route::get('/create', [SupplierController::class, 'create'])
            ->middleware('permission:suppliers.create')->name('create');
        Route::post('/', [SupplierController::class, 'store'])
            ->middleware('permission:suppliers.create')->name('store');

        Route::get('/transactions', [SupplierController::class, 'transactions'])
            ->middleware('permission:suppliers.view')->name('transactions');
        Route::get('/trash', [SupplierController::class, 'trash'])->name('trash');
        Route::post('/{id}/restore', [SupplierController::class, 'restore'])->whereNumber('id')->name('restore');
        Route::delete('/{id}/force-delete', [SupplierController::class, 'forceDelete'])->whereNumber('id')->name('forceDelete');
        Route::patch('/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus'])->name('toggleStatus');

        Route::get('/{supplier}/payments/create', [SupplierController::class, 'createPayment'])
            ->middleware('permission:suppliers.create')->name('payments.create');
        Route::post('/{supplier}/payments', [SupplierController::class, 'storePayment'])
            ->middleware('permission:suppliers.create')->name('payments.store');
        Route::get('/{supplier}/payments/{payment}/receipt', [SupplierController::class, 'paymentReceipt'])
            ->middleware('permission:suppliers.view')->name('payments.receipt');
        Route::get('/{supplier}/purchases/{product}/receipt', [SupplierController::class, 'purchaseReceipt'])
            ->middleware('permission:suppliers.view')->name('purchases.receipt');
        Route::get('/{supplier}/invoices/{invoice}/receipt', [SupplierController::class, 'invoiceReceipt'])
            ->middleware('permission:suppliers.view')->name('invoices.receipt');
        Route::post('/{supplier}/invoices/{invoice}/bill', [SupplierController::class, 'storeBill'])
            ->middleware('permission:suppliers.create')->name('invoices.bill');

        Route::get('/{supplier}', [SupplierController::class, 'show'])
            ->middleware('permission:suppliers.view')->name('show');
        Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])
            ->middleware('permission:suppliers.update')->name('edit');
        Route::put('/{supplier}', [SupplierController::class, 'update'])
            ->middleware('permission:suppliers.update')->name('update');
        Route::delete('/{supplier}', [SupplierController::class, 'destroy'])
            ->middleware('permission:suppliers.delete')->name('destroy');
    });

    /* ═══════════════════════════════════════════════════════
     |  CUSTOMERS
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin,branch_manager,cashier,salesperson', 'module:customers'])
        ->prefix('customers')->name('customers.')->group(function () {

        Route::get('/', [CustomerController::class, 'index'])
            ->middleware('permission:customers.view')->name('index');
        Route::get('/create', [CustomerController::class, 'create'])
            ->middleware('permission:customers.create')->name('create');
        Route::post('/', [CustomerController::class, 'store'])
            ->middleware('permission:customers.create')->name('store');

        Route::get('/trash', [CustomerController::class, 'trash'])->name('trash');
        Route::post('/{id}/restore', [CustomerController::class, 'restore'])->whereNumber('id')->name('restore');
        Route::delete('/{id}/force-delete', [CustomerController::class, 'forceDelete'])->whereNumber('id')->name('forceDelete');
        Route::patch('/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('toggleStatus');

        /* ⭐ Customer Ledger — Per Customer */
        Route::get('/{customer}/transactions', [CustomerTransactionController::class, 'byCustomer'])
            ->name('transactions');

        Route::post('/{customer}/transactions/adjustment', [CustomerTransactionController::class, 'storeAdjustment'])
            ->name('transactions.adjustment');

        Route::post('/{customer}/payments', [CustomerController::class, 'receivePayment'])
            ->middleware('permission:sales.create')
            ->name('payments.store');

        Route::get('/{customer}', [CustomerController::class, 'show'])
            ->middleware('permission:customers.view')->name('show');
        Route::get('/{customer}/edit', [CustomerController::class, 'edit'])
            ->middleware('permission:customers.update')->name('edit');
        Route::put('/{customer}', [CustomerController::class, 'update'])
            ->middleware('permission:customers.update')->name('update');
        Route::delete('/{customer}', [CustomerController::class, 'destroy'])
            ->middleware('permission:customers.delete')->name('destroy');
    });

    /* ═══════════════════════════════════════════════════════
     |  CUSTOMER TRANSACTIONS (Global Ledger)  ⭐
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin,accountant,branch_manager,cashier,salesperson', 'module:customers'])
        ->prefix('customer-transactions')->name('customer-transactions.')->group(function () {

        Route::get('/', [CustomerTransactionController::class, 'index'])->name('index');
        Route::get('/{transaction}', [CustomerTransactionController::class, 'show'])->name('show');
        Route::delete('/{transaction}', [CustomerTransactionController::class, 'destroy'])->name('destroy');
    });

    /* ═══════════════════════════════════════════════════════
     |  RECEIPTS  ⭐ NEW
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin,branch_manager,cashier,salesperson', 'module:sales'])
        ->prefix('receipts')->name('receipts.')->group(function () {

        Route::get('/', [ReceiptController::class, 'index'])->name('index');
        Route::get('/{receipt}', [ReceiptController::class, 'show'])->name('show');
        Route::get('/{receipt}/print', [ReceiptController::class, 'print'])->name('print');
        Route::delete('/{receipt}', [ReceiptController::class, 'destroy'])->name('destroy');
    });

    /* ═══════════════════════════════════════════════════════
     |  INVENTORY
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin,branch_manager,stock_keeper,cashier,salesperson', 'module:inventory'])
        ->prefix('inventory')->name('inventory.')->group(function () {

        Route::get('/', [InventoryController::class, 'index'])
            ->middleware('permission:inventory.view')->name('index');
        Route::get('/low-stock', [InventoryController::class, 'lowStock'])
            ->middleware('permission:inventory.view')->name('lowStock');
        Route::get('/transactions', [InventoryController::class, 'transactions'])
            ->middleware('permission:inventory.view')->name('transactions');

        Route::post('/{inventory}/adjust', [InventoryController::class, 'adjust'])
            ->middleware(['role:admin,branch_manager,stock_keeper', 'permission:inventory.adjust'])
            ->name('adjust');

        Route::get('/{inventory}', [InventoryController::class, 'show'])
            ->middleware('permission:inventory.view')->name('show');
    });

    /* ═══════════════════════════════════════════════════════
     |  STOCK TRANSFERS
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin,branch_manager,stock_keeper', 'module:stock-transfers'])
        ->prefix('stock-transfers')->name('stock-transfers.')->group(function () {

        Route::get('/', [StockTransferController::class, 'index'])
            ->middleware('permission:stock-transfers.view')->name('index');
        Route::get('/create', [StockTransferController::class, 'create'])
            ->middleware('permission:stock-transfers.create')->name('create');
        Route::post('/', [StockTransferController::class, 'store'])
            ->middleware('permission:stock-transfers.create')->name('store');

        Route::get('/trash', [StockTransferController::class, 'trash'])->name('trash');
        Route::post('/{id}/restore', [StockTransferController::class, 'restore'])->whereNumber('id')->name('restore');
        Route::delete('/{id}/force-delete', [StockTransferController::class, 'forceDelete'])->whereNumber('id')->name('forceDelete');

        Route::patch('/{stockTransfer}/mark-as-completed', [StockTransferController::class, 'markAsCompleted'])->name('markAsCompleted');
        Route::patch('/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])->name('cancel');

        Route::get('/{stockTransfer}', [StockTransferController::class, 'show'])
            ->middleware('permission:stock-transfers.view')->name('show');
        Route::get('/{stockTransfer}/edit', [StockTransferController::class, 'edit'])
            ->middleware('permission:stock-transfers.update')->name('edit');
        Route::put('/{stockTransfer}', [StockTransferController::class, 'update'])
            ->middleware('permission:stock-transfers.update')->name('update');
        Route::delete('/{stockTransfer}', [StockTransferController::class, 'destroy'])
            ->middleware('permission:stock-transfers.delete')->name('destroy');
    });

    /* ═══════════════════════════════════════════════════════
     |  SALES
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin,branch_manager,cashier,salesperson', 'module:sales'])
        ->prefix('sales')->name('sales.')->group(function () {

        Route::get('/', [SaleController::class, 'index'])
            ->middleware('permission:sales.view')->name('index');
        Route::get('/create', [SaleController::class, 'create'])
            ->middleware('permission:sales.create')->name('create');
        Route::post('/', [SaleController::class, 'store'])
            ->middleware('permission:sales.create')->name('store');

        Route::get('/trash', [SaleController::class, 'trash'])
            ->middleware('role:admin,branch_manager')->name('trash');

        Route::get('/lookup-barcode', [SaleController::class, 'lookupBarcode'])
            ->middleware('permission:sales.create')->name('lookupBarcode');
Route::get('/report', [SaleController::class, 'report'])->name('sales.report');
        Route::post('/preview-cost', [SaleController::class, 'previewCost'])
            ->middleware('permission:sales.create')->name('previewCost');

        Route::post('/{id}/restore', [SaleController::class, 'restore'])
            ->middleware('role:admin,branch_manager')->whereNumber('id')->name('restore');

        Route::delete('/{id}/force-delete', [SaleController::class, 'forceDelete'])
            ->middleware('role:admin,branch_manager')->whereNumber('id')->name('forceDelete');

        Route::post('/{sale}/completed', [SaleController::class, 'markAsCompleted'])
            ->middleware('permission:sales.update')->name('markAsCompleted');

        Route::post('/{sale}/payments', [SaleController::class, 'addPayment'])
            ->middleware('permission:sales.create')->name('addPayment');

        Route::post('/{sale}/print-receipt', [SaleController::class, 'printReceipt'])
            ->name('printReceipt');

        Route::get('/{sale}/receipt', [SaleController::class, 'receipt'])
            ->name('receipt');

        Route::get('/{sale}', [SaleController::class, 'show'])
            ->middleware('permission:sales.view')->name('show');
        Route::get('/{sale}/edit', [SaleController::class, 'edit'])
            ->middleware('permission:sales.update')->name('edit');
        Route::put('/{sale}', [SaleController::class, 'update'])
            ->middleware('permission:sales.update')->name('update');
        Route::delete('/{sale}', [SaleController::class, 'destroy'])
            ->middleware('permission:sales.delete')->name('destroy');
    });

    /* ═══════════════════════════════════════════════════════
     |  SALE RETURNS
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin,branch_manager,cashier,salesperson', 'module:sale-returns'])
        ->prefix('sale-returns')->name('sale-returns.')->group(function () {

        Route::get('/', [SaleReturnController::class, 'index'])
            ->middleware('permission:sale-returns.view')->name('index');
        Route::get('/create', [SaleReturnController::class, 'create'])
            ->middleware('permission:sale-returns.create')->name('create');
        Route::post('/', [SaleReturnController::class, 'store'])
            ->middleware('permission:sale-returns.create')->name('store');

        Route::get('/trash', [SaleReturnController::class, 'trash'])
            ->middleware('role:admin,branch_manager')->name('trash');
        Route::post('/{id}/restore', [SaleReturnController::class, 'restore'])
            ->middleware('role:admin,branch_manager')->whereNumber('id')->name('restore');
        Route::delete('/{id}/force-delete', [SaleReturnController::class, 'forceDelete'])
            ->middleware('role:admin,branch_manager')->whereNumber('id')->name('forceDelete');

        Route::patch('/{saleReturn}/mark-as-completed', [SaleReturnController::class, 'markAsCompleted'])
            ->middleware('permission:sale-returns.update')->name('markAsCompleted');
        Route::post('/{saleReturn}/items/{item}/mark-used', [SaleReturnController::class, 'markItemUsed'])
            ->middleware('permission:sale-returns.update')->name('markUsed');

        Route::get('/{saleReturn}', [SaleReturnController::class, 'show'])
            ->middleware('permission:sale-returns.view')->name('show');
        Route::get('/{saleReturn}/edit', [SaleReturnController::class, 'edit'])
            ->middleware('permission:sale-returns.update')->name('edit');
        Route::put('/{saleReturn}', [SaleReturnController::class, 'update'])
            ->middleware('permission:sale-returns.update')->name('update');
        Route::delete('/{saleReturn}', [SaleReturnController::class, 'destroy'])
            ->middleware('permission:sale-returns.delete')->name('destroy');
    });

    /* ═══════════════════════════════════════════════════════
     |  INVESTORS
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin,accountant', 'module:investors'])
        ->prefix('investors')->name('investors.')->group(function () {

        Route::get('/dashboard', [InvestorDashboardController::class, 'index'])->name('dashboard');
        Route::get('/', [InvestorController::class, 'index'])->name('index');
        Route::get('/create', [InvestorController::class, 'create'])->name('create');
        Route::post('/', [InvestorController::class, 'store'])->name('store');
        Route::get('/trash', [InvestorController::class, 'trash'])->name('trash');
        Route::post('/{id}/restore', [InvestorController::class, 'restore'])->whereNumber('id')->name('restore');
        Route::delete('/{id}/force-delete', [InvestorController::class, 'forceDelete'])->whereNumber('id')->name('forceDelete');
        Route::post('/{investor}/settle', [InvestorWithdrawalController::class, 'settle'])->name('settle');
        Route::post('/{investor}/toggle-status', [InvestorController::class, 'toggleStatus'])->name('toggleStatus');
        Route::get('/{investor}', [InvestorController::class, 'show'])
            ->middleware('permission:investors.view')
            ->name('show');
        Route::get('/{investor}/edit', [InvestorController::class, 'edit'])->name('edit');
        Route::put('/{investor}', [InvestorController::class, 'update'])->name('update');
        Route::delete('/{investor}', [InvestorController::class, 'destroy'])->name('destroy');
    });

    /* ═══════════════════════════════════════════════════════
     |  INVESTOR WITHDRAWALS
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin,accountant', 'module:investor-withdrawals'])
        ->prefix('investor-withdrawals')->name('investor-withdrawals.')->group(function () {

        Route::get('/', [InvestorWithdrawalController::class, 'index'])->name('index');
        Route::get('/create', [InvestorWithdrawalController::class, 'create'])->name('create');
        Route::post('/', [InvestorWithdrawalController::class, 'store'])->name('store');
        Route::get('/{withdrawal}', [InvestorWithdrawalController::class, 'show'])->name('show');
        Route::delete('/{withdrawal}', [InvestorWithdrawalController::class, 'destroy'])->name('destroy');
        Route::post('/{withdrawal}/approve', [InvestorWithdrawalController::class, 'approve'])->name('approve');
        Route::post('/{withdrawal}/reject', [InvestorWithdrawalController::class, 'reject'])->name('reject');
        Route::post('/{withdrawal}/mark-paid', [InvestorWithdrawalController::class, 'markAsPaid'])->name('markAsPaid');
        Route::get('/{withdrawal}/receipt', [InvestorWithdrawalController::class, 'receipt'])->name('receipt');
    });

    /* ═══════════════════════════════════════════════════════
     |  INVESTMENTS
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin,accountant', 'module:investments'])
        ->prefix('investments')->name('investments.')->group(function () {

        Route::get('/', [InvestmentController::class, 'index'])->name('index');
        Route::get('/create', [InvestmentController::class, 'create'])->name('create');
        Route::post('/', [InvestmentController::class, 'store'])->name('store');
        Route::get('/trash', [InvestmentController::class, 'trash'])->name('trash');
        Route::post('/{id}/restore', [InvestmentController::class, 'restore'])->whereNumber('id')->name('restore');
        Route::delete('/{id}/force-delete', [InvestmentController::class, 'forceDelete'])->whereNumber('id')->name('forceDelete');
        Route::post('/{investment}/withdraw', [InvestmentController::class, 'withdraw'])->name('withdraw');
        Route::patch('/{investment}/close', [InvestmentController::class, 'close'])->name('close');
        Route::get('/{investment}', [InvestmentController::class, 'show'])->name('show');
        Route::get('/{investment}/edit', [InvestmentController::class, 'edit'])->name('edit');
        Route::put('/{investment}', [InvestmentController::class, 'update'])->name('update');
        Route::delete('/{investment}', [InvestmentController::class, 'destroy'])->name('destroy');
    });

    /* ═══════════════════════════════════════════════════════
     |  INVESTMENT ALLOCATIONS
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin,accountant', 'module:investments'])
        ->prefix('investment-purchase-allocations')->name('investment-purchase-allocations.')->group(function () {

        Route::get('/', [InvestmentPurchaseAllocationController::class, 'index'])->name('index');
        Route::get('/create', [InvestmentPurchaseAllocationController::class, 'create'])->name('create');
        Route::post('/', [InvestmentPurchaseAllocationController::class, 'store'])->name('store');
        Route::get('/{allocation}', [InvestmentPurchaseAllocationController::class, 'show'])->name('show');
        Route::delete('/{allocation}', [InvestmentPurchaseAllocationController::class, 'destroy'])->name('destroy');
    });

    Route::middleware(['role:admin,accountant', 'module:investments'])
        ->prefix('investment-product-allocations')->name('investment-product-allocations.')->group(function () {

        Route::get('/', [InvestmentProductAllocationController::class, 'index'])->name('index');
        Route::get('/create', [InvestmentProductAllocationController::class, 'create'])->name('create');
        Route::post('/', [InvestmentProductAllocationController::class, 'store'])->name('store');
        Route::get('/{allocation}', [InvestmentProductAllocationController::class, 'show'])->name('show');
        Route::delete('/{allocation}', [InvestmentProductAllocationController::class, 'destroy'])->name('destroy');
    });

    /* ═══════════════════════════════════════════════════════
     |  PROFIT ENTRIES & TRANSACTIONS
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin,accountant,investor', 'module:investor-profit-entries'])
        ->prefix('investor-profit-entries')->name('investor-profit-entries.')->group(function () {

        Route::get('/', [InvestorProfitEntryController::class, 'index'])->name('index');
        Route::get('/by-investor/{investor}', [InvestorProfitEntryController::class, 'byInvestor'])->name('byInvestor');
        Route::get('/by-investment/{investment}', [InvestorProfitEntryController::class, 'byInvestment'])->name('byInvestment');
        Route::get('/{entry}', [InvestorProfitEntryController::class, 'show'])->name('show');
    });

    Route::middleware(['role:admin,accountant,investor', 'module:investor-transactions'])
        ->prefix('investor-transactions')->name('investor-transactions.')->group(function () {

        Route::get('/', [InvestorTransactionController::class, 'index'])->name('index');
        Route::get('/by-investor/{investor}', [InvestorTransactionController::class, 'byInvestor'])->name('byInvestor');
        Route::get('/{transaction}', [InvestorTransactionController::class, 'show'])->name('show');
    });

    Route::middleware('role:admin,accountant')->group(function () {
        Route::post('investor-profit-entries/bulk-pay', [InvestorProfitEntryController::class, 'markBatchAsPaid'])->name('investor-profit-entries.bulkPay');
        Route::patch('investor-profit-entries/{entry}/mark-as-paid', [InvestorProfitEntryController::class, 'markAsPaid'])->name('investor-profit-entries.markAsPaid');
        Route::patch('investor-profit-entries/{entry}/cancel', [InvestorProfitEntryController::class, 'cancel'])->name('investor-profit-entries.cancel');
    });

    /* ═══════════════════════════════════════════════════════
     |  EXPENSES
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin,accountant', 'module:expenses'])
        ->prefix('expenses')->name('expenses.')->group(function () {

        Route::get('/', [ExpenseController::class, 'index'])->name('index');
        Route::get('/create', [ExpenseController::class, 'create'])->name('create');
        Route::post('/', [ExpenseController::class, 'store'])->name('store');
        Route::get('/trash', [ExpenseController::class, 'trash'])->name('trash');
        Route::post('/{id}/restore', [ExpenseController::class, 'restore'])->whereNumber('id')->name('restore');
        Route::delete('/{id}/force-delete', [ExpenseController::class, 'forceDelete'])->whereNumber('id')->name('forceDelete');
        Route::get('/{expense}', [ExpenseController::class, 'show'])->name('show');
        Route::get('/{expense}/edit', [ExpenseController::class, 'edit'])->name('edit');
        Route::put('/{expense}', [ExpenseController::class, 'update'])->name('update');
        Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->name('destroy');
    });

    /* ═══════════════════════════════════════════════════════
     |  PRODUCT PRICE HISTORIES
     ═══════════════════════════════════════════════════════ */
    Route::middleware(['role:admin,accountant,branch_manager,stock_keeper', 'module:products'])
        ->prefix('product-price-histories')->name('product-price-histories.')->group(function () {

        Route::get('/', [ProductPriceHistoryController::class, 'index'])->name('index');
        Route::get('/by-variant/{variant}', [ProductPriceHistoryController::class, 'byVariant'])->name('byVariant');
        Route::get('/{history}', [ProductPriceHistoryController::class, 'show'])->name('show');
    });
});


// ... بقیه routes

// ... داخل گروه middleware(['auth'])



Route::middleware(['auth'])->group(function () {
    // ✅ Profit Report routes — قبل از هر چیز
    Route::middleware('module:reports')->prefix('reports/profit')->name('reports.profit.')->group(function () {
        Route::get('/', [ProfitReportController::class, 'index'])->name('index');

        // PDF (اول)
        Route::get('/pdf', [ProfitReportController::class, 'exportPdf'])->name('pdf');

        // CSV
        Route::get('/export', [ProfitReportController::class, 'export'])->name('export');
        Route::get('/export-summary', [ProfitReportController::class, 'exportSummary'])->name('export.summary');
        Route::get('/export-breakdown', [ProfitReportController::class, 'exportBreakdown'])->name('export.breakdown');
        Route::get('/export-per-investor', [ProfitReportController::class, 'exportPerInvestor'])->name('export.per-investor');
        Route::get('/export-exchange-rates', [ProfitReportController::class, 'exportExchangeRates'])->name('export.exchange-rates');
        Route::get('/export-sales', [ProfitReportController::class, 'exportSales'])->name('export.sales');
        Route::get('/export-products', [ProfitReportController::class, 'exportProducts'])->name('export.products');

        // Detail pages (آخر)
        Route::get('/investor/{investorId}/pdf', [ProfitReportController::class, 'investorPdf'])->whereNumber('investorId')->name('investor.pdf');
        Route::get('/investor/{investorId}', [ProfitReportController::class, 'investorDetail'])->whereNumber('investorId')->name('investor.detail');
        Route::get('/store/pdf', [ProfitReportController::class, 'storePdf'])->name('store.pdf');
        Route::get('/store', [ProfitReportController::class, 'storeDetail'])->name('store.detail');
    });
    
    // ... بقیه routes (dashboard, products, sales, ...)
});

/*
|--------------------------------------------------------------------------
| Additional Route Files
|--------------------------------------------------------------------------
*/
require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';