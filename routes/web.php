<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\AgentRegistrationController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\StockAdjustmentController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SalesReturnController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\IncomeController;
use App\Http\Controllers\Admin\IncomeCategoryController;
use App\Http\Controllers\Admin\MoneyTransferController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\PurchaseReturnController;
use App\Http\Controllers\Admin\BankReconciliationController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\GoldenClubController;
use App\Http\Controllers\Admin\GoldenClubRewardController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ApiSystemController;
use App\Http\Controllers\Admin\CustomerGroupController;
use App\Http\Controllers\Admin\StaffUserController;
use App\Http\Controllers\Admin\RolePermissionController;

// Agent Controllers
use App\Http\Controllers\Agent\DashboardController as AgentDashboardController;
use App\Http\Controllers\Agent\CustomerController as AgentCustomerController;
use App\Http\Controllers\Agent\SaleController as AgentSaleController;
use App\Http\Controllers\Agent\CommissionController as AgentCommissionController;
use App\Http\Controllers\Agent\ReportController as AgentReportController;
use App\Http\Controllers\Agent\ProfileController as AgentProfileController;
use App\Http\Controllers\Agent\GoldenClubController as AgentGoldenClubController;

// Admin Agent Management
use App\Http\Controllers\Admin\AgentManagementController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// ============================================
// PUBLIC ROUTES
// ============================================

// Web setup wizard - see routes/install.php. Locked shut by the
// not.installed middleware once storage/installed exists (i.e. on every
// real install after the first), so this require is always safe.
require __DIR__ . '/install.php';

Route::get('/', function () {
    if (!\Illuminate\Support\Facades\File::exists(storage_path('installed'))) {
        return redirect()->route('install.index');
    }
    return redirect()->route('login');
});

// Agent Registration
Route::middleware(['throttle:6,1'])->group(function () {
    Route::get('/agent/register', [AgentRegistrationController::class, 'showRegistrationForm'])->name('agent.register');
    Route::post('/agent/register', [AgentRegistrationController::class, 'register'])->name('agent.register.post');
});
Route::get('/agent/register/success', [AgentRegistrationController::class, 'showSuccess'])->name('agent.register.success');
Route::get('/agent/policy', function () {
    return view('auth.agent-policy');
})->name('agent.policy');

// Auth routes (Laravel Breeze)
require __DIR__ . '/auth.php';

