<?php

use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminCommerceEngineController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminMarketingAssetController;
use App\Http\Controllers\Admin\AdminMemberController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminWalletController;
use App\Http\Controllers\Admin\AdminWithdrawalController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Member\MemberDashboardController;
use App\Http\Controllers\Member\MemberOrderController;
use App\Http\Controllers\Member\MemberTeamController;
use App\Http\Controllers\Member\MemberWalletController;
use App\Http\Controllers\Public\PublicProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ModerNutrition Public API Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/
Route::prefix('public')->group(function () {
    Route::get('/products', [PublicProductController::class, 'index']);
    Route::get('/products/{sku}', [PublicProductController::class, 'show']);
    Route::get('/migrate', function () {
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $migrateOutput = \Illuminate\Support\Facades\Artisan::output();

            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
            $seedOutput = \Illuminate\Support\Facades\Artisan::output();

            return response()->json([
                'message' => 'Database migrations and seeders executed successfully.',
                'migrate_output' => $migrateOutput,
                'seed_output' => $seedOutput,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Migration failed: ' . $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ], 500);
        }
    });
});

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

/*
|--------------------------------------------------------------------------
| Member-Facing Routes (Sanctum Protected — ModerNutrition-Dev)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:Consumer|Distributor|Leader|Country Operations|Global Administration'])->prefix('member')->group(function () {
    // Dashboard & Rank Progress
    Route::get('/dashboard', [MemberDashboardController::class, 'summary']);
    Route::get('/rank-progress', [MemberDashboardController::class, 'rankProgress']);
    Route::get('/profile', [MemberDashboardController::class, 'profile']);
    Route::put('/profile', [MemberDashboardController::class, 'updateProfile']);

    // Orders & Guest Cart Conversion
    Route::get('/orders', [MemberOrderController::class, 'index']);
    Route::post('/orders', [MemberOrderController::class, 'store']);
    Route::get('/orders/{id}', [MemberOrderController::class, 'show']);

    // Wallets & Transactions (from append-only ledger_entries)
    Route::get('/wallets', [MemberWalletController::class, 'wallets']);
    Route::get('/transactions', [MemberWalletController::class, 'transactions']);
    Route::post('/withdrawals', [MemberWalletController::class, 'requestWithdrawal']);

    // Binary Team View
    Route::get('/team/binary', [MemberTeamController::class, 'binarySummary']);
});

/*
|--------------------------------------------------------------------------
| Admin-Facing Routes (Sanctum Protected — ModerNutrition-Admin)
| Scoped strictly to Country Operations and Global Administration roles.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:Country Operations|Global Administration'])->prefix('admin')->group(function () {
    // Dashboard Summary
    Route::get('/dashboard', [AdminDashboardController::class, 'summary']);

    // Members Management
    Route::get('/members', [AdminMemberController::class, 'index']);
    Route::get('/members/{id}', [AdminMemberController::class, 'show']);
    Route::post('/members/{id}/approve', [AdminMemberController::class, 'approve']);
    Route::post('/members/{id}/suspend', [AdminMemberController::class, 'suspend']);
    Route::post('/members/{id}/rank-override', [AdminMemberController::class, 'rankOverride'])->middleware('role:Global Administration');

    // Products Management
    Route::get('/products', [AdminProductController::class, 'index']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::get('/products/{id}', [AdminProductController::class, 'show']);
    Route::put('/products/{id}', [AdminProductController::class, 'update']);
    Route::delete('/products/{id}', [AdminProductController::class, 'destroy'])->middleware('role:Global Administration');

    // Orders Management
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
    Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);

    // Commerce Engine (Plan Versions & Categories)
    Route::get('/plan-versions', [AdminCommerceEngineController::class, 'index']);
    Route::post('/plan-versions', [AdminCommerceEngineController::class, 'store']);
    Route::post('/plan-versions/{id}/approve', [AdminCommerceEngineController::class, 'approve']);
    Route::post('/plan-versions/{id}/activate', [AdminCommerceEngineController::class, 'activate'])->middleware('role:Global Administration');
    Route::get('/allocation-categories', [AdminCommerceEngineController::class, 'categories']);
    Route::post('/allocation-categories', [AdminCommerceEngineController::class, 'storeCategory']);

    // Wallets Platform Monitoring
    Route::get('/wallets', [AdminWalletController::class, 'index']);

    // Withdrawals (Maker-Checker Enforced)
    Route::get('/withdrawals', [AdminWithdrawalController::class, 'index']);
    Route::post('/withdrawals/{id}/approve', [AdminWithdrawalController::class, 'approve']);
    Route::post('/withdrawals/{id}/reject', [AdminWithdrawalController::class, 'reject']);

    // Marketing Assets & Affiliates
    Route::get('/marketing-assets', [AdminMarketingAssetController::class, 'index']);
    Route::post('/marketing-assets', [AdminMarketingAssetController::class, 'store']);
    Route::get('/marketing-assets/{id}', [AdminMarketingAssetController::class, 'show']);
    Route::put('/marketing-assets/{id}', [AdminMarketingAssetController::class, 'update']);
    Route::delete('/marketing-assets/{id}', [AdminMarketingAssetController::class, 'destroy'])->middleware('role:Global Administration');

    // Reports
    Route::get('/reports/daily-sales', [AdminReportController::class, 'dailySales']);
    Route::get('/reports/country-sales', [AdminReportController::class, 'countrySales']);
    Route::get('/reports/product-performance', [AdminReportController::class, 'productPerformance']);
    Route::get('/reports/commerce-pool', [AdminReportController::class, 'commercePool']);
    Route::get('/reports/bonus-distribution', [AdminReportController::class, 'bonusDistribution']);
    Route::get('/reports/member-activity', [AdminReportController::class, 'memberActivity']);

    // Audit Log
    Route::get('/audit-log', [AdminAuditLogController::class, 'index']);
});
