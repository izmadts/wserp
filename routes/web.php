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
use App\Http\Controllers\Admin\AgentController;
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
use App\Http\Controllers\Admin\CommissionController;
use App\Http\Controllers\Admin\PurchaseReturnController;
use App\Http\Controllers\Admin\PurchaseReturnItemController;
use App\Http\Controllers\Admin\BankReconciliationController;
use App\Http\Controllers\Admin\BankReconciliationItemController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\ActivityLogController;
// Agent Controllers
use App\Http\Controllers\Agent\DashboardController as AgentDashboardController;
use App\Http\Controllers\Agent\CustomerController as AgentCustomerController;
use App\Http\Controllers\Agent\SaleController as AgentSaleController;
use App\Http\Controllers\Agent\CommissionController as AgentCommissionController;
use App\Http\Controllers\Agent\ReportController as AgentReportController;
use App\Http\Controllers\Agent\ProfileController as AgentProfileController;

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
Route::get('/', function () {
    return redirect()->route('login');
});
Route::get('/test-chart', function () {
    return view('test-chart');
});
// Agent Registration
Route::get('/agent/register', [AgentRegistrationController::class, 'showRegistrationForm'])->name('agent.register');
Route::post('/agent/register', [AgentRegistrationController::class, 'register'])->name('agent.register.post');
Route::get('/agent/register/success', [AgentRegistrationController::class, 'showSuccess'])->name('agent.register.success');
Route::get('/agent/policy', function () {
    return view('auth.agent-policy');
})->name('agent.policy');

// Auth routes (Laravel Breeze)
require __DIR__ . '/auth.php';

