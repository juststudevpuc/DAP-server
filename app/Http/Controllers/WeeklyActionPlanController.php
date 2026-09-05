<?php

namespace App\Http\Controllers;

use App\Models\WeeklyActionPlan;
use App\Http\Requests\StoreWeeklyActionPlanRequest;
use Illuminate\Http\Request; // We will create an UpdateRequest later if needed
use App\Services\WeeklyPlanService;
use App\Http\Resources\WeeklyPlanResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WeeklyActionPlanController extends Controller
{
    public function __construct(protected WeeklyPlanService $planService) {}

    // [READ] Get a list of all past and present weeks
    public function index()
    {
        // We use eager loading to prevent the N+1 query problem[cite: 1]
        // Note: Hardcoding user_id 1 until Sanctum is fully configured
        $plans = WeeklyActionPlan::where('user_id', 1)
            ->with('dailyMetrics')
            ->latest('start_date')
            ->paginate(10);

        return WeeklyPlanResource::collection($plans);
    }

    // [CREATE] (Already completed)
    public function store(StoreWeeklyActionPlanRequest $request): WeeklyPlanResource
    {
        $plan = $this->planService->createPlan($request->validated(), 1);
        return new WeeklyPlanResource($plan->load('dailyMetrics'));
    }

    // [READ] Get one specific week for the React dashboard
    public function show(WeeklyActionPlan $weeklyPlan): WeeklyPlanResource
    {
        // Eager load the 6 days so React gets the full grid
        return new WeeklyPlanResource($weeklyPlan->load('dailyMetrics'));
    }

    // [UPDATE] Update header targets or bottom reflections
    public function update(Request $request, WeeklyActionPlan $weeklyPlan): WeeklyPlanResource
    {
        // For production, you should move this validation to an UpdateWeeklyActionPlanRequest class[cite: 1]
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

    // [DELETE] Remove the week (Cascades to delete daily metrics automatically)
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

        // 1. Try to find the user's most recent plan
        $plan = $user->weeklyActionPlans()->with('dailyMetrics')->latest()->first();

        // 2. If no plan exists, we provision a fresh template
        if (!$plan) {
            $plan = DB::transaction(function () use ($user) {

                // 🚨 FIX 1: We must define the Carbon dates up here so we can reuse them!
                $startOfWeek = Carbon::now()->startOfWeek();
                $endOfWeek = Carbon::now()->endOfWeek();

                // A. Create the Weekly Plan parent record
                $newPlan = $user->weeklyActionPlans()->create([
                    'start_date' => $startOfWeek->format('Y-m-d'),
                    'end_date' => $endOfWeek->format('Y-m-d'),
                    'week_number' => Carbon::now()->weekOfMonth,
                ]);

                // B. Generate the default empty rows for the work week
                $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

                // 🚨 FIX 2: Added $index => $day so the loop knows which day number it is on
                foreach ($days as $index => $day) {
                    $newPlan->dailyMetrics()->create([
                        'day_name' => $day,
                        // Now $startOfWeek and $index both exist, so this math works perfectly!
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

                // C. Return the newly created plan with its metrics loaded
                return $newPlan->load('dailyMetrics');
            });
        }

        // 3. Return the data wrapped in a 'data' object
        return response()->json([
            'data' => $plan
        ]);
    }
}
