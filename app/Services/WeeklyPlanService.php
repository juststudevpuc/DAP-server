<?php

namespace App\Services;

use App\Models\WeeklyActionPlan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WeeklyPlanService
{
    /**
     * Creates a new weekly plan, calculates last week's summary, and spawns daily rows.
     */
    public function createPlan(array $validatedData, int $userId): WeeklyActionPlan
    {
        $startDate = Carbon::parse($validatedData['start_date']);

        // 1. Calculate previous week metrics
        $lastWeekMetrics = $this->calculatePreviousWeekSummary($userId, $startDate);

        // 2. Merge metrics into data array and create the plan
        $planData = array_merge($validatedData, $lastWeekMetrics, ['user_id' => $userId]);
        $plan = WeeklyActionPlan::create($planData);

        // 3. Spawn the 6 days
        $this->spawnDailyMetrics($plan, $startDate);

        return $plan;
    }

    /**
     * Calculates the summary percentages from the previous week.
     * We prefer to use Eloquent over raw SQL and collections over arrays here.
     */
    protected function calculatePreviousWeekSummary(int $userId, Carbon $currentStartDate): array
    {
        // Find the most recent plan before this new one
        $lastWeekPlan = WeeklyActionPlan::where('user_id', $userId)
            ->where('end_date', '<', $currentStartDate)
            ->latest('end_date')
            ->with('dailyMetrics') // Eager load to prevent N+1 issues
            ->first();

        if (!$lastWeekPlan || $lastWeekPlan->dailyMetrics->isEmpty()) {
            return []; // Return empty if this is the user's very first week
        }

        $days = $lastWeekPlan->dailyMetrics;

        // Training Math
        $trainExpected = $days->sum('train_expected');
        $trainCompleted = $days->sum('train_completed');
        $trainingPct = $trainExpected > 0 ? ($trainCompleted / $trainExpected) * 100 : 0;

        // Onboarding Math (Summing the 5 onboarding step columns for total expected vs success)
        $onboardTotalSteps = $days->sum('onboard_company_info')
                           + $days->sum('onboard_system_analysis')
                           + $days->sum('onboard_configure_hr')
                           + $days->sum('onboard_provide_lesson');
        $onboardSuccess = $days->sum('onboard_success');
        $onboardingPct = $onboardTotalSteps > 0 ? ($onboardSuccess / $onboardTotalSteps) * 100 : 0;

        // Graduated Math
        $gradTotal = $days->sum('grad_certificate') + $days->sum('grad_hr_policy') + $days->sum('grad_book');
        // Assuming graduation is a total count metric, we'll track the raw quantity.
        // If there's a specific target to hit 100%, adjust this formula.
        $gradPct = $lastWeekPlan->target_graduated > 0 ? ($gradTotal / $lastWeekPlan->target_graduated) * 100 : 0;

        return [
            'last_week_training_qty' => $trainCompleted,
            'last_week_training_pct' => round($trainingPct, 2),
            'last_week_onboarding_qty' => $onboardSuccess,
            'last_week_onboarding_pct' => round($onboardingPct, 2),
            'last_week_graduated_qty' => $gradTotal,
            'last_week_graduated_pct' => round($gradPct, 2),
        ];
    }

    /**
     * Automatically generates the 6 daily metric rows (Mon-Sat).
     */
    protected function spawnDailyMetrics(WeeklyActionPlan $plan, Carbon $startDate): void
    {
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $metrics = [];

        foreach ($days as $index => $dayName) {
            $metrics[] = [
                'day_name' => $dayName,
                'record_date' => $startDate->copy()->addDays($index),
                // created_at and updated_at need to be set manually when using createMany or insert
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // createMany is a highly efficient way to insert multiple related rows at once
        $plan->dailyMetrics()->createMany($metrics);
    }
}