// ============================================
// ADMIN ROUTES - Protected with Auth
// ============================================
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'log.activity'])->group(function () {

    // ==========================================
    // 1. DASHBOARD
    // ==========================================
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // ==========================================
    // 2. ACCOUNTS MANAGEMENT
    // ==========================================
    Route::prefix('accounts')->name('accounts.')->group(function () {
        Route::get('/', [AccountController::class, 'index'])->name('index');
        Route::get('/create', [AccountController::class, 'create'])->name('create');
        Route::post('/', [AccountController::class, 'store'])->name('store');
        Route::get('/{account}', [AccountController::class, 'show'])->name('show');
        Route::get('/{account}/edit', [AccountController::class, 'edit'])->name('edit');
        Route::put('/{account}', [AccountController::class, 'update'])->name('update');
        Route::delete('/{account}', [AccountController::class, 'destroy'])->name('destroy');
        Route::get('/{account}/toggle-status', [AccountController::class, 'toggleStatus'])->name('toggle-status');
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
        Route::get('/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('toggle-status');
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
        Route::get('/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('toggle-status');
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
        Route::get('/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus'])->name('toggle-status');
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
    // PURCHASE RETURNS
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
    // 7. INVENTORY MANAGEMENT
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
    // 8. CUSTOMER MANAGEMENT
    // ==========================================
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::get('/create', [CustomerController::class, 'create'])->name('create');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
        Route::get('/{customer}/edit', [CustomerController::class, 'edit'])->name('edit');
        Route::put('/{customer}', [CustomerController::class, 'update'])->name('update');
        Route::delete('/{customer}', [CustomerController::class, 'destroy'])->name('destroy');
        Route::get('/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('toggle-status');
    });

    // ==========================================
    // 9. AGENT MANAGEMENT (Admin)
    // ==========================================
    Route::prefix('agents')->name('agents.')->group(function () {
        Route::get('/', [AgentManagementController::class, 'index'])->name('index');
        Route::get('/pending', [AgentManagementController::class, 'pending'])->name('pending');
        Route::get('/create', [AgentManagementController::class, 'create'])->name('create');
        Route::post('/', [AgentManagementController::class, 'store'])->name('store');
        Route::get('/{user}/approve', [AgentManagementController::class, 'approve'])->name('approve');
        Route::post('/{user}/approve', [AgentManagementController::class, 'doApprove'])->name('do-approve');
        Route::get('/{user}/reject', [AgentManagementController::class, 'reject'])->name('reject');
        Route::post('/{user}/reject', [AgentManagementController::class, 'doReject'])->name('do-reject');
        Route::get('/{user}', [AgentManagementController::class, 'view'])->name('view');
        Route::get('/{user}/edit', [AgentManagementController::class, 'edit'])->name('edit');
        Route::put('/{user}', [AgentManagementController::class, 'update'])->name('update');
        Route::delete('/{user}', [AgentManagementController::class, 'destroy'])->name('destroy');
        Route::post('/{user}/toggle-status', [AgentManagementController::class, 'toggleStatus'])->name('toggle-status');
        Route::post('/{user}/pay-commission', [AgentManagementController::class, 'payCommission'])->name('pay-commission');
    });

    // ==========================================
    // 10. SALES MANAGEMENT (Admin)
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
    });
    // ==========================================
    // 11. SALES RETURNS (Admin)
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
    // 12. ADMIN PROFILE
    // ==========================================
    Route::get('/profile', [App\Http\Controllers\Admin\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [App\Http\Controllers\Admin\ProfileController::class, 'update'])->name('profile.update');

    // ==========================================
    // EXPENSES MANAGEMENT
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
    // INCOME MANAGEMENT
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
    // MONEY TRANSFER MANAGEMENT
    // ==========================================
    Route::prefix('money-transfers')->name('money-transfers.')->group(function () {
        Route::get('/', [MoneyTransferController::class, 'index'])->name('index');
        Route::get('/create', [MoneyTransferController::class, 'create'])->name('create');
        Route::post('/', [MoneyTransferController::class, 'store'])->name('store');
        Route::get('/{transfer}', [MoneyTransferController::class, 'show'])->name('show');
        Route::get('/{transfer}/edit', [MoneyTransferController::class, 'edit'])->name('edit');
        Route::put('/{transfer}', [MoneyTransferController::class, 'update'])->name('update');
        Route::delete('/{transfer}', [MoneyTransferController::class, 'destroy'])->name('destroy');
        Route::post('/{transfer}/complete', [MoneyTransferController::class, 'complete'])->name('complete');
        Route::post('/{transfer}/cancel', [MoneyTransferController::class, 'cancel'])->name('cancel');
    });

    // ==========================================
    // REPORTS
    // ==========================================
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
    });
    // ==========================================
    // BUSINESS REPORTS
    // ==========================================
    Route::prefix('reports')->name('reports.')->group(function () {
        // Existing
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');

        // Customer Reports
        Route::get('/customers', [ReportController::class, 'customers'])->name('customers');
        Route::get('/customer/{customer}', [ReportController::class, 'customerDetail'])->name('customer-detail');

        // Supplier Reports
        Route::get('/suppliers', [ReportController::class, 'suppliers'])->name('suppliers');
        Route::get('/supplier/{supplier}', [ReportController::class, 'supplierDetail'])->name('supplier-detail');

        // Receivable Report
        Route::get('/receivable', [ReportController::class, 'receivable'])->name('receivable');

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
    });
    // ==========================================
    // BANK RECONCILIATION
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
    // EXPORTS
    // ==========================================
    Route::prefix('exports')->name('exports.')->group(function () {
        Route::get('/csv', [ExportController::class, 'exportCSV'])->name('csv');
        Route::get('/excel', [ExportController::class, 'exportExcel'])->name('excel');
        Route::get('/pdf', [ExportController::class, 'exportPDF'])->name('pdf');
    });
    // ==========================================
    // ACTIVITY LOGS
    // ==========================================
    Route::prefix('activity-logs')->name('activity-logs.')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index'])->name('index');
        Route::get('/{activityLog}', [ActivityLogController::class, 'show'])->name('show');
        Route::post('/clear', [ActivityLogController::class, 'clear'])->name('clear');
        Route::post('/clear-all', [ActivityLogController::class, 'clearAll'])->name('clear-all');
        Route::get('/export/{format}', [ActivityLogController::class, 'export'])->name('export');
    });
    // ==========================================
    // BACKUP & RESTORE
    // ==========================================
    Route::prefix('backups')->name('backups.')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::post('/create', [BackupController::class, 'create'])->name('create');
        Route::get('/download/{filename}', [BackupController::class, 'download'])->name('download');
        Route::post('/restore/{filename}', [BackupController::class, 'restore'])->name('restore');
        Route::delete('/{filename}', [BackupController::class, 'destroy'])->name('destroy');
        Route::delete('/delete-all', [BackupController::class, 'deleteAll'])->name('delete-all');
    });
});

// ============================================
// AGENT ROUTES (Protected)
// ============================================
Route::prefix('agent')->name('agent.')->middleware(['auth', 'verified'])->group(function () {

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
    });
});

// ============================================
// FALLBACK ROUTES (Redirects)
// ============================================
Route::get('/dashboard', function () {
    return redirect()->route('admin.dashboard');
})->middleware(['auth'])->name('dashboard');

Route::get('/admin', function () {
    return redirect()->route('admin.dashboard');
})->middleware(['auth']);
