<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Agent\AuthController;
use App\Http\Controllers\Api\Agent\DashboardController;
use App\Http\Controllers\Api\Agent\CustomerController;
use App\Http\Controllers\Api\Agent\ProductController;
use App\Http\Controllers\Api\Agent\SaleController;
use App\Http\Controllers\Api\Agent\CommissionController;
use App\Http\Controllers\Api\Agent\ReportController;
use App\Http\Controllers\Api\Agent\GoldenClubController;
use App\Http\Controllers\Api\Agent\NotificationController;
use App\Http\Controllers\Api\Agent\PaymentController;
use App\Http\Controllers\Api\Agent\CompanyController;

// ============================================================
// SALE AGENT MOBILE APP API (Flutter) - /api/v1/agent/*
// See resources/views/admin/system/api-docs.blade.php for the full
// developer guide (auth flow, request/response shapes, examples).
// ============================================================
Route::prefix('agent')->name('api.agent.')->group(function () {

    // ---- Public (no token yet) ----
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:6,1') // 6 attempts/minute, mirrors login's brute-force protection
        ->name('register');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1') // 6 attempts/minute, mirrors typical login brute-force protection
        ->name('login');

    // ---- Authenticated + must be an active, approved sales_agent ----
    Route::middleware(['auth:sanctum', 'agent.active'])->group(function () {

        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::put('/me', [AuthController::class, 'updateProfile'])->name('me.update');
        Route::put('/me/password', [AuthController::class, 'changePassword'])->name('me.password');
        Route::post('/device-token', [AuthController::class, 'updateDeviceToken'])->name('device-token');

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
            Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
            Route::post('/{notification}/read', [NotificationController::class, 'markRead'])->name('read');
        });

        Route::apiResource('customers', CustomerController::class);
        Route::get('/customer-groups', [CustomerController::class, 'groups'])->name('customer-groups');

        Route::get('/products', [ProductController::class, 'index'])->name('products.index');

        // Every sale submitted here lands as status=draft, no stock/ledger
        // effect - only an admin can confirm/reject it (Admin\SaleController,
        // see admin.sales.confirm/reject). There is no agent-side confirm or
        // reject endpoint any more, for agent-created sales or customer-app
        // orders alike.
        Route::apiResource('sales', SaleController::class);
        Route::post('/sales/{sale}/payments', [SaleController::class, 'addPayment'])->name('sales.payments.store');

        // The agent's own payment history (every payment submitted against
        // their invoices, with pending/approved/rejected state).
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');

        // Letterhead details for the invoice PDF the app generates.
        Route::get('/company', [CompanyController::class, 'show'])->name('company');

        Route::get('/commissions', [CommissionController::class, 'index'])->name('commissions.index');
        Route::get('/commissions/summary', [CommissionController::class, 'summary'])->name('commissions.summary');

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/overview', [ReportController::class, 'overview'])->name('overview');
            Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
            Route::get('/commission', [ReportController::class, 'commission'])->name('commission');
            Route::get('/target', [ReportController::class, 'target'])->name('target');
        });

        Route::prefix('golden-club')->name('golden-club.')->group(function () {
            Route::get('/dashboard', [GoldenClubController::class, 'dashboard'])->name('dashboard');
            Route::get('/customers', [GoldenClubController::class, 'customers'])->name('customers');
            Route::get('/rewards', [GoldenClubController::class, 'rewards'])->name('rewards');
            Route::post('/rewards/{reward}/redeem', [GoldenClubController::class, 'redeemReward'])->name('rewards.redeem');
        });
    });
});
