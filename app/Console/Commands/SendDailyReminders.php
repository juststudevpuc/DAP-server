<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\DailyMetric;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

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
        $this->info("Today's date is: {$today}");

        // 1. Fetch only users who have a Telegram Chat ID linked
        // AND have explicitly enabled Telegram notifications in their settings!
        $users = User::whereNotNull('telegram_chat_id')
                    ->where('telegram_notifications_enabled', true)
                    ->get();

        $this->info("Total eligible users with notifications enabled: " . $users->count());

        foreach ($users as $user) {
            // 2. Check if the user has completed their daily metric/tasks for today
            // (Adjust this condition based on how your DailyMetric table relates to users and dates)
            $hasCompletedToday = DailyMetric::where('user_id', $user->id)
                ->whereDate('created_at', $today)
                // ->where('is_completed', true) // Uncomment/adjust if you have a completion flag
                ->exists();

            if (!$hasCompletedToday) {
                $this->info("Sending reminder to: {$user->name}");
                $this->sendTelegramMessage($user->telegram_chat_id, $user->name);
            } else {
                $this->info("Skipping {$user->name} (Already completed tasks for today).");
            }
        }

        $this->info('Daily reminder sweep completed successfully.');
    }

    private function sendTelegramMessage($chatId, $userName)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');

        if (!$botToken) {
            $this->error('Telegram Bot Token is missing in environment variables.');
            return;
        }

        $message = "⚠️ អេប្រុសស្អាត {$userName},\n\nជួយបញ្ចប់ Daily Action Plan របស់អ្នកសម្រាប់ថ្ងៃនេះ។\n\nសូមចូលទៅកាន់ប្រព័ន្ធដើម្បីបញ្ចប់វា Hort mes";

        $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
        ]);

        if ($response->successful()) {
            $this->info("Successfully sent alert to {$userName}!");
        } else {
            $this->error("Failed to send alert to {$userName}. Telegram API error.");
        }
    }
}
