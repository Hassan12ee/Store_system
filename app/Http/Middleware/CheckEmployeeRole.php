<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
class CheckEmployeeRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $employee = JWTAuth::parseToken()->authenticate();

        if (!$employee) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        foreach ($roles as $role) {
            if (in_array($role, $employee->roles ?? [])) {
                return $next($request);
            }
        }

        return response()->json(['error' => 'Access Denied'], 403);
    }
}
