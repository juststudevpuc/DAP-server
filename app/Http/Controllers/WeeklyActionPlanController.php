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

class WeeklyActionPlanController extends Controller
{
    public function __construct(protected WeeklyPlanService $planService) {}

    /**
     * Calculate the Monday start_date for a given Year, Month, and Week (1-5).
     * Rule: Sunday is a holiday. If the 1st is Sunday, Week 1 starts on Monday the 2nd.
     */
    private function resolveExpectedStartDate(int $year, int $month, int $week): string
    {
        $firstDayOfMonth = Carbon::createFromDate($year, $month, 1)->startOfDay();

        // If the 1st of the month is Sunday (Holiday), first working day is Monday the 2nd
        $firstWorkingDay = $firstDayOfMonth->isSunday()
            ? $firstDayOfMonth->copy()->addDay()
            : $firstDayOfMonth;

        $week1Monday = $firstWorkingDay->copy()->startOfWeek(Carbon::MONDAY);

        return $week1Monday->addWeeks($week - 1)->format('Y-m-d');
    }

    /**
     * Get all unique Monday start_dates belonging to this Month (excluding overlap with next month).
     */
    private function resolveMonthStartDates(int $year, int $month): array
    {
        $nextMonth = $month === 12 ? 1 : $month + 1;
        $nextMonthYear = $month === 12 ? $year + 1 : $year;
        $nextMonthWeek1 = $this->resolveExpectedStartDate($nextMonthYear, $nextMonth, 1);

        $dates = [];
        for ($w = 1; $w <= 5; $w++) {
            $d = $this->resolveExpectedStartDate($year, $month, $w);
            if ($d !== $nextMonthWeek1) {
                $dates[] = $d;
            }
        }
        return array_values(array_unique($dates));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $year = $request->query('year');
        $month = $request->query('month');
        $week = $request->query('week');

        $query = $user->weeklyActionPlans()->with('dailyMetrics');

        if ($year && $month && $month !== 'all' && $week !== null && $week !== '') {
            $expectedStartDate = $this->resolveExpectedStartDate((int) $year, (int) $month, (int) $week);

            // Safe auto-fix: if a row was previously saved with week_number = 1 when it should be 5,
            // just update its week_number to 5 (never deletes any data!)
            $week5StartDate = $this->resolveExpectedStartDate((int) $year, (int) $month, 5);
            if ((int) $week === 1 && $week5StartDate !== $expectedStartDate) {
                $user->weeklyActionPlans()
                    ->where('week_number', 1)
                    ->whereDate('start_date', $week5StartDate)
                    ->update(['week_number' => 5]);
            }

            $query->whereDate('start_date', $expectedStartDate);
        } else {
            if ($year && $month && $month !== 'all') {
                $monthDates = $this->resolveMonthStartDates((int) $year, (int) $month);
                $query->whereIn(DB::raw('DATE(start_date)'), $monthDates);
            } elseif ($year) {
                $query->whereYear('start_date', $year);
            }

            if ($week !== null && $week !== '') {
                $query->where('week_number', (int) $week);
            }
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

        // Match by exact Monday start_date so boundary weeks never collide
        $existing = $user->weeklyActionPlans()
            ->with('dailyMetrics')
            ->whereDate('start_date', $startDate->format('Y-m-d'))
            ->first();

        if ($existing) {
            if ((int) $existing->week_number !== (int) $validated['week_number']) {
                $existing->update(['week_number' => (int) $validated['week_number']]);
            }
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
            'week_number' => 'sometimes|integer|min:1|max:5',
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
            'week_number' => 'required|integer|min:1|max:5',
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

        // Scope by start_date so completing a week never overwrites another month's week
        $existingPlan = $user->weeklyActionPlans()
            ->where('week_number', $targetWeekNumber)
            ->whereDate('start_date', $weeklyPlan->start_date->format('Y-m-d'))
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

        $latestCompleted = $user->weeklyActionPlans()
            ->where('is_completed', true)
            ->latest('end_date')
            ->first();

        $startDate = $latestCompleted
            ? Carbon::parse($latestCompleted->end_date)->addDay()->startOfWeek()
            : Carbon::now()->startOfWeek();

        $endDate = $startDate->copy()->endOfWeek();

        // Derive the week number (1-5) from the week's end_date month so it never wraps 26/10/2026 to Week 1
        $nextWeekNumber = min((int) ceil($endDate->day / 7), 5);

        $existingForDate = $user->weeklyActionPlans()
            ->with('dailyMetrics')
            ->whereDate('start_date', $startDate->format('Y-m-d'))
            ->first();

        if ($existingForDate) {
            return new WeeklyPlanResource($existingForDate);
        }

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

        $year = (int) $validated['year'];
        $month = (int) $validated['month'];
        $weekNumber = (int) $validated['week_number'];
        $expectedStartDate = $this->resolveExpectedStartDate($year, $month, $weekNumber);

        // --- CASE 1: All Users Combined Mode ---
        if ($validated['user_id'] === 'all') {
            $plans = WeeklyActionPlan::with('dailyMetrics')
                ->whereDate('start_date', $expectedStartDate)
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
                    $actualGraduated += ($m->grad_certificate ?? 0) + ($m->grad_hr_policy ?? 0) + ($m->grad_book ?? 0);

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
                    $aggregatedDays[$dayName]['grad_book'] += ($m->grad_certificate ?? 0) + ($m->grad_hr_policy ?? 0) + ($m->grad_book ?? 0);
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
            ->whereDate('start_date', $expectedStartDate)
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

        $year = (int) $validated['year'];
        $month = !empty($validated['month']) ? (int) $validated['month'] : null;
        $weekNumber = !empty($validated['week_number']) ? (int) $validated['week_number'] : null;

        $query = WeeklyActionPlan::with(['user:id,name,email', 'dailyMetrics']);

        if ($year && $month && $weekNumber) {
            $expectedStartDate = $this->resolveExpectedStartDate($year, $month, $weekNumber);
            $query->whereDate('start_date', $expectedStartDate);
        } elseif ($year && $month) {
            $monthDates = $this->resolveMonthStartDates($year, $month);
            $query->whereIn(DB::raw('DATE(start_date)'), $monthDates);
        } else {
            $query->whereYear('start_date', $year);
            if ($weekNumber) {
                $query->where('week_number', $weekNumber);
            }
        }

        $plans = $query->get();

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
                $totalActualGraduated += ($metric->grad_certificate ?? 0) + ($metric->grad_hr_policy ?? 0) + ($metric->grad_book ?? 0);
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
