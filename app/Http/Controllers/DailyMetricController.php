<?php

namespace App\Http\Controllers;

use App\Models\DailyMetric;
use App\Http\Requests\UpdateDailyMetricRequest;
use Illuminate\Http\JsonResponse;

class DailyMetricController extends Controller
{
    public function update(UpdateDailyMetricRequest $request, DailyMetric $dailyMetric): JsonResponse
    {
        // 1. Update the individual day
        $dailyMetric->update($request->validated());

        // 2. Fetch the parent weekly plan and metrics
        $weeklyPlan = $dailyMetric->weeklyPlan()->with('dailyMetrics')->first();
        $metrics = $weeklyPlan->dailyMetrics;

        // 3. Sum up the actual achievements
        $actualTraining = $metrics->sum('train_completed');
        $actualOnboarding = $metrics->sum('onboard_success');
        $actualGraduated = $metrics->sum('grad_certificate');

        // 4. Calculate percentages using FIXED numbers (10, 9, 9)
        // We use round() to ensure it fits perfectly into your decimal database columns
        $pctTraining = round(($actualTraining / 10) * 100, 2);
        $pctOnboarding = round(($actualOnboarding / 9) * 100, 2);
        $pctGraduated = round(($actualGraduated / 9) * 100, 2);

        // 5. Save the totals and percentages directly into the parent table
        $weeklyPlan->update([
            'last_week_training_qty' => $actualTraining,
            'last_week_training_pct' => $pctTraining,
            'last_week_onboarding_qty' => $actualOnboarding,
            'last_week_onboarding_pct' => $pctOnboarding,
            'last_week_graduated_qty' => $actualGraduated,
            'last_week_graduated_pct' => $pctGraduated,
        ]);

        return response()->json([
            'message' => 'Daily metrics updated and weekly summary recalculated successfully.',
            'data' => $dailyMetric
        ]);
    }
}
