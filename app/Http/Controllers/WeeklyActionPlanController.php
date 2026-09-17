<?php

namespace App\Http\Controllers;

use App\Models\WeeklyActionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\WeeklyPlanService;
use App\Http\Resources\WeeklyPlanResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WeeklyActionPlanController extends Controller
{
    public function __construct(protected WeeklyPlanService $planService) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $year = $request->query('year');
        $month = $request->query('month');
        $week = $request->query('week');

        $query = $user->weeklyActionPlans()->with('dailyMetrics');

        if ($year) {
            $query->whereYear('start_date', $year);
        }

        if ($month && $month !== 'all') {
            $query->whereMonth('start_date', $month);
        }

        if ($week !== null && $week !== '') {
            $query->where('week_number', (int) $week);
        }

        $plans = $query->orderBy('start_date', 'desc')->get();

        return WeeklyPlanResource::collection($plans);
    }

    public function store(Request $request): WeeklyPlanResource
    {
        $user = $request->user();

        $validated = $request->validate([
            'week_number' => 'required|integer|min:1|max:5',
            'start_date' => 'required|date',
            'target_completed_training' => 'nullable|integer|min:0',
            'target_completed_onboarding' => 'nullable|integer|min:0',
            'target_graduated' => 'nullable|integer|min:0',
        ]);

        $startDate = Carbon::parse($validated['start_date'])->startOfWeek();
        $endDate = $startDate->copy()->endOfWeek();

        // Prevent duplicate creation for the same user, year, month, and week number
        $existing = $user->weeklyActionPlans()
            ->with('dailyMetrics')
            ->where('week_number', $validated['week_number'])
            ->whereYear('start_date', $startDate->year)
            ->whereMonth('start_date', $startDate->month)
            ->first();

        if ($existing) {
            return new WeeklyPlanResource($existing);
        }

        $plan = DB::transaction(function () use ($user, $validated, $startDate, $endDate) {
            $weeklyPlan = $user->weeklyActionPlans()->create([
                'week_number' => $validated['week_number'],
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'target_completed_training' => $validated['target_completed_training'] ?? 0,
                'target_completed_onboarding' => $validated['target_completed_onboarding'] ?? 0,
                'target_graduated' => $validated['target_graduated'] ?? 0,
                'is_completed' => false,
            ]);

            $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            foreach ($days as $index => $day) {
                $weeklyPlan->dailyMetrics()->create([
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

            return $weeklyPlan->load('dailyMetrics');
        });

        return new WeeklyPlanResource($plan);
    }

    public function show(WeeklyActionPlan $weeklyPlan): WeeklyPlanResource
    {
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
            'week_number' => 'sometimes|integer|min:1',
            'target_completed_training' => 'nullable|integer|min:0',
            'target_completed_onboarding' => 'nullable|integer|min:0',
            'target_graduated' => 'nullable|integer|min:0',
            'what_worked' => 'nullable|string',
            'what_didnt_work' => 'nullable|string',
            'what_to_improve' => 'nullable|string',
            'what_is_next' => 'nullable|string',
            'is_completed' => 'nullable|boolean',
        ]);

        $weeklyPlan->update(array_merge($validated, [
            'is_completed' => true
        ]));

        return new WeeklyPlanResource($weeklyPlan->load('dailyMetrics'));
    }

    public function completeWeek(Request $request, WeeklyActionPlan $weeklyPlan): WeeklyPlanResource
    {
        if ($weeklyPlan->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'week_number' => 'required|integer|min:1',
            'target_completed_training' => 'nullable|integer|min:0',
            'target_completed_onboarding' => 'nullable|integer|min:0',
            'target_graduated' => 'nullable|integer|min:0',
            'what_worked' => 'nullable|string',
            'what_didnt_work' => 'nullable|string',
            'what_to_improve' => 'nullable|string',
            'what_is_next' => 'nullable|string',
            'action_type' => 'nullable|string'
        ]);

        $targetWeekNumber = $validated['week_number'];
        $actionType = $validated['action_type'] ?? 'create_new';
        $user = $request->user();

        $existingPlan = $user->weeklyActionPlans()
            ->where('week_number', $targetWeekNumber)
            ->where('id', '!=', $weeklyPlan->id)
            ->where('is_completed', true)
            ->first();

        if ($existingPlan && $actionType === 'update_existing') {
            $existingPlan->update([
                'target_completed_training' => $validated['target_completed_training'] ?? $existingPlan->target_completed_training,
                'target_completed_onboarding' => $validated['target_completed_onboarding'] ?? $existingPlan->target_completed_onboarding,
                'target_graduated' => $validated['target_graduated'] ?? $existingPlan->target_graduated,
                'what_worked' => $validated['what_worked'] ?? $existingPlan->what_worked,
                'what_didnt_work' => $validated['what_didnt_work'] ?? $existingPlan->what_didnt_work,
                'what_to_improve' => $validated['what_to_improve'] ?? $existingPlan->what_to_improve,
                'what_is_next' => $validated['what_is_next'] ?? $existingPlan->what_is_next,
                'is_completed' => true
            ]);

            $weeklyPlan->dailyMetrics()->delete();
            $weeklyPlan->delete();

            return new WeeklyPlanResource($existingPlan->load('dailyMetrics'));
        }

        $weeklyPlan->update([
            'week_number' => $targetWeekNumber,
            'target_completed_training' => $validated['target_completed_training'] ?? 0,
            'target_completed_onboarding' => $validated['target_completed_onboarding'] ?? 0,
            'target_graduated' => $validated['target_graduated'] ?? 0,
            'what_worked' => $validated['what_worked'] ?? null,
            'what_didnt_work' => $validated['what_didnt_work'] ?? null,
            'what_to_improve' => $validated['what_to_improve'] ?? null,
            'what_is_next' => $validated['what_is_next'] ?? null,
            'is_completed' => true
        ]);

        return new WeeklyPlanResource($weeklyPlan->load('dailyMetrics'));
    }

    public function destroy(WeeklyActionPlan $weeklyPlan): JsonResponse
    {
        if ($weeklyPlan->user_id !== request()->user()->id) {
            abort(403, 'Unauthorized action.');
        }

        $weeklyPlan->dailyMetrics()->delete();
        $weeklyPlan->delete();

        return response()->json([
            'message' => 'Weekly plan and its daily metrics deleted successfully.'
        ], 200);
    }

    public function current(Request $request): WeeklyPlanResource
    {
        $user = $request->user();

        $activePlan = $user->weeklyActionPlans()
            ->with('dailyMetrics')
            ->where('is_completed', false)
            ->latest('start_date')
            ->first();

        if ($activePlan) {
            return new WeeklyPlanResource($activePlan);
        }

        $maxWeekNumber = $user->weeklyActionPlans()->max('week_number') ?? 0;
        $nextWeekNumber = ($maxWeekNumber % 4) + 1;

        $latestCompleted = $user->weeklyActionPlans()
            ->where('is_completed', true)
            ->latest('end_date')
            ->first();

        $startDate = $latestCompleted
            ? Carbon::parse($latestCompleted->end_date)->addDay()
            : Carbon::now()->startOfWeek();

        $endDate = $startDate->copy()->endOfWeek();

        $newPlan = DB::transaction(function () use ($user, $startDate, $endDate, $nextWeekNumber) {
            $plan = $user->weeklyActionPlans()->create([
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'week_number' => $nextWeekNumber,
                'is_completed' => false,
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

    // --- Admin: Get specific team member's weekly plan ---
   public function getMemberPlan(Request $request): JsonResponse
{
    $validated = $request->validate([
        'user_id'     => 'required',
        'year'        => 'required|integer',
        'month'       => 'required|integer|between:1,12',
        'week_number' => 'required|integer|between:1,5',
    ]);

    $year = $validated['year'];
    $month = $validated['month'];
    $weekNumber = $validated['week_number'];

    // --- CASE 1: All Users Combined Mode ---
    if ($validated['user_id'] === 'all') {
        $plans = WeeklyActionPlan::with('dailyMetrics')
            ->whereYear('start_date', $year)
            ->whereMonth('start_date', $month)
            ->where('week_number', $weekNumber)
            ->get();

        $targetTraining = 90;
        $targetOnboarding = 81;
        $targetGraduated = 81;

        $actualTraining = 0;
        $actualOnboarding = 0;
        $actualGraduated = 0;

        $categoryTotals = [
            'Company Information' => 0,
            'System Analysis' => 0,
            'Configure HR Policy' => 0,
            'Provide Lesson (Path)' => 0,
        ];

        $aggregatedDays = [];

        foreach ($plans as $plan) {
            foreach ($plan->dailyMetrics as $m) {
                $actualTraining += $m->train_completed ?? 0;
                $actualOnboarding += $m->onboard_success ?? 0;
                $actualGraduated += $m->grad_book ?? 0;

                $categoryTotals['Company Information'] += $m->onboard_company_info ?? 0;
                $categoryTotals['System Analysis'] += $m->onboard_system_analysis ?? 0;
                $categoryTotals['Configure HR Policy'] += $m->onboard_configure_hr ?? 0;
                $categoryTotals['Provide Lesson (Path)'] += $m->onboard_provide_lesson ?? 0;

                $dayName = $m->day_name ?? 'Unknown';
                if (!isset($aggregatedDays[$dayName])) {
                    $aggregatedDays[$dayName] = [
                        'id' => $dayName,
                        'day_name' => $dayName,
                        'record_date' => $m->record_date,
                        'train_expected' => 0,
                        'train_completed' => 0,
                        'train_cancel_delay' => 0,
                        'onboard_success' => 0,
                        'grad_book' => 0,
                    ];
                }

                $aggregatedDays[$dayName]['train_expected'] += $m->train_expected ?? 0;
                $aggregatedDays[$dayName]['train_completed'] += $m->train_completed ?? 0;
                $aggregatedDays[$dayName]['train_cancel_delay'] += $m->train_cancel_delay ?? 0;
                $aggregatedDays[$dayName]['onboard_success'] += $m->onboard_success ?? 0;
                $aggregatedDays[$dayName]['grad_book'] += $m->grad_book ?? 0;
            }
        }

        return response()->json([
            'success' => true,
            'is_all_users' => true,
            'target_user' => [
                'name' => 'All Team Members',
                'email' => 'Company-wide aggregate view',
                'role' => 'team',
            ],
            'plan' => [
                'target_completed_training' => $targetTraining,
                'target_completed_onboarding' => $targetOnboarding,
                'target_graduated' => $targetGraduated,
                'actual_training' => $actualTraining,
                'actual_onboarding' => $actualOnboarding,
                'actual_graduated' => $actualGraduated,
                'category_totals' => $categoryTotals,
                'start_date' => $plans->min('start_date'),
                'end_date' => $plans->max('end_date'),
                'daily_metrics' => array_values($aggregatedDays),
            ]
        ]);
    }

    // --- CASE 2: Single User Mode ---
    $targetUser = User::select('id', 'name', 'email', 'role')->findOrFail($validated['user_id']);

    $plan = WeeklyActionPlan::with('dailyMetrics')
        ->where('user_id', $validated['user_id'])
        ->whereYear('start_date', $year)
        ->whereMonth('start_date', $month)
        ->where('week_number', $weekNumber)
        ->first();

    $categoryTotals = [
        'Company Information' => 0,
        'System Analysis' => 0,
        'Configure HR Policy' => 0,
        'Provide Lesson (Path)' => 0,
    ];

    if ($plan && $plan->dailyMetrics) {
        foreach ($plan->dailyMetrics as $m) {
            $categoryTotals['Company Information'] += $m->onboard_company_info ?? 0;
            $categoryTotals['System Analysis'] += $m->onboard_system_analysis ?? 0;
            $categoryTotals['Configure HR Policy'] += $m->onboard_configure_hr ?? 0;
            $categoryTotals['Provide Lesson (Path)'] += $m->onboard_provide_lesson ?? 0;
        }
    }

    $planResource = $plan ? (new WeeklyPlanResource($plan))->resolve() : null;
    if ($planResource) {
        $planResource['category_totals'] = $categoryTotals;
    }

    return response()->json([
        'success' => true,
        'is_all_users' => false,
        'target_user' => $targetUser,
        'plan' => $planResource,
    ]);
}

    public function companySummary(Request $request): JsonResponse
{
    $validated = $request->validate([
        'year'        => 'required|integer',
        'month'       => 'nullable|integer|between:1,12',
        'week_number' => 'nullable|integer|between:1,5',
    ]);

    $query = WeeklyActionPlan::with(['user:id,name,email', 'dailyMetrics'])
        ->whereYear('start_date', $validated['year']);

    if (!empty($validated['month'])) {
        $query->whereMonth('start_date', $validated['month']);
    }

    if (!empty($validated['week_number'])) {
        $query->where('week_number', $validated['week_number']);
    }

    $plans = $query->get();

    // Aggregate metrics across all retrieved plans
    $totalTargetTraining = $plans->sum('target_completed_training');
    $totalTargetOnboarding = $plans->sum('target_completed_onboarding');
    $totalTargetGraduated = $plans->sum('target_graduated');

    $totalActualTraining = 0;
    $totalActualOnboarding = 0;
    $totalActualGraduated = 0;
    $totalDelaysCancels = 0;

    $memberBreakdown = $plans->groupBy('user_id')->map(function ($userPlans) {
        $user = $userPlans->first()->user;
        $tTarget = $userPlans->sum('target_completed_training');
        $oTarget = $userPlans->sum('target_completed_onboarding');
        $gTarget = $userPlans->sum('target_graduated');

        $tActual = 0;
        $oActual = 0;
        $gActual = 0;

        foreach ($userPlans as $p) {
            foreach ($p->dailyMetrics as $m) {
                $tActual += $m->train_completed ?? 0;
                $oActual += $m->onboard_success ?? 0;
                $gActual += $m->grad_book ?? 0;
            }
        }

        return [
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Unknown',
            'user_email' => $user?->email ?? '',
            'target_training' => $tTarget,
            'actual_training' => $tActual,
            'target_onboarding' => $oTarget,
            'actual_onboarding' => $oActual,
            'target_graduated' => $gTarget,
            'actual_graduated' => $gActual,
        ];
    })->values();

    foreach ($plans as $plan) {
        foreach ($plan->dailyMetrics as $metric) {
            $totalActualTraining += $metric->train_completed ?? 0;
            $totalActualOnboarding += $metric->onboard_success ?? 0;
            $totalActualGraduated += $metric->grad_book ?? 0;
            $totalDelaysCancels += $metric->train_cancel_delay ?? 0;
        }
    }

    return response()->json([
        'success' => true,
        'summary' => [
            'total_plans' => $plans->count(),
            'targets' => [
                'training' => $totalTargetTraining,
                'onboarding' => $totalTargetOnboarding,
                'graduated' => $totalTargetGraduated,
            ],
            'actuals' => [
                'training' => $totalActualTraining,
                'onboarding' => $totalActualOnboarding,
                'graduated' => $totalActualGraduated,
                'delays_cancels' => $totalDelaysCancels,
            ],
        ],
        'members' => $memberBreakdown,
    ]);
}
}
