<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class WeeklyActionPlan extends Model
{
    protected $fillable = [
        'user_id',
        'week_number',
        'start_date',
        'end_date',
        'target_completed_training',
        'target_completed_onboarding',
        'target_graduated',
        'last_week_training_qty',
        'last_week_training_pct',
        'last_week_onboarding_qty',
        'last_week_onboarding_pct',
        'last_week_graduated_qty',
        'last_week_graduated_pct',
        'what_worked',
        'what_didnt_work',
        'what_to_improve',
        'what_is_next'
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'last_week_training_pct' => 'decimal:2',
            'last_week_onboarding_pct' => 'decimal:2',
            'last_week_graduated_pct' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(DailyMetric::class, 'weekly_plan_id');
    }

    // 👉 Add this scope to allow easy filtering of historical data
    public function scopeFilterByPeriod($query, $year, $month, $week = null)
    {
        if ($year) {
            $query->whereYear('start_date', $year);
        }
        if ($month) {
            $query->whereMonth('start_date', $month);
        }
        // If your weeks are stored as a number (1-5) in the week_number column
        if ($week) {
            $query->where('week_number', $week);
        }
        return $query;
    }
}
