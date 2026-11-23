<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Usage:
     *   ->middleware('permission:sales.create')
     *   ->middleware('permission:sales.create,sales.update')   // OR
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        // Super Admin = everything
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (empty($permissions)) {
            return $next($request);
        }

        // OR — having any one of the permissions is enough
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }

            if (str_ends_with($permission, '.view')) {
                $module = substr($permission, 0, -strlen('.view'));
                if ($user->canAccessModule($module)) {
                    return $next($request);
                }
            }
        }

        abort(403, 'You do not have the required permission.');
    }
}