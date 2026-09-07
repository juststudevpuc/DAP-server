<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WeeklyActionPlanController;
use App\Http\Controllers\DailyMetricController;
// just comit 
// 1. Public Routes (No authentication required)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// 2. Protected Routes (Require a valid Bearer token)
// Protected Routes (Require a valid Bearer token)
Route::middleware('auth:sanctum')->group(function () {

    // Auth Routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // 🚨 Place this BEFORE the apiResource!
    Route::get('/weekly-plans/current', [WeeklyActionPlanController::class, 'current']);

    // App Data Routes
    Route::apiResource('weekly-plans', WeeklyActionPlanController::class);
    Route::patch('daily-metrics/{dailyMetric}', [DailyMetricController::class, 'update']);
});
