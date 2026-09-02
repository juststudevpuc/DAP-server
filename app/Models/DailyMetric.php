<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyMetric extends Model
{
    protected $fillable = [
        'weekly_plan_id', 'day_name', 'record_date',
        'train_expected', 'train_completed', 'train_cancel_delay',
        'onboard_company_info', 'onboard_system_analysis', 'onboard_configure_hr', 'onboard_provide_lesson', 'onboard_success',
        'grad_certificate', 'grad_hr_policy', 'grad_book', 'comment'
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'datetime',
        ];
    }

    public function weeklyPlan(): BelongsTo
    {
        return $this->belongsTo(WeeklyActionPlan::class, 'weekly_plan_id');
    }
}
