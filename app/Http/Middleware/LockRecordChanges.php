<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LockRecordChanges
{
    /**
     * ویرایش، حذف، زباله و تغییر وضعیت فقط برای ادمین و سوپر ادمین.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin() || $user->isAdmin()) {
            return $next($request);
        }

        $name = (string) ($request->route()?->getName() ?? '');

        if (in_array($request->method(), ['PUT', 'PATCH', 'DELETE'], true)) {
            abort(403, 'Only an admin can change this record.');
        }

        foreach (['.edit', 'trash', 'restore', 'forceDelete', 'force-delete', 'toggle'] as $needle) {
            if ($name !== '' && str_contains($name, $needle)) {
                abort(403, 'Only an admin can do this.');
            }
        }

        return $next($request);
    }
}
