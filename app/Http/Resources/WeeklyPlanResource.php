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
            'week_number' => $this->week_number,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            // Eager-loaded relationship automatically formatted
            'daily_metrics' => $this->whenLoaded('dailyMetrics'),
            'summary' => [
                'training_pct' => $this->last_week_training_pct,
                'onboarding_pct' => $this->last_week_onboarding_pct,
                'graduated_pct' => $this->last_week_graduated_pct,
            ]
        ];
    }
}
