<?php

namespace App\Http\Controllers;

use App\Models\WeeklyActionPlan;
use Illuminate\Http\Request;
use App\Services\WeeklyPlanService;
use App\Http\Resources\WeeklyPlanResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WeeklyActionPlanController extends Controller
{
    public function __construct(protected WeeklyPlanService $planService) {}

    // [READ] Get a list of historical weeks for the authenticated user
    public function index(Request $request)
    {
        $user = $request->user();
        $year = $request->query('year');
        $month = $request->query('month');

        // Convert empty string to null to prevent SQL crashes
        $week = $request->query('week');
        if ($week === '') {
            $week = null;
        }

        // ✅ FIXED: Use $user->weeklyActionPlans() instead of hardcoding user_id 1
        $plans = $user->weeklyActionPlans()
            ->filterByPeriod($year, $month, $week)
            ->with('dailyMetrics')
            ->latest('start_date')
            ->paginate(10);

        return WeeklyPlanResource::collection($plans);
    }

    public function store(Request $request): WeeklyPlanResource
    {
        $user = $request->user();
        $plan = $this->planService->createPlan($request->validated(), $user->id);
        return new WeeklyPlanResource($plan->load('dailyMetrics'));
    }

    public function show(WeeklyActionPlan $weeklyPlan): WeeklyPlanResource
    {
        // Optional security check: Ensure the plan belongs to the logged-in user
        if ($weeklyPlan->user_id !== request()->user()->id) {
            abort(403, 'Unauthorized action.');
        }

        return new WeeklyPlanResource($weeklyPlan->load('dailyMetrics'));
    }

    public function update(Request $request, WeeklyActionPlan $weeklyPlan): WeeklyPlanResource
    {
        if ($weeklyPlan->user_id !== request()->user()->id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'target_completed_training' => 'sometimes|integer|min:0',
            'target_completed_onboarding' => 'sometimes|integer|min:0',
            'target_graduated' => 'sometimes|integer|min:0',
            'what_worked' => 'nullable|string',
            'what_didnt_work' => 'nullable|string',
            'what_to_improve' => 'nullable|string',
            'what_is_next' => 'nullable|string',
        ]);

        $weeklyPlan->update($validated);

        return new WeeklyPlanResource($weeklyPlan->load('dailyMetrics'));
    }

    // 👉 ADD THE COMPLETE WEEK METHOD HERE:
    public function completeWeek(Request $request, WeeklyActionPlan $weeklyPlan): WeeklyPlanResource
    {
        if ($weeklyPlan->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'what_worked' => 'nullable|string',
            'what_didnt_work' => 'nullable|string',
            'what_to_improve' => 'nullable|string',
            'what_is_next' => 'nullable|string',
        ]);

        $weeklyPlan->update(array_merge($validated, [
            'is_completed' => true // Locks this week into history!
        ]));

        return new WeeklyPlanResource($weeklyPlan->load('dailyMetrics'));
    }

  public function destroy(WeeklyActionPlan $weeklyPlan): JsonResponse
{
    if ($weeklyPlan->user_id !== request()->user()->id) {
        abort(403, 'Unauthorized action.');
    }

    // 1. Explicitly delete related daily metrics first to avoid database foreign key conflicts
    $weeklyPlan->dailyMetrics()->delete();

    // 2. Now delete the weekly plan itself from the database
    $weeklyPlan->delete();

    return response()->json([
        'message' => 'Weekly plan and its daily metrics deleted successfully.'
    ], 200);
}

   public function current(Request $request): WeeklyPlanResource
{
    $user = $request->user();

    // 1. Find the user's absolute latest weekly plan
    $latestPlan = $user->weeklyActionPlans()
        ->with('dailyMetrics')
        ->latest('start_date')
        ->first();

    // 2. If they have a plan and it's NOT completed yet, return it as their "Current Active Week"
    if ($latestPlan && !$latestPlan->is_completed) {
        return new WeeklyPlanResource($latestPlan);
    }

    // 3. Otherwise (First time user, or previous week was completed), generate the next sequential week!
    $startDate = $latestPlan
        ? Carbon::parse($latestPlan->end_date)->addDay() // Day after last week ended
        : Carbon::now()->startOfWeek();                  // Absolute first week

    $endDate = $startDate->copy()->endOfWeek();

    // Dynamically calculate the week number relative to user's history count + 1
    $nextWeekNumber = $user->weeklyActionPlans()->count() + 1;

    $newPlan = DB::transaction(function () use ($user, $startDate, $endDate, $nextWeekNumber) {
        $plan = $user->weeklyActionPlans()->create([
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'week_number' => $nextWeekNumber,
            'is_completed' => false, // Active until finished
        ]);

        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        foreach ($days as $index => $day) {
            $plan->dailyMetrics()->create([
                'day_name' => $day,
                'record_date' => $startDate->copy()->addDays($index)->format('Y-m-d'),
                'train_expected' => 0,
                'train_completed' => 0,
                'train_cancel_delay' => 0,
                'onboard_company_info' => 0,
                'onboard_system_analysis' => 0,
                'onboard_configure_hr' => 0,
                'onboard_provide_lesson' => 0,
                'onboard_success' => 0,
                'grad_certificate' => 0,
                'grad_hr_policy' => 0,
                'grad_book' => 0,
                'comment' => '',
            ]);
        }

        return $plan->load('dailyMetrics');
    });

    return new WeeklyPlanResource($newPlan);
}
}
