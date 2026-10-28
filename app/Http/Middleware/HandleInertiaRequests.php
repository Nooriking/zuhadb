<?php

namespace App\Http\Middleware;

use App\Models\ActivityNotification;
use App\Models\AppSetting;
use App\Models\Currency;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $isAuthenticated = (bool) $user;

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user
                    ? array_merge(
                        $user->only([
                            'id',
                            'name',
                            'email',
                            'username',
                            'role',
                            'branch_id',
                            'avatar',
                        ]),
                        [
                            'two_factor_enabled' => $user->two_factor_enabled,
                        ],
                    )
                    : null,
            ],

            'permissions' => fn () => $isAuthenticated ? [
                'role'           => $user->role,
                'is_admin'       => in_array($user->role, ['admin', 'super_admin'], true),
                'is_super_admin' => $user->isSuperAdmin(),
                'permissions'    => $user->getPermissions(),
                'modules'        => $user->getAccessibleModules(),
            ] : null,

            'access' => fn () => $isAuthenticated ? [
                'role'           => $user->role,
                'is_admin'       => $user->isAdmin() || $user->isSuperAdmin(),
                'is_super_admin' => $user->isSuperAdmin(),
                'modules'        => $user->isSuperAdmin()
                    ? ['*']
                    : $user->getAccessibleModules(),
            ] : null,

            // Load after first paint — avoids 2 DB queries on every navigation.
            'notifications' => $isAuthenticated
                ? Inertia::defer(fn () => [
                    'unread' => ActivityNotification::query()
                        ->where('user_id', $user->id)
                        ->whereNull('read_at')
                        ->count(),
                    'items' => ActivityNotification::query()
                        ->where('user_id', $user->id)
                        ->latest('id')
                        ->limit(8)
                        ->get(['id', 'section', 'title', 'body', 'read_at', 'created_at']),
                ], 'notifications')
                : ['unread' => 0, 'items' => []],

            // Cached across Inertia navigations until full reload.
            'shop' => Inertia::once(fn () => AppSetting::bill()),

            'currency' => fn () => Currency::sharedPayload(),

            'flash' => [
                'success'      => fn () => $request->session()->get('success'),
                'error'        => fn () => $request->session()->get('error'),
                'created_sale' => fn () => $request->session()->get('created_sale'),
            ],
        ]);
    }
}
