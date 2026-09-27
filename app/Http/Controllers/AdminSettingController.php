<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class AdminSettingController extends Controller
{
    public function getSystemSettingsData()
    {
        $users = User::select('id', 'name', 'email', 'telegram_chat_id', 'telegram_notifications_enabled')->get();

        return response()->json([
            'success' => true,
            'users' => $users,
        ]);
    }

   public function toggleUserTelegram(Request $request, $id)
{
    if (!$request->user()->isSuperAdmin() && !$request->user()->isAdmin()) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized.'
        ], 403);
    }

    $request->validate([
        'telegram_notifications_enabled' => 'required|boolean',
    ]);

    $user = User::findOrFail($id);
    $user->telegram_notifications_enabled = $request->telegram_notifications_enabled;
    $user->save();

    // 🔍 Return the user object so we can inspect it in the browser Network tab
    return response()->json([
        'success' => true,
        'message' => "Telegram alerts updated for {$user->name}.",
        'updated_user' => $user
    ]);
}

    public function sendInstantTestReminder(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if (!$user->telegram_chat_id) {
            return response()->json([
                'success' => false,
                'message' => "{$user->name} has not linked their Telegram account yet."
            ], 400);
        }

        $botToken = env('TELEGRAM_BOT_TOKEN');

        if (!$botToken) {
            return response()->json([
                'success' => false,
                'message' => 'Telegram Bot Token is missing in environment variables.'
            ], 500);
        }

        $message = "⚠️ អេប្រុសស្អាត {$user->name},\n\n ជួយបញ្ចប់ Daily Action Plan របស់អ្នកសម្រាប់ថ្ងៃនេះ។\n\n Hort mes!";

        $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $user->telegram_chat_id,
            'text' => $message,
        ]);

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'message' => "Reminder successfully sent to {$user->name}!"
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to communicate with Telegram API.'
        ], 500);
    }
}
