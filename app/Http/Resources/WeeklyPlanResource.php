<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeeklyPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'week_number' => (int) $this->week_number,
            'start_date' => $this->start_date ? $this->start_date->toDateString() : null,
            'end_date' => $this->end_date ? $this->end_date->toDateString() : null,

            // 👉 CRITICAL: Expose completion status so the React dropdown filter sees it
            'is_completed' => (bool) $this->is_completed,

            // 1. Targets (For Header)
            'target_completed_training' => $this->target_completed_training,
            'target_completed_onboarding' => $this->target_completed_onboarding,
            'target_graduated' => $this->target_graduated,

            // 2. Last Week Summary (For Footer)
            'last_week_training_qty' => $this->last_week_training_qty,
            'last_week_training_pct' => $this->last_week_training_pct,
            'last_week_onboarding_qty' => $this->last_week_onboarding_qty,
            'last_week_onboarding_pct' => $this->last_week_onboarding_pct,
            'last_week_graduated_qty' => $this->last_week_graduated_qty,
            'last_week_graduated_pct' => $this->last_week_graduated_pct,

            // 3. Reflections
            'what_worked' => $this->what_worked,
            'what_didnt_work' => $this->what_didnt_work,
            'what_to_improve' => $this->what_to_improve,
            'what_is_next' => $this->what_is_next,

            'daily_metrics' => $this->whenLoaded('dailyMetrics'),
            'summary' => [
                'training_pct' => $this->last_week_training_pct,
                'onboarding_pct' => $this->last_week_onboarding_pct,
                'graduated_pct' => $this->last_week_graduated_pct,
            ]
        ];
    }
}
