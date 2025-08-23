<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
                'error_code' => 'AUTHENTICATION_REQUIRED'
            ], 401);
        }

        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Admin access required',
                'error_code' => 'ADMIN_REQUIRED'
            ], 403);
        }

        return $next($request);
    }
}
