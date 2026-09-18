<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserManagementController extends Controller
{
   public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        $query = User::select('id', 'name', 'email', 'role', 'created_at');

        // If the logged-in user is a regular 'admin' (not a super_admin),
        // hide other super_admins from their list view.
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

        // Direct property assignment bypasses $fillable restrictions
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
            // Prevent super admin from deleting their own account
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
}
