<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ForgotPasswordController;
use App\Http\Controllers\Api\V1\GoalController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ResetPasswordController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\RecurringTransactionController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Auth routes (public) with strict rate limiting
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

        // Password reset — public, strict rate limiting to prevent email spam
        Route::post('password/forgot', [ForgotPasswordController::class, 'sendResetLink'])->middleware('throttle:5,1');
        Route::post('password/reset', [ResetPasswordController::class, 'reset'])->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('profile', [AuthController::class, 'profile']);
            Route::patch('profile', [AuthController::class, 'updateProfile']);

            Route::patch('password', [AuthController::class, 'changePassword']); // TATI ADDED TO CHANGE PASSWORD FROM SETTINGS

            // Account deletion — requires current_password confirmation
            Route::delete('account', [AuthController::class, 'deleteAccount']);
        });
    });

    // Protected routes with general API rate limiting
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::apiResource('accounts', AccountController::class);
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('transactions', TransactionController::class);
        Route::apiResource('budgets', BudgetController::class);
        Route::apiResource('tags', TagController::class);

        Route::apiResource('goals', GoalController::class);
        Route::post('goals/{goal}/deposit', [GoalController::class, 'deposit']);

        Route::post('attachments', [AttachmentController::class, 'store']);
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy']);

        Route::get('dashboard', [DashboardController::class, 'index']);

        Route::apiResource('recurring-transactions', RecurringTransactionController::class);

        Route::get('reports', [ReportController::class, 'index']);

        Route::middleware('throttle:30,1')->group(function () {
            Route::get('reports/export', [ReportController::class, 'export'])->name('api.v1.reports.export');
        });
        Route::get('reports/exports', [ReportController::class, 'exportsList'])->name('api.v1.reports.exports.list');
        Route::delete('reports/exports/{key}', [ReportController::class, 'deleteExport'])->name('api.v1.reports.exports.delete');
        Route::get('reports/export/status/{key}', [ReportController::class, 'exportStatus'])->name('api.v1.reports.export.status');
        Route::get('reports/export/download/{key}', [ReportController::class, 'exportDownload'])->name('api.v1.reports.export.download');
    });
});
