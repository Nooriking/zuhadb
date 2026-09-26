<?php

namespace App\Http\Middleware;

use App\Models\ActivityNotification;
use App\Models\AppSetting;
use Illuminate\Http\Request;
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

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user,
            ],

            // ⭐ Middleware-based permissions (role-based, not config)
            'permissions' => fn () => $user ? [
                'role'           => $user->role,
                'is_admin'       => in_array($user->role, ['admin', 'super_admin'], true),
                'is_super_admin' => $user->isSuperAdmin(),
                'permissions'    => $user->getPermissions(),
                'modules'        => $user->getAccessibleModules(),
            ] : null,

            // جدا از permissions صفحه، تا صفحات Users و Branches منو را پاک نکنند
            'access' => fn () => $user ? [
                'role'           => $user->role,
                'is_admin'       => $user->isAdmin() || $user->isSuperAdmin(),
                'is_super_admin' => $user->isSuperAdmin(),
                'modules'        => $user->isSuperAdmin()
                    ? ['*']
                    : $user->getAccessibleModules(),
            ] : null,

            'notifications' => fn () => $user ? (function () use ($user) {
                $items = ActivityNotification::query()
                    ->where('user_id', $user->id)
                    ->latest('id')
                    ->limit(8)
                    ->get(['id', 'section', 'title', 'body', 'read_at', 'created_at']);

                return [
                    'unread' => ActivityNotification::query()
                        ->where('user_id', $user->id)
                        ->whereNull('read_at')
                        ->count(),
                    'items' => $items,
                ];
            })() : ['unread' => 0, 'items' => []],

            'shop' => fn () => AppSetting::bill(),

            'flash' => [
                'success'      => fn () => $request->session()->get('success'),
                'error'        => fn () => $request->session()->get('error'),
                'created_sale' => fn () => $request->session()->get('created_sale'),
            ],
        ]);
    }
}