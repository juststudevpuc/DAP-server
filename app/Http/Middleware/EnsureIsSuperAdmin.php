<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized. Super Admin access required.'], 403);
        }
        return $next($request);
    }
}
