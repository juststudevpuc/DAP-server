<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TelegramController extends Controller
{
    /**
     * 1. Generate deep-link for the logged-in user to click in React.
     */
    public function generateLink(Request $request): JsonResponse
    {
        $user = $request->user();

        // Generate a random 32-character token
        $token = Str::random(32);
        $user->update(['telegram_verify_token' => $token]);

        $botUsername = env('TELEGRAM_BOT_USERNAME'); // e.g. MySystemAlertBot
        $link = "https://t.me/{$botUsername}?start={$token}";

        return response()->json([
            'link' => $link,
            'is_linked' => !empty($user->telegram_chat_id),
        ]);
    }

    /**
     * 2. Webhook receiver called by Telegram servers when the user clicks 'Start'.
     */
    public function handleWebhook(Request $request, string $secret): JsonResponse
    {
        // 1. Security Check
        if ($secret !== env('TELEGRAM_WEBHOOK_SECRET')) {
            abort(403, 'Unauthorized');
        }

        $messageText = $request->input('message.text', '');
        $chat = $request->input('message.chat');

        // If there is no chat object, ignore the request
        if (!$chat) {
            return response()->json(['ok' => true]);
        }

        // 2. Extract Telegram Account Details
        $chatId = $chat['id'];
        $firstName = $chat['first_name'] ?? 'Telegram User';

        // 3. Process the /start command
        if (Str::startsWith($messageText, '/start')) {

            // Check if there is a deep-link token (e.g., "/start xyz123")
            $token = trim(Str::after($messageText, '/start '));

            if (!empty($token) && $token !== '/start') {
                // SCENARIO A: User came from the React Frontend Link
                $user = User::where('telegram_verify_token', $token)->first();

                if ($user) {
                    $user->update([
                        'telegram_chat_id' => (string) $chatId,
                        'telegram_verify_token' => null,
                    ]);
                    $this->replyToTelegram($chatId, "✅ Hi {$user->name}, your account is connected!");
                }
            } else {
                // SCENARIO B: Unknown User clicked Start directly in Telegram
                $user = User::firstOrCreate(
                    ['telegram_chat_id' => (string) $chatId], // Search by Chat ID
                    [
                        'name' => $firstName,
                        'email' => $chatId . '@telegram.bot',
                        'password' => bcrypt(Str::random(16))
                    ]
                );

                $this->replyToTelegram($chatId, "👋 Welcome {$firstName}! Your account has been registered in our system.");
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Helper method to send a direct reply to Telegram
     */
    private function replyToTelegram($chatId, $message)
    {
        Http::post("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);
    }

    /**
     * 3. Send an alert to any user by their User ID.
     */
    public static function sendAlertToUser(int $userId, string $message): bool
    {
        $user = User::find($userId);

        if (!$user || !$user->telegram_chat_id) {
            return false;
        }

        $response = Http::post("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/sendMessage", [
            'chat_id' => $user->telegram_chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);

        return $response->successful();
    }

    /**
     * 4. Fetch all users who have a connected Telegram account.
     */
    public function getLinkedUsers(): JsonResponse
    {
        $users = User::whereNotNull('telegram_chat_id')
            ->select('id', 'name', 'email', 'telegram_chat_id')
            ->get();

        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }

    /**
     * 5. API Endpoint to trigger an alert from the React frontend.
     */
    public function triggerAlert(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'message' => 'required|string',
        ]);

        $success = self::sendAlertToUser($request->user_id, $request->message);

        if ($success) {
            return response()->json(['success' => true, 'message' => 'Alert sent successfully!']);
        }

        return response()->json(['success' => false, 'message' => 'Failed to send alert. User might not be connected.'], 400);
    }

    /**
     * 6. Receive an image from React and send it to the user's Telegram.
     */
    public function sendImageToUser(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Check if user has completed the one-time activation
        if (!$user->telegram_chat_id) {
            return response()->json([
                'success' => false,
                'needs_linking' => true,
                'message' => 'Please connect your Telegram account first.'
            ], 403);
        }

        // 2. Validate the incoming image
        $request->validate([
            'image' => 'required|file|mimes:png,jpg,jpeg|max:10240', // Max 10MB
        ]);

        $file = $request->file('image');

        // 3. Send to Telegram using multipart/form-data
        $response = Http::attach(
            'photo', file_get_contents($file->getRealPath()), 'weekly_plan.png'
        )->post("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/sendPhoto", [
            'chat_id' => $user->telegram_chat_id,
            'caption' => 'Here is your Action Plan!',
        ]);

        if ($response->successful()) {
            return response()->json(['success' => true, 'message' => 'Image sent to Telegram!']);
        }

        return response()->json(['success' => false, 'message' => 'Telegram API rejected the file.'], 500);
    }

    /**
     * 7. Send selected multi-select daily reports to Telegram.
     */
    public function sendDailyImagesToTelegram(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->telegram_chat_id) {
            return response()->json([
                'success' => false,
                'message' => 'Please connect your Telegram account first.'
            ], 403);
        }

        $request->validate([
            'image' => 'required|file|mimes:png,jpg,jpeg|max:10240',
            'days' => 'required',
            'week_number' => 'required|integer',
            'year' => 'required|integer',
            'month' => 'required|integer',
        ]);

        $selectedDays = $request->input('days');
        if (is_string($selectedDays)) {
            $selectedDays = json_decode($selectedDays, true);
        }

        if (empty($selectedDays) || !is_array($selectedDays)) {
            return response()->json([
                'success' => false,
                'message' => 'Please select at least one valid day.'
            ], 422);
        }

        $file = $request->file('image');

        $formattedDays = collect($selectedDays)->map(function($day) {
            return match($day) {
                'Mon' => 'Monday',
                'Tue' => 'Tuesday',
                'Wed' => 'Wednesday',
                'Thu' => 'Thursday',
                'Fri' => 'Friday',
                'Sat' => 'Saturday',
                default => $day
            };
        });

        $count = $formattedDays->count();
        if ($count === 1) {
            $dayTitle = "Daily action on " . $formattedDays->first();
        } elseif ($count === 2) {
            $dayTitle = "Daily action on " . $formattedDays->get(0) . " and " . $formattedDays->get(1);
        } else {
            $last = $formattedDays->pop();
            $dayTitle = "Daily action on " . $formattedDays->implode(', ') . " and " . $last;
        }

        $response = Http::attach(
            'photo', file_get_contents($file->getRealPath()), 'daily_plan.png'
        )->post("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/sendPhoto", [
            'chat_id' => $user->telegram_chat_id,
            'caption' => "{$dayTitle}",
        ]);

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'message' => 'Daily image report sent successfully to Telegram!'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Telegram API rejected the file.'
        ], 500);
    }
    public function sendWeeklyImagesToTelegram(Request $request): JsonResponse
{
    $user = $request->user();

    if (!$user->telegram_chat_id) {
        return response()->json([
            'success' => false,
            'message' => 'Please connect your Telegram account first.'
        ], 403);
    }

    $request->validate([
        'image' => 'required|file|mimes:png,jpg,jpeg|max:10240',
        'week_number' => 'required|integer',
        'month' => 'required|integer',
        'year' => 'required|integer',
        'caption' => 'nullable|string',
    ]);

    $file = $request->file('image');
    // Default caption fallback if not provided
    $caption = $request->input('caption', 'Weekly Action Plan Report');

    $response = Http::attach(
        'photo', file_get_contents($file->getRealPath()), 'weekly_plan.png'
    )->post("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/sendPhoto", [
        'chat_id' => $user->telegram_chat_id,
        'caption' => "📊 " . $caption,
    ]);

    if ($response->successful()) {
        return response()->json([
            'success' => true,
            'message' => 'Weekly image report sent successfully to Telegram!'
        ]);
    }

    return response()->json([
        'success' => false,
        'message' => 'Telegram API rejected the file.'
    ], 500);
}
}
