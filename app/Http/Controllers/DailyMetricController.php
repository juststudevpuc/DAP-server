<?php

namespace App\Http\Controllers;

use App\Models\DailyMetric;
use App\Http\Requests\UpdateDailyMetricRequest;
use Illuminate\Http\JsonResponse;

class DailyMetricController extends Controller
{
    /**
     * Update the specified daily metrics in storage.
     * We use UpdateDailyMetricRequest to handle validation outside the controller[cite: 1].
     */
    public function update(UpdateDailyMetricRequest $request, DailyMetric $dailyMetric): JsonResponse
    {
        // $request->validated() contains only the safe, validated integer counts
        $dailyMetric->update($request->validated());

        return response()->json([
            'message' => 'Daily metrics updated successfully.',
            'data' => $dailyMetric
        ]);
    }
}
