<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, $permission)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user || !$user->can($permission)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            return $next($request);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Token Error', 'error' => $e->getMessage()], 401);
        }
    }
}
