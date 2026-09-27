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
        return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
    }

    $request->validate([
        'telegram_notifications_enabled' => 'required|boolean',
        'telegram_chat_id' => 'nullable|string', // Allow saving chat ID from UI
    ]);

    $user = User::findOrFail($id);
    $user->telegram_notifications_enabled = $request->telegram_notifications_enabled;

    // If the frontend sends a chat ID, save it to the database table!
    if ($request->has('telegram_chat_id') && !empty($request->telegram_chat_id)) {
        $user->telegram_chat_id = $request->telegram_chat_id;
    }

    $user->save();

    return response()->json([
        'success' => true,
        'message' => "Telegram settings updated for {$user->name}.",
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
