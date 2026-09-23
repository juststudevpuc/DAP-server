<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
 use App\Models\User;
 
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
    $request->validate([
        'telegram_notifications_enabled' => 'required|boolean',
    ]);

    $user = User::findOrFail($id);
    $user->telegram_notifications_enabled = $request->telegram_notifications_enabled;
    $user->save();

    return response()->json([
        'success' => true,
        'message' => "Telegram alerts updated for {$user->name}.",
    ]);
}
}
