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
    $this->info("Today's date is: {$today}");

    // Fetch ALL users that have a Telegram Chat ID linked, ignoring the toggle for a moment
    $users = User::whereNotNull('telegram_chat_id')->get();

    $this->info("Total users with Telegram chat ID linked: " . $users->count());

    foreach ($users as $user) {
        $this->info("User: {$user->name} | telegram_notifications_enabled value: " . var_export($user->telegram_notifications_enabled, true));
    }

    $this->info('Diagnostic sweep completed.');
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
