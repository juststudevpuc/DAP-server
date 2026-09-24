<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\DailyMetric;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Request as FacadesRequest;

class SendDailyReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:send-daily-reminders';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Send evening Telegram reminders to users who have not completed today tasks';

    /**
     * Execute the console command.
     */
   public function handle()
{
    $today = Carbon::today()->toDateString();

    // Find users with Telegram alerts active and chat ID linked
    $users = User::where('telegram_notifications_enabled', true)
                 ->whereNotNull('telegram_chat_id')
                 ->get();

    foreach ($users as $user) {
        // Check if this user has recorded a metric for today
        $hasRecordedToday = DailyMetric::whereHas('weeklyPlan', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->whereDate('record_date', $today)
            ->exists();

        // If they HAVEN'T recorded today, send them the automatic reminder!
        if (!$hasRecordedToday) {
            $this->sendTelegramMessage($user->telegram_chat_id, $user->name);
        }
    }

    $this->info('Daily Telegram reminders sweep completed successfully.');
}

    private function sendTelegramMessage($chatId, $userName)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        $message = "⚠️ អេប្រុសស្អាត {$userName},\n\n ជួយបញ្ចប់ Daily Action Plan របស់អ្នកសម្រាប់ថ្ងៃនេះ។\n\n សូមចូលទៅកាន់ប្រព័ន្ធដើម្បីបញ្ចប់វា Hort mes";

        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
        ]);
    }

}
