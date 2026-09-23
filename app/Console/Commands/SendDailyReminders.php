<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\DailyMetric; // Make sure this matches your daily action plan model
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

        // Find users whose individual toggle is Active and have a Telegram Chat ID linked
        $users = User::where('telegram_notifications_enabled', true)
                     ->whereNotNull('telegram_chat_id')
                     ->get();

        foreach ($users as $user) {
            // Check if the user has completed today's metrics in their daily action plan
            $hasCompletedToday = DailyMetric::where('user_id', $user->id)
                ->whereDate('date', $today)
                ->where('is_completed', true) // Adjust this field name if your completion column differs
                ->exists();

            // If their toggle is ON, but they haven't completed today, send the alert!
            if (!$hasCompletedToday) {
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
