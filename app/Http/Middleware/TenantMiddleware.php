<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get tenant_id from route parameter or request
        $tenantId = $request->route('tenant_id') ??
                   $request->input('tenant_id') ??
                   $request->input('tenant');

        if ($tenantId) {
            // If tenant_id is specified in request, ensure user belongs to it
            if ($user->tenant_id != $tenantId) {
                return response()->json(['error' => 'Access denied to tenant'], 403);
            }
        } else {
            // If no tenant specified, ensure user has a tenant
            if (!$user->tenant_id) {
                return response()->json(['error' => 'User not associated with any tenant'], 403);
            }
        }

        return $next($request);
    }
}
