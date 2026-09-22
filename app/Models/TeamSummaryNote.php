<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamSummaryNote extends Model
{
    use HasFactory;

    protected $table = 'team_summary_notes';

    protected $fillable = [
        'year',
        'month',
        'week_number',
        'what_worked',
        'what_didnt_work',
        'what_to_improve',
        'what_is_next',
    ];
}
