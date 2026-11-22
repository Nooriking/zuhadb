<?php

namespace App\Http\Middleware;

use App\Models\ActivityNotification;
use App\Models\AppSetting;
use App\Models\Currency;
use App\Services\ExchangeService;
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

            'exchange' => fn () => $isAuthenticated
                ? $this->sharedExchangeRates()
                : [
                    'usd_to_afn' => null,
                    'aed_to_afn' => null,
                    'usd_to_aed' => null,
                    'effective_at' => null,
                    'source' => null,
                ],

            'flash' => [
                'success'      => fn () => $request->session()->get('success'),
                'error'        => fn () => $request->session()->get('error'),
                'created_sale' => fn () => $request->session()->get('created_sale'),
            ],
        ]);
    }

    /**
     * @return array{usd_to_afn: string|null, aed_to_afn: string|null, usd_to_aed: string|null, effective_at: string|null, source: string|null}
     */
    private function sharedExchangeRates(): array
    {
        $empty = [
            'usd_to_afn' => null,
            'aed_to_afn' => null,
            'usd_to_aed' => null,
            'effective_at' => null,
            'source' => null,
        ];

        try {
            $exchange = app(ExchangeService::class);
            $rates = $exchange->getCurrentRates();
            $usdSnap = $exchange->snapshot('USD', 'AFN');

            return [
                'usd_to_afn' => $rates['usd_to_afn'] ?? null,
                'aed_to_afn' => $rates['aed_to_afn'] ?? null,
                'usd_to_aed' => $rates['usd_to_aed'] ?? null,
                'effective_at' => $usdSnap['effective_at'] ?? null,
                'source' => $usdSnap['source'] ?? null,
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }
}
