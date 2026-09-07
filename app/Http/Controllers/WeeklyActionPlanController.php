<?php

namespace App\Http\Controllers;

use App\Models\WeeklyActionPlan;
use App\Http\Requests\StoreWeeklyActionPlanRequest;
use Illuminate\Http\Request;
use App\Services\WeeklyPlanService;
use App\Http\Resources\WeeklyPlanResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WeeklyActionPlanController extends Controller
{
    public function __construct(protected WeeklyPlanService $planService) {}

    // [READ] Get a list of historical weeks based on filters
    public function index(Request $request)
    {
        $year = $request->query('year');
        $month = $request->query('month');

        // Convert empty string to null to prevent SQL crashes
        $week = $request->query('week');
        if ($week === '') {
            $week = null;
        }

        $plans = WeeklyActionPlan::where('user_id', 1)
            ->filterByPeriod($year, $month, $week) // 👉 Using the new scope!
            ->with('dailyMetrics')
            ->latest('start_date')
            ->paginate(10);

        return WeeklyPlanResource::collection($plans);
    }

    public function store(StoreWeeklyActionPlanRequest $request): WeeklyPlanResource
    {
        $plan = $this->planService->createPlan($request->validated(), 1);
        return new WeeklyPlanResource($plan->load('dailyMetrics'));
    }

    public function show(WeeklyActionPlan $weeklyPlan): WeeklyPlanResource
    {
        return new WeeklyPlanResource($weeklyPlan->load('dailyMetrics'));
    }

    public function update(Request $request, WeeklyActionPlan $weeklyPlan): WeeklyPlanResource
    {
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

    public function destroy(WeeklyActionPlan $weeklyPlan): JsonResponse
    {
        $weeklyPlan->delete();

        return response()->json([
            'message' => 'Weekly plan deleted successfully.'
        ], 200);
    }

    public function current(Request $request)
    {
        $user = $request->user();

        // 1. Get the exact date for THIS Monday
        $thisMonday = Carbon::now()->startOfWeek()->format('Y-m-d');

        // 2. ONLY return a plan if it started on THIS Monday
        $plan = $user->weeklyActionPlans()
            ->with('dailyMetrics')
            ->where('start_date', $thisMonday)
            ->first();

        // 3. If no plan exists for this exact week, generate a fresh one!
        if (!$plan) {
            $plan = DB::transaction(function () use ($user) {
                $startOfWeek = Carbon::now()->startOfWeek();
                $endOfWeek = Carbon::now()->endOfWeek();

                $newPlan = $user->weeklyActionPlans()->create([
                    'start_date' => $startOfWeek->format('Y-m-d'),
                    'end_date' => $endOfWeek->format('Y-m-d'),
                    'week_number' => Carbon::now()->weekOfMonth,
                ]);

                $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

                foreach ($days as $index => $day) {
                    $newPlan->dailyMetrics()->create([
                        'day_name' => $day,
                        'record_date' => $startOfWeek->copy()->addDays($index)->format('Y-m-d'),
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

                return $newPlan->load('dailyMetrics');
            });
        }

        return response()->json([
            'data' => $plan
        ]);
    }
}