// ============================================
// ADMIN ROUTES - Protected with Auth
// ============================================
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin,manager,accountant', 'log.activity'])->group(function () {

    // ==========================================
    // 1. DASHBOARD
    // ==========================================
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ==========================================
    // 2. ACCOUNTS MANAGEMENT
    // ==========================================
    Route::prefix('accounts')->name('accounts.')->middleware('permission:accounts,view')->group(function () {
        Route::get('/', [AccountController::class, 'index'])->name('index');
        Route::get('/create', [AccountController::class, 'create'])->middleware('permission:accounts,create')->name('create');
        Route::post('/', [AccountController::class, 'store'])->middleware('permission:accounts,create')->name('store');
        Route::get('/{account}', [AccountController::class, 'show'])->name('show');
        Route::get('/{account}/edit', [AccountController::class, 'edit'])->middleware('permission:accounts,edit')->name('edit');
        Route::put('/{account}', [AccountController::class, 'update'])->middleware('permission:accounts,edit')->name('update');
        Route::delete('/{account}', [AccountController::class, 'destroy'])->middleware('permission:accounts,delete')->name('destroy');
        Route::post('/{account}/toggle-status', [AccountController::class, 'toggleStatus'])->middleware('permission:accounts,edit')->name('toggle-status');
    });

    // ==========================================
    // 3. CATEGORIES MANAGEMENT
    // ==========================================
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::get('/create', [CategoryController::class, 'create'])->name('create');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::get('/{category}', [CategoryController::class, 'show'])->name('show');
        Route::get('/{category}/edit', [CategoryController::class, 'edit'])->name('edit');
        Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
        Route::post('/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('toggle-status');
    });

    // ==========================================
    // 4. PRODUCTS MANAGEMENT
    // ==========================================
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::get('/create', [ProductController::class, 'create'])->name('create');
        Route::post('/', [ProductController::class, 'store'])->name('store');
        Route::get('/low-stock', [ProductController::class, 'lowStock'])->name('low-stock');
        Route::get('/{product}', [ProductController::class, 'show'])->name('show');
        Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
        Route::put('/{product}', [ProductController::class, 'update'])->name('update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
        Route::post('/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('toggle-status');
    });

    // ==========================================
    // 5. SUPPLIERS MANAGEMENT
    // ==========================================
    Route::prefix('suppliers')->name('suppliers.')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])->name('index');
        Route::get('/create', [SupplierController::class, 'create'])->name('create');
        Route::post('/', [SupplierController::class, 'store'])->name('store');
        Route::get('/{supplier}', [SupplierController::class, 'show'])->name('show');
        Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->name('edit');
        Route::put('/{supplier}', [SupplierController::class, 'update'])->name('update');
        Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->name('destroy');
        Route::post('/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus'])->name('toggle-status');
        Route::post('/{supplier}/payments', [SupplierController::class, 'makePayment'])->name('payments.store');
        Route::delete('/{supplier}/payments/{payment}', [SupplierController::class, 'deletePayment'])->name('payments.destroy');
    });

    // ==========================================
    // 6. PURCHASES MANAGEMENT
    // ==========================================
    Route::prefix('purchases')->name('purchases.')->group(function () {
        Route::get('/', [PurchaseController::class, 'index'])->name('index');
        Route::get('/create', [PurchaseController::class, 'create'])->name('create');
        Route::post('/', [PurchaseController::class, 'store'])->name('store');
        Route::get('/{purchase}', [PurchaseController::class, 'show'])->name('show');
        Route::get('/{purchase}/edit', [PurchaseController::class, 'edit'])->name('edit');
        Route::put('/{purchase}', [PurchaseController::class, 'update'])->name('update');
        Route::delete('/{purchase}', [PurchaseController::class, 'destroy'])->name('destroy');
        Route::post('/{purchase}/add-payment', [PurchaseController::class, 'addPayment'])->name('add-payment');
        Route::get('/{purchase}/return', [PurchaseController::class, 'returnCreate'])->name('return-create');
    });

    // ==========================================
    // 7. PURCHASE RETURNS
    // ==========================================
    Route::prefix('purchase-returns')->name('purchase-returns.')->group(function () {
        Route::get('/', [PurchaseReturnController::class, 'index'])->name('index');
        Route::get('/create', [PurchaseReturnController::class, 'create'])->name('create');
        Route::post('/', [PurchaseReturnController::class, 'store'])->name('store');
        Route::get('/{purchaseReturn}', [PurchaseReturnController::class, 'show'])->name('show');
        Route::delete('/{purchaseReturn}', [PurchaseReturnController::class, 'destroy'])->name('destroy');
        Route::get('/get-purchase-details/{purchaseId}', [PurchaseReturnController::class, 'getPurchaseDetails'])->name('get-purchase-details');
    });

    // ==========================================
    // 8. INVENTORY MANAGEMENT
    // ==========================================
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/dashboard', [InventoryController::class, 'dashboard'])->name('dashboard');
        Route::get('/history', [InventoryController::class, 'history'])->name('history');

        Route::prefix('adjustments')->name('adjustments.')->group(function () {
            Route::get('/', [StockAdjustmentController::class, 'index'])->name('index');
            Route::get('/create', [StockAdjustmentController::class, 'create'])->name('create');
            Route::post('/', [StockAdjustmentController::class, 'store'])->name('store');
            Route::delete('/{adjustment}', [StockAdjustmentController::class, 'destroy'])->name('destroy');
        });
    });

    // ==========================================
    // 9. CUSTOMER MANAGEMENT
    // ==========================================
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::get('/create', [CustomerController::class, 'create'])->name('create');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
        Route::get('/{customer}/edit', [CustomerController::class, 'edit'])->name('edit');
        Route::put('/{customer}', [CustomerController::class, 'update'])->name('update');
        Route::delete('/{customer}', [CustomerController::class, 'destroy'])->name('destroy');
        Route::post('/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('toggle-status');
        Route::post('/{customer}/payments', [CustomerController::class, 'makePayment'])->name('payments.store');
        Route::delete('/{customer}/payments/{payment}', [CustomerController::class, 'deletePayment'])->name('payments.destroy');
    });

    // ==========================================
    // 10. AGENT MANAGEMENT (Admin)
    // ==========================================
    Route::prefix('agents')->name('agents.')->middleware('permission:agents,view')->group(function () {
        Route::get('/', [AgentManagementController::class, 'index'])->name('index');
        Route::get('/pending', [AgentManagementController::class, 'pending'])->name('pending');
        Route::get('/create', [AgentManagementController::class, 'create'])->middleware('permission:agents,create')->name('create');
        Route::post('/', [AgentManagementController::class, 'store'])->middleware('permission:agents,create')->name('store');
        Route::get('/{user}/approve', [AgentManagementController::class, 'approve'])->middleware('permission:agents,edit')->name('approve');
        Route::post('/{user}/approve', [AgentManagementController::class, 'doApprove'])->middleware('permission:agents,edit')->name('do-approve');
        Route::get('/{user}/reject', [AgentManagementController::class, 'reject'])->middleware('permission:agents,edit')->name('reject');
        Route::post('/{user}/reject', [AgentManagementController::class, 'doReject'])->middleware('permission:agents,edit')->name('do-reject');
        Route::get('/{user}', [AgentManagementController::class, 'view'])->name('view');
        Route::get('/{user}/edit', [AgentManagementController::class, 'edit'])->middleware('permission:agents,edit')->name('edit');
        Route::put('/{user}', [AgentManagementController::class, 'update'])->middleware('permission:agents,edit')->name('update');
        Route::delete('/{user}', [AgentManagementController::class, 'destroy'])->middleware('permission:agents,delete')->name('destroy');
        Route::post('/{user}/toggle-status', [AgentManagementController::class, 'toggleStatus'])->middleware('permission:agents,edit')->name('toggle-status');
        Route::post('/{user}/pay-commission', [AgentManagementController::class, 'payCommission'])->middleware('permission:agents,edit')->name('pay-commission');
        Route::post('/{user}/sales/{sale}/hold-commission', [AgentManagementController::class, 'holdCommission'])->middleware('permission:agents,edit')->name('hold-commission');
        Route::post('/{user}/sales/{sale}/release-commission', [AgentManagementController::class, 'releaseCommission'])->middleware('permission:agents,edit')->name('release-commission');
        Route::post('/close-month', [AgentManagementController::class, 'closeMonth'])->middleware('permission:agents,edit')->name('close-month');
    });

    // ==========================================
    // 11. SALES MANAGEMENT (Admin)
    // ==========================================
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::get('/', [SaleController::class, 'index'])->name('index');
        Route::get('/create', [SaleController::class, 'create'])->name('create');
        Route::post('/', [SaleController::class, 'store'])->name('store');
        Route::get('/{sale}', [SaleController::class, 'show'])->name('show');
        Route::get('/{sale}/edit', [SaleController::class, 'edit'])->name('edit');
        Route::put('/{sale}', [SaleController::class, 'update'])->name('update');
        Route::delete('/{sale}', [SaleController::class, 'destroy'])->name('destroy');
        Route::post('/{sale}/add-payment', [SaleController::class, 'addPayment'])->name('add-payment');
        // Confirms/rejects a still-draft sale - how a customer-placed order
        // (source=customer_app, particularly a "direct"/no-agent one, which
        // only an admin can act on) becomes a real, ledger-posted sale.
        Route::post('/{sale}/confirm', [SaleController::class, 'confirm'])->name('confirm');
        Route::post('/{sale}/reject', [SaleController::class, 'reject'])->name('reject');
    });

    // ==========================================
    // 12. SALES RETURNS (Admin)
    // ==========================================
    Route::prefix('sales-returns')->name('sales-returns.')->group(function () {
        Route::get('/', [SalesReturnController::class, 'index'])->name('index');
        Route::get('/create', [SalesReturnController::class, 'create'])->name('create');
        Route::post('/', [SalesReturnController::class, 'store'])->name('store');
        Route::get('/{salesReturn}', [SalesReturnController::class, 'show'])->name('show');
        Route::delete('/{salesReturn}', [SalesReturnController::class, 'destroy'])->name('destroy');
        Route::get('/get-sale-details/{saleId}', [SalesReturnController::class, 'getSaleDetails'])->name('get-sale-details');
    });

    // ==========================================
    // 13. ADMIN PROFILE
    // ==========================================
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // ==========================================
    // 14. EXPENSES MANAGEMENT
    // ==========================================
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->name('index');
        Route::get('/create', [ExpenseController::class, 'create'])->name('create');
        Route::post('/', [ExpenseController::class, 'store'])->name('store');
        Route::get('/{expense}', [ExpenseController::class, 'show'])->name('show');
        Route::get('/{expense}/edit', [ExpenseController::class, 'edit'])->name('edit');
        Route::put('/{expense}', [ExpenseController::class, 'update'])->name('update');
        Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->name('destroy');
        Route::post('/{expense}/approve', [ExpenseController::class, 'approve'])->name('approve');
        Route::post('/{expense}/mark-paid', [ExpenseController::class, 'markAsPaid'])->name('mark-paid');
        Route::post('/{expense}/cancel', [ExpenseController::class, 'cancel'])->name('cancel');
    });

    // Expense Categories
    Route::prefix('expense-categories')->name('expense-categories.')->group(function () {
        Route::get('/', [ExpenseCategoryController::class, 'index'])->name('index');
        Route::post('/', [ExpenseCategoryController::class, 'store'])->name('store');
        Route::put('/{category}', [ExpenseCategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [ExpenseCategoryController::class, 'destroy'])->name('destroy');
    });

    // ==========================================
    // 15. INCOME MANAGEMENT
    // ==========================================
    Route::prefix('incomes')->name('incomes.')->group(function () {
        Route::get('/', [IncomeController::class, 'index'])->name('index');
        Route::get('/create', [IncomeController::class, 'create'])->name('create');
        Route::post('/', [IncomeController::class, 'store'])->name('store');
        Route::get('/{income}', [IncomeController::class, 'show'])->name('show');
        Route::get('/{income}/edit', [IncomeController::class, 'edit'])->name('edit');
        Route::put('/{income}', [IncomeController::class, 'update'])->name('update');
        Route::delete('/{income}', [IncomeController::class, 'destroy'])->name('destroy');
    });

    // Income Categories
    Route::prefix('income-categories')->name('income-categories.')->group(function () {
        Route::get('/', [IncomeCategoryController::class, 'index'])->name('index');
        Route::post('/', [IncomeCategoryController::class, 'store'])->name('store');
        Route::put('/{category}', [IncomeCategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [IncomeCategoryController::class, 'destroy'])->name('destroy');
    });

    // ==========================================
    // 16. MONEY TRANSFER MANAGEMENT
    // ==========================================
    Route::prefix('money-transfers')->name('money-transfers.')->group(function () {
        Route::get('/', [MoneyTransferController::class, 'index'])->name('index');
        Route::get('/create', [MoneyTransferController::class, 'create'])->name('create');
        Route::post('/', [MoneyTransferController::class, 'store'])->name('store');
        // Bound as {moneyTransfer}, not {transfer} - every controller method
        // below type-hints `MoneyTransfer $moneyTransfer`, and implicit
        // route-model-binding only works when the URI segment name matches
        // the controller's parameter name exactly. With the mismatched
        // {transfer} this used to have, Laravel silently fell through to
        // constructing a brand new, empty MoneyTransfer (id=null, no
        // attributes) instead of fetching the real record or 404ing -
        // confirmed live: show() rendered with a blank model and 500'd on
        // ->transfer_date->format(), and edit/update/destroy/complete/
        // cancel would all have silently operated on that same empty
        // instance instead of the real one.
        Route::get('/{moneyTransfer}', [MoneyTransferController::class, 'show'])->name('show');
        Route::get('/{moneyTransfer}/edit', [MoneyTransferController::class, 'edit'])->name('edit');
        Route::put('/{moneyTransfer}', [MoneyTransferController::class, 'update'])->name('update');
        Route::delete('/{moneyTransfer}', [MoneyTransferController::class, 'destroy'])->name('destroy');
        Route::post('/{moneyTransfer}/complete', [MoneyTransferController::class, 'complete'])->name('complete');
        Route::post('/{moneyTransfer}/cancel', [MoneyTransferController::class, 'cancel'])->name('cancel');
    });

    // ==========================================
    // 17. BUSINESS REPORTS
    // ==========================================
    Route::prefix('reports')->name('reports.')->group(function () {
        // Financial Reports
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/profit-loss/pdf', [ReportController::class, 'profitLossPdf'])->name('profit-loss-pdf');
        Route::get('/trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('/trial-balance/pdf', [ReportController::class, 'trialBalancePdf'])->name('trial-balance-pdf');

        // Customer Reports
        Route::get('/customers', [ReportController::class, 'customers'])->name('customers');
        Route::get('/customer/{customer}', [ReportController::class, 'customerDetail'])->name('customer-detail');
        Route::get('/customer-ledger/{customer}', [ReportController::class, 'customerLedger'])->name('customer-ledger');
        Route::get('/customer-ledger/{customer}/pdf', [ReportController::class, 'customerLedgerPdf'])->name('customer-ledger-pdf');

        // Supplier Reports
        Route::get('/suppliers', [ReportController::class, 'suppliers'])->name('suppliers');
        Route::get('/supplier/{supplier}', [ReportController::class, 'supplierDetail'])->name('supplier-detail');
        Route::get('/supplier-ledger/{supplier}', [ReportController::class, 'supplierLedger'])->name('supplier-ledger');
        Route::get('/supplier-ledger/{supplier}/pdf', [ReportController::class, 'supplierLedgerPdf'])->name('supplier-ledger-pdf');

        // Receivable / Payable Reports
        Route::get('/receivable', [ReportController::class, 'receivable'])->name('receivable');
        Route::get('/receivable/pdf', [ReportController::class, 'receivablePdf'])->name('receivable-pdf');
        Route::get('/payable', [ReportController::class, 'payable'])->name('payable');
        Route::get('/payable/pdf', [ReportController::class, 'payablePdf'])->name('payable-pdf');

        // Account Ledger / Cash Book / Bank Book (same report, any account)
        Route::get('/account-ledger/{account}', [ReportController::class, 'accountLedger'])->name('account-ledger');
        Route::get('/account-ledger/{account}/pdf', [ReportController::class, 'accountLedgerPdf'])->name('account-ledger-pdf');

        // Day Book (General Journal)
        Route::get('/day-book', [ReportController::class, 'dayBook'])->name('day-book');
        Route::get('/day-book/pdf', [ReportController::class, 'dayBookPdf'])->name('day-book-pdf');

        // Expense & Income Reports
        Route::get('/expenses', [ReportController::class, 'expenses'])->name('expenses');
        Route::get('/incomes', [ReportController::class, 'incomes'])->name('incomes');

        // Agent Reports
        Route::get('/agents', [ReportController::class, 'agents'])->name('agents');
        Route::get('/agent/{user}', [ReportController::class, 'agentDetail'])->name('agent-detail');

        // Daily Summary
        Route::get('/daily-summary', [ReportController::class, 'dailySummary'])->name('daily-summary');

        // Tax Report
        Route::get('/tax', [ReportController::class, 'taxReport'])->name('tax');
        Route::get('/tax/pdf', [ReportController::class, 'taxReportPdf'])->name('tax-pdf');
    });

    // ==========================================
    // 18. BANK RECONCILIATION
    // ==========================================
    Route::prefix('bank-reconciliations')->name('bank-reconciliations.')->group(function () {
        Route::get('/', [BankReconciliationController::class, 'index'])->name('index');
        Route::get('/create', [BankReconciliationController::class, 'create'])->name('create');
        Route::post('/', [BankReconciliationController::class, 'store'])->name('store');
        Route::get('/{bankReconciliation}', [BankReconciliationController::class, 'show'])->name('show');
        Route::get('/{bankReconciliation}/edit', [BankReconciliationController::class, 'edit'])->name('edit');
        Route::put('/{bankReconciliation}', [BankReconciliationController::class, 'update'])->name('update');
        Route::delete('/{bankReconciliation}', [BankReconciliationController::class, 'destroy'])->name('destroy');
        Route::post('/{bankReconciliation}/reconcile', [BankReconciliationController::class, 'reconcile'])->name('reconcile');
    });

    // ==========================================
    // 19. EXPORTS
    // ==========================================
    Route::prefix('exports')->name('exports.')->group(function () {
        Route::get('/csv', [ExportController::class, 'exportCSV'])->name('csv');
        Route::get('/excel', [ExportController::class, 'exportExcel'])->name('excel');
        Route::get('/pdf', [ExportController::class, 'exportPDF'])->name('pdf');
    });

    // ==========================================
    // 20. ACTIVITY LOGS
    // ==========================================
    Route::prefix('activity-logs')->name('activity-logs.')->middleware('permission:activity-logs,view')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index'])->name('index');
        Route::get('/{activityLog}', [ActivityLogController::class, 'show'])->name('show');
        Route::post('/clear', [ActivityLogController::class, 'clear'])->middleware('permission:activity-logs,delete')->name('clear');
        Route::post('/clear-all', [ActivityLogController::class, 'clearAll'])->middleware('permission:activity-logs,delete')->name('clear-all');
        Route::get('/export/{format}', [ActivityLogController::class, 'export'])->name('export');
    });

    // ==========================================
    // 21. BACKUP & RESTORE
    // ==========================================
    Route::prefix('backups')->name('backups.')->middleware('permission:backups,view')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::post('/create', [BackupController::class, 'create'])->middleware('permission:backups,create')->name('create');
        Route::get('/download/{filename}', [BackupController::class, 'download'])->name('download');
        Route::post('/restore/{filename}', [BackupController::class, 'restore'])
            ->middleware(['password.confirm', 'permission:backups,delete'])->name('restore');
        Route::delete('/delete-all', [BackupController::class, 'deleteAll'])->middleware('permission:backups,delete')->name('delete-all');
        Route::delete('/{filename}', [BackupController::class, 'destroy'])->middleware('permission:backups,delete')->name('destroy');
    });

    // ==========================================
    // 21b. SALE AGENT API - Docs & Tester (admin-only: the tester can
    // perform real create/update/delete calls against live data)
    // ==========================================
    Route::prefix('system/api')->name('system.api.')->middleware('role:admin')->group(function () {
        Route::get('/docs', [ApiSystemController::class, 'documentation'])->name('docs');
        Route::get('/tester', [ApiSystemController::class, 'tester'])->name('tester');
    });

    // Golden Customer Guide (Urdu) - internal reference doc for sales
    // agents, admin-gated same as the API docs above.
    Route::prefix('system')->name('system.')->middleware('role:admin')->group(function () {
        Route::get('/golden-guide', [ApiSystemController::class, 'goldenGuide'])->name('golden-guide');
    });

    // ==========================================
    // 22. GOLDEN CLUB - Admin
    // ==========================================
    Route::prefix('golden-club')->name('golden-club.')->group(function () {

        // Dashboard
        Route::get('/dashboard', [GoldenClubController::class, 'dashboard'])->name('dashboard');

        // Customers
        Route::prefix('customers')->name('customers.')->group(function () {
            Route::get('/', [GoldenClubController::class, 'customers'])->name('index');
            // /pending MUST come before /{customer} - otherwise route-model
            // binding tries (and fails) to resolve a Customer with id
            // "pending" and this always 404s (same bug class fixed
            // earlier in the backups routes).
            Route::get('/pending', [GoldenClubController::class, 'pendingVerification'])->name('pending-verification');
            Route::get('/{customer}', [GoldenClubController::class, 'customerShow'])->name('show');
            Route::post('/{customer}/verify', [GoldenClubController::class, 'verifyCustomer'])->name('verify');
        });

        // Rewards
        Route::resource('rewards', GoldenClubRewardController::class)->except(['show']);
        Route::prefix('redemptions')->name('redemptions.')->group(function () {
            Route::post('/{redeemedReward}/approve', [GoldenClubRewardController::class, 'approveRedemption'])->name('approve');
            Route::post('/{redeemedReward}/deliver', [GoldenClubRewardController::class, 'deliverRedemption'])->name('deliver');
            Route::post('/{redeemedReward}/cancel', [GoldenClubRewardController::class, 'cancelRedemption'])->name('cancel');
        });

        // Lucky Draw
        Route::prefix('lucky-draw')->name('lucky-draw.')->group(function () {
            Route::get('/campaigns', [GoldenClubController::class, 'campaigns'])->name('campaigns');
            Route::get('/campaigns/create', [GoldenClubController::class, 'campaignCreate'])->name('campaigns.create');
            Route::post('/campaigns', [GoldenClubController::class, 'campaignStore'])->name('campaigns.store');
            Route::get('/campaigns/{campaign}/edit', [GoldenClubController::class, 'campaignEdit'])->name('campaigns.edit');
            Route::put('/campaigns/{campaign}', [GoldenClubController::class, 'campaignUpdate'])->name('campaigns.update');
            Route::post('/campaigns/{campaign}/draw-winner', [GoldenClubController::class, 'drawWinner'])->name('campaigns.draw-winner');
            Route::get('/winners', [GoldenClubController::class, 'winners'])->name('winners');
        });
    });

    // ==========================================
    // 23. SETTINGS (admin-only)
    // ==========================================
    Route::prefix('settings')->name('settings.')->middleware('role:admin')->group(function () {
        Route::get('/', [SettingsController::class, 'general'])->name('index');

        Route::get('/general', [SettingsController::class, 'general'])->name('general');
        Route::post('/general', [SettingsController::class, 'updateGeneral'])->name('general.update');

        Route::get('/commission', [SettingsController::class, 'commission'])->name('commission');
        Route::post('/commission', [SettingsController::class, 'updateCommission'])->name('commission.update');

        Route::get('/golden-club', [SettingsController::class, 'goldenClub'])->name('golden-club');
        Route::post('/golden-club', [SettingsController::class, 'updateGoldenClub'])->name('golden-club.update');

        Route::prefix('customer-groups')->name('customer-groups.')->group(function () {
            Route::get('/', [CustomerGroupController::class, 'index'])->name('index');
            Route::get('/create', [CustomerGroupController::class, 'create'])->name('create');
            Route::post('/', [CustomerGroupController::class, 'store'])->name('store');
            Route::get('/{customerGroup}/edit', [CustomerGroupController::class, 'edit'])->name('edit');
            Route::put('/{customerGroup}', [CustomerGroupController::class, 'update'])->name('update');
            Route::delete('/{customerGroup}', [CustomerGroupController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [StaffUserController::class, 'index'])->name('index');
            Route::get('/create', [StaffUserController::class, 'create'])->name('create');
            Route::post('/', [StaffUserController::class, 'store'])->name('store');
            Route::get('/{user}/edit', [StaffUserController::class, 'edit'])->name('edit');
            Route::put('/{user}', [StaffUserController::class, 'update'])->name('update');
            Route::delete('/{user}', [StaffUserController::class, 'destroy'])->name('destroy');
        });

        Route::get('/permissions', [RolePermissionController::class, 'index'])->name('permissions.index');
        Route::post('/permissions', [RolePermissionController::class, 'update'])->name('permissions.update');
    });
});

