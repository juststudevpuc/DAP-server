<?php
namespace App\Http\Controllers;

use App\Models\WeeklyActionPlan;
use App\Http\Requests\StoreWeeklyActionPlanRequest;
use Illuminate\Http\Request; // We will create an UpdateRequest later if needed
use App\Services\WeeklyPlanService;
use App\Http\Resources\WeeklyPlanResource;
use Illuminate\Http\JsonResponse;

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
}
