<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    /**
     * بررسی دسترسی به ماژول.
     *
     * استفاده در routes:
     *   ->middleware('module:sales')
     *   ->middleware('module:products')
     *   ->middleware('module:users,branches')   // OR
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $user = $request->user();

        // اگر لاگین نکرده
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        // Super Admin = دسترسی کامل
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // اگر هیچ ماژولی مشخص نشده — فقط لاگین کافی است
        if (empty($modules)) {
            return $next($request);
        }

        // OR — اگر یکی از ماژول‌ها را داشت کافی است
        foreach ($modules as $module) {
            if ($user->canAccessModule($module)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have access to this module.');
    }
}