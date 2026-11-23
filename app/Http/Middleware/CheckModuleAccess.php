<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    /**
     * Check module access.
     *
     * Usage in routes:
     *   ->middleware('module:sales')
     *   ->middleware('module:products')
     *   ->middleware('module:users,branches')   // OR
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $user = $request->user();

        // Not logged in
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        // Super Admin = full access
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // No modules specified — login alone is enough
        if (empty($modules)) {
            return $next($request);
        }

        // OR — having any one of the modules is enough
        foreach ($modules as $module) {
            if ($user->canAccessModule($module)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have access to this module.');
    }
}
