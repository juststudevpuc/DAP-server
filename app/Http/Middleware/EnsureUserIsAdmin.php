<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Allow both regular admin and super_admin
        if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        return $next($request);
    }
}
