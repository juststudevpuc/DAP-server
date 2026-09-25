<?php

use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AdminSettingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WeeklyActionPlanController;
use App\Http\Controllers\DailyMetricController;
use App\Http\Controllers\TelegramController;
use App\Models\User;

Route::get('/public/stats', function () {
    return response()->json([
        'success' => true,
        'total_users' => User::count()
    ]);
});

// 1. Public Routes (No authentication required)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// 🚨 TELEGRAM WEBHOOK: Must be public because Telegram's servers don't log in.
Route::post('/telegram/{secret}/webhook', [TelegramController::class, 'handleWebhook']);

// 2. Protected Routes (Require a valid Bearer token)
Route::middleware('auth:sanctum')->group(function () {

    // Auth Routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/weekly-plans/{weeklyPlan}/complete', [WeeklyActionPlanController::class, 'completeWeek']);
    Route::delete('/weekly-plans/{weeklyPlan}', [WeeklyActionPlanController::class, 'destroy']);

    // Telegram Link & Image Routes
    Route::get('/telegram/link', [TelegramController::class, 'generateLink']);
    Route::post('/telegram/send-image', [TelegramController::class, 'sendImageToUser']);
    Route::post('/telegram/send-daily-images', [TelegramController::class, 'sendDailyImagesToTelegram']);
    Route::post('/telegram/send-weekly-images', [TelegramController::class, 'sendWeeklyImagesToTelegram']);

    // 🚨 Place this BEFORE the apiResource!
    Route::get('/weekly-plans/current', [WeeklyActionPlanController::class, 'current']);

    // App Data Routes
    Route::apiResource('weekly-plans', WeeklyActionPlanController::class);
    Route::patch('daily-metrics/{dailyMetric}', [DailyMetricController::class, 'update']);

    // Admin & Super Admin Shared Group
    Route::middleware('isAdmin')->prefix('admin')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index']);
        Route::get('/company-summary', [WeeklyActionPlanController::class, 'companySummary']);
        Route::get('/member-plan', [WeeklyActionPlanController::class, 'getMemberPlan']);
        Route::get('/team-reports', [WeeklyActionPlanController::class, 'teamReportsSummary']);

        // Auto Telegram Alerts & System Settings Routes (Protected by isAdmin)
        Route::get('/telegram/auto-alerts', [AdminSettingController::class, 'getAutoAlertsStatus']);
        Route::post('/telegram/auto-alerts', [AdminSettingController::class, 'toggleAutoAlerts']);
        Route::get('/system-settings', [AdminSettingController::class, 'getSystemSettingsData']);
        Route::post('/users/{id}/telegram-toggle', [AdminSettingController::class, 'toggleUserTelegram']);
        Route::post('/users/{id}/settings', [AdminSettingController::class, 'toggleUserTelegram']); // Dedicated modal save route
        Route::post('/users/{id}/telegram-test', [AdminSettingController::class, 'sendInstantTestReminder']);
    });

    // Super Admin Exclusive Group
    Route::middleware('isSuperAdmin')->prefix('super-admin')->group(function () {
        Route::patch('/users/{user}/role', [UserManagementController::class, 'updateRole']);
        Route::patch('/users/{id}/role', [UserManagementController::class, 'updateRole']);
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy']);
    });

    Route::post('/company-summary/notes', [WeeklyActionPlanController::class, 'saveSummaryNotes']);
    Route::put('/admin/users/{user}/reset-password', [UserManagementController::class, 'adminResetPassword']);

});
