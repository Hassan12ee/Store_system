<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
public function handle($request, Closure $next)
{
    try {
        $user = JWTAuth::parseToken()->authenticate();

        if (is_null($user->email_verified_at)) {
            return response()->json(['error' => 'Email not verified'], 403);
        }

        return $next($request);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
}

}
