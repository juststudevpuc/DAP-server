<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule; // 💡 Import the Schedule facade

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 💡 Use Schedule::command instead of $schedule->command
Schedule::command('telegram:send-daily-reminders')->dailyAt('18:00');
