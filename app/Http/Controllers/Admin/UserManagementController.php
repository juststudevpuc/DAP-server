<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash; // 👈 Don't forget to import Hash

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        $query = User::select('id', 'name', 'email', 'role', 'created_at');

        if ($currentUser->role === 'admin') {
            $query->where('role', '!=', 'super_admin');
        }

        $users = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function updateRole(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|string|in:user,admin,super_admin',
        ]);

        $user = User::findOrFail($id);

        if ($request->user()->id === $user->id && $validated['role'] !== 'super_admin') {
            return response()->json([
                'message' => 'You cannot revoke your own Super Admin role.'
            ], 422);
        }

        $user->role = $validated['role'];
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
            'user'    => $user
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own super admin account.'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.'
        ]);
    }

    // --- NEW METHOD: Admin Password Reset ---
    public function adminResetPassword(Request $request, User $user): JsonResponse
    {
        // Optional security guard: Ensure requester is super_admin or admin
        if (!in_array($request->user()->role, ['super_admin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Password successfully reset for {$user->name}.",
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
            'message' => "Telegram notifications updated for {$user->name}.",
            'user' => $user
        ]);
    }
}
