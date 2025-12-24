<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TenantScopeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Ensure the authenticated user belongs to a tenant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required'
            ], 401);
        }

        // Check if user belongs to a tenant
        if (!$user->tenant) {
            return response()->json([
                'error' => 'No tenant found for this user'
            ], 403);
        }

        // Add tenant to request for easy access in controllers
        $request->merge(['tenant' => $user->tenant]);

        return $next($request);
    }
}
