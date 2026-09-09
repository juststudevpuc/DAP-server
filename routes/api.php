<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WeeklyActionPlanController;
use App\Http\Controllers\DailyMetricController;
use App\Http\Controllers\TelegramController;

// 1. Public Routes (No authentication required)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// 🚨 TELEGRAM WEBHOOK: Must be public because Telegram's servers don't log in.
// (Ensure your controller expects the $secret parameter from Step 3)
Route::post('/telegram/{secret}/webhook', [TelegramController::class, 'handleWebhook']);


// 2. Protected Routes (Require a valid Bearer token)
Route::middleware('auth:sanctum')->group(function () {

    // Auth Routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Telegram Link Generation (Needs the logged-in user)
    Route::get('/telegram/link', [TelegramController::class, 'generateLink']);

    Route::post('/telegram/send-image', [TelegramController::class, 'sendImageToUser']);
    // 🚨 Place this BEFORE the apiResource!
    Route::get('/weekly-plans/current', [WeeklyActionPlanController::class, 'current']);

    // App Data Routes
    Route::apiResource('weekly-plans', WeeklyActionPlanController::class);
    Route::patch('daily-metrics/{dailyMetric}', [DailyMetricController::class, 'update']);
});
