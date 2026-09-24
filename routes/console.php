<?php


use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Change dailyAt from '18:00' to '22:00' (10:00 PM)
Schedule::command('telegram:send-daily-reminders')->everyMinute();
