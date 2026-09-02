<?php

use App\Http\Controllers\DailyMetricController;
use App\Http\Controllers\WeeklyActionPlanController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('weekly-plans', WeeklyActionPlanController::class);

Route::patch('daily-metrics/{dailyMetric}', [DailyMetricController::class, 'update']);