// ============================================
// AGENT ROUTES (Protected)
// ============================================
Route::prefix('agent')->name('agent.')->middleware(['auth'])->group(function () {

    // Only sales agents can access these
    Route::middleware(['role:sales_agent'])->group(function () {

        // Dashboard
        Route::get('/dashboard', [AgentDashboardController::class, 'index'])->name('dashboard');

        // Customers (Only agent's own customers)
        Route::prefix('customers')->name('customers.')->group(function () {
            Route::get('/', [AgentCustomerController::class, 'index'])->name('index');
            Route::get('/create', [AgentCustomerController::class, 'create'])->name('create');
            Route::post('/', [AgentCustomerController::class, 'store'])->name('store');
            Route::get('/{customer}', [AgentCustomerController::class, 'show'])->name('show');
            Route::get('/{customer}/edit', [AgentCustomerController::class, 'edit'])->name('edit');
            Route::put('/{customer}', [AgentCustomerController::class, 'update'])->name('update');
            Route::delete('/{customer}', [AgentCustomerController::class, 'destroy'])->name('destroy');
        });

        // Sales (Only agent's own sales)
        Route::prefix('sales')->name('sales.')->group(function () {
            Route::get('/', [AgentSaleController::class, 'index'])->name('index');
            Route::get('/create', [AgentSaleController::class, 'create'])->name('create');
            Route::post('/', [AgentSaleController::class, 'store'])->name('store');
            Route::get('/{sale}', [AgentSaleController::class, 'show'])->name('show');
            Route::get('/{sale}/edit', [AgentSaleController::class, 'edit'])->name('edit');
            Route::put('/{sale}', [AgentSaleController::class, 'update'])->name('update');
            Route::delete('/{sale}', [AgentSaleController::class, 'destroy'])->name('destroy');
            Route::post('/{sale}/add-payment', [AgentSaleController::class, 'addPayment'])->name('add-payment');
            // Confirms/rejects a still-draft sale - how a customer's order
            // placed through this agent (source=customer_app) becomes real.
            Route::post('/{sale}/confirm', [AgentSaleController::class, 'confirm'])->name('confirm');
            Route::post('/{sale}/reject', [AgentSaleController::class, 'reject'])->name('reject');
        });

        // Commission & Reports
        Route::prefix('commissions')->name('commissions.')->group(function () {
            Route::get('/', [AgentCommissionController::class, 'index'])->name('index');
            Route::get('/details', [AgentCommissionController::class, 'details'])->name('details');
        });

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [AgentReportController::class, 'index'])->name('index');
            Route::get('/sales', [AgentReportController::class, 'sales'])->name('sales');
            Route::get('/commission', [AgentReportController::class, 'commission'])->name('commission');
            Route::get('/target', [AgentReportController::class, 'target'])->name('target');
        });

        // Profile
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [AgentProfileController::class, 'index'])->name('index');
            Route::put('/', [AgentProfileController::class, 'update'])->name('update');
        });

        // ==========================================
        // GOLDEN CLUB - Agent
        // ==========================================
        Route::prefix('golden-club')->name('golden-club.')->group(function () {
            Route::get('/dashboard', [AgentGoldenClubController::class, 'dashboard'])->name('dashboard');
            Route::get('/customers', [AgentGoldenClubController::class, 'customers'])->name('customers');
            Route::get('/rewards', [AgentGoldenClubController::class, 'rewards'])->name('rewards');
            Route::post('/rewards/{reward}/redeem', [AgentGoldenClubController::class, 'redeemReward'])->name('rewards.redeem');
        });
    });
});

// ============================================
// CUSTOMER GOLDEN CLUB ROUTES
// ============================================
// Deferred: requires a real `customers` auth guard/login (customers are not
// `User` records), which is out of scope for this pass. Re-add once the
// customer self-service portal is built.

// ============================================
// FALLBACK ROUTES (Redirects)
// ============================================
Route::get('/dashboard', function () {
    return redirect()->route('admin.dashboard');
})->middleware(['auth'])->name('dashboard');

Route::get('/admin', function () {
    return redirect()->route('admin.dashboard');
})->middleware(['auth']);
