<?php

use App\Models\User;

/**
 * Black box: each request is judged only by the HTTP result a user would see.
 */
function shopSections(): array
{
    return [
        '/dashboard',
        '/notifications',
        '/search',
        '/branches',
        '/branches/create',
        '/users',
        '/users/create',
        '/app-settings',
        '/brands',
        '/brands/create',
        '/products',
        '/products/create',
        '/products/receive',
        '/suppliers',
        '/suppliers/create',
        '/customers',
        '/customers/create',
        '/customer-transactions',
        '/receipts',
        '/inventory',
        '/inventory/low-stock',
        '/stock-transfers',
        '/stock-transfers/create',
        '/sales',
        '/sales/create',
        '/sale-returns',
        '/sale-returns/create',
        '/investors',
        '/investors/create',
        '/investor-withdrawals',
        '/investor-withdrawals/create',
        '/investments',
        '/investments/create',
        '/investment-purchase-allocations',
        '/investment-product-allocations',
        '/investor-profit-entries',
        '/investor-transactions',
        '/expenses',
        '/expenses/create',
        '/currencies',
        '/currencies/create',
        '/product-price-histories',
        '/settings/profile',
    ];
}

test('a guest is sent to login from every shop section', function () {
    $blocked = [];

    foreach (shopSections() as $path) {
        $status = $this->get($path)->status();
        if ($status !== 302) {
            $blocked[$path] = $status;
        }
    }

    expect($blocked)->toBe([]);
});

test('a super admin can open every shop section', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

    $failed = [];

    foreach (shopSections() as $path) {
        $status = $this->get($path)->status();
        if ($status !== 200) {
            $failed[$path] = $status;
        }
    }

    expect($failed)->toBe([]);
});

test('each role only opens the sections that role is allowed to use', function (string $role, array $allowed, array $denied) {
    $this->actingAs(User::factory()->create(['role' => $role]));

    $wrong = [];

    foreach ($allowed as $path) {
        $status = $this->get($path)->status();
        if ($status !== 200) {
            $wrong["allow {$path}"] = $status;
        }
    }

    foreach ($denied as $path) {
        $status = $this->get($path)->status();
        if ($status !== 403) {
            $wrong["deny {$path}"] = $status;
        }
    }

    expect($wrong)->toBe([]);
})->with([
    'admin' => [
        User::ROLE_ADMIN,
        ['/dashboard', '/users', '/products', '/products/receive', '/sales', '/sales/create', '/inventory', '/customers', '/investors', '/expenses', '/app-settings', '/currencies'],
        ['/branches'],
    ],
    'branch manager' => [
        User::ROLE_BRANCH_MANAGER,
        ['/dashboard', '/dashboard/branch', '/users', '/users/create', '/products', '/sales', '/inventory', '/customers', '/stock-transfers'],
        ['/investors', '/expenses', '/branches', '/app-settings', '/currencies'],
    ],
    'cashier' => [
        User::ROLE_CASHIER,
        ['/dashboard', '/dashboard/cashier', '/sales', '/sales/create', '/customers', '/inventory', '/sale-returns', '/receipts'],
        ['/products', '/users', '/investors', '/expenses', '/stock-transfers', '/currencies'],
    ],
    'salesperson' => [
        User::ROLE_SALESPERSON,
        [
            '/dashboard',
            '/dashboard/cashier',
            '/sales',
            '/sales/create',
            '/products',
            '/products/create',
            '/products/receive',
            '/customers',
            '/customer-transactions',
            '/sale-returns',
            '/receipts',
            '/inventory',
        ],
        ['/users', '/investors', '/expenses', '/stock-transfers', '/app-settings', '/currencies'],
    ],
    'stock keeper' => [
        User::ROLE_STOCK_KEEPER,
        ['/dashboard', '/dashboard/stock', '/products', '/products/receive', '/inventory', '/suppliers', '/stock-transfers', '/brands'],
        ['/sales', '/customers', '/users', '/investors'],
    ],
    'accountant' => [
        User::ROLE_ACCOUNTANT,
        ['/dashboard', '/dashboard/accountant', '/products', '/investors', '/investments', '/expenses', '/investor-withdrawals', '/currencies'],
        ['/sales', '/customers', '/users', '/inventory'],
    ],
    'investor' => [
        User::ROLE_INVESTOR,
        ['/dashboard', '/dashboard/investor'],
        ['/sales', '/products', '/investors', '/expenses', '/users'],
    ],
]);
