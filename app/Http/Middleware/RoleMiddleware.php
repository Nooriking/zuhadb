<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Usage in routes:
     *   ->middleware('role:admin')
     *   ->middleware('role:admin,branch_manager')   // OR
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        // Super Admin = full access
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (empty($roles)) {
            return $next($request);
        }

        // OR — having any one of the roles is enough
        if (! $user->hasAnyRole($roles)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}