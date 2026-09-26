<?php

// config/permissions.php

return [

    /* =========================================================
     |  ماژول‌ها بر اساس نقش
     |  ────────────────────────────────────────────────────────
     |  هر نقش چه ماژول‌هایی را می‌بیند (Sidebar + Route guard)
     ========================================================= */

    'modules' => [

        'super_admin' => ['*'],

        'admin' => [
            'dashboard',
            'users',
            'branches',
            'companies',
            'currencies',
            'exchange-rates',
            'brands',
            'categories',
            'products',
            'suppliers',
            'customers',
            'purchases',
            'inventory',
            'stock-transfers',
            'sales',
            'sale-returns',
            'investors',
            'investments',
            'investor-withdrawals',
            'investor-profit-entries',
            'investor-transactions',
            'expenses',
            'reports',
            'settings',
        ],

        'branch_manager' => [
            'dashboard',
            'users',
            'brands',
            'products',
            'suppliers',
            'customers',
            'purchases',
            'inventory',
            'stock-transfers',
            'sales',
            'sale-returns',
        ],

        'accountant' => [
            'dashboard',
            'investors',
            'investments',
            'investor-withdrawals',
            'investor-profit-entries',
            'investor-transactions',
            'expenses',
            'currencies',
            'exchange-rates',
            'reports',
            'products',
        ],

        'stock_keeper' => [
            'dashboard',
            'brands',
            'products',
            'suppliers',
            'purchases',
            'inventory',
            'stock-transfers',
        ],

        'cashier' => [
            'dashboard',
            'customers',
            'sales',
            'sale-returns',
            'inventory',
        ],

        'salesperson' => [
            'dashboard',
            'customers',
            'sales',
            'sale-returns',
        ],

        'investor' => [
            'dashboard',
            'investments',
            'investor-profit-entries',
            'investor-transactions',
        ],
    ],

    /* =========================================================
     |  دسترسی‌های دقیق درون ماژول
     |  ────────────────────────────────────────────────────────
     |  فرمت: module.action
     |  '*' = همه دسترسی‌ها
     ========================================================= */

    'permissions' => [

        'super_admin' => ['*'],

        'admin' => [
            // Users
            'users.view', 'users.create', 'users.update',
            'users.block', 'users.unblock',
            // Branches
            'branches.view', 'branches.create', 'branches.update',
            // Products
            'products.view', 'products.create', 'products.update', 'products.delete',
            // Sales
            'sales.view', 'sales.create', 'sales.update', 'sales.delete',
            // Purchases
            'purchases.view', 'purchases.create', 'purchases.update', 'purchases.delete',
            // Investors
            'investors.view', 'investors.create', 'investors.update',
            'investments.view', 'investments.create', 'investments.update',
            'withdrawals.view', 'withdrawals.create', 'withdrawals.approve',
            // Expenses
            'expenses.view', 'expenses.create', 'expenses.update',
            // Settings
            'settings.view', 'settings.update',
        ],

        'branch_manager' => [
            // Users — فقط شعبه خودش
            'users.view', 'users.create', 'users.update',
            'users.block', 'users.unblock',
            // Branches — فقط مشاهده شعبه خودش
            'branches.view',
            // Products
            'products.view', 'products.create', 'products.update',
            // Sales
            'sales.view', 'sales.create', 'sales.update',
            // Purchases
            'purchases.view', 'purchases.create', 'purchases.update',
            // Customers
            'customers.view', 'customers.create', 'customers.update',
            // Inventory
            'inventory.view', 'inventory.adjust',
            // Stock Transfers
            'stock-transfers.view', 'stock-transfers.create',
        ],

        'accountant' => [
            'investors.view', 'investors.create', 'investors.update',
            'investments.view', 'investments.create', 'investments.update',
            'withdrawals.view', 'withdrawals.create', 'withdrawals.approve', 'withdrawals.mark_paid',
            'profit-entries.view', 'profit-entries.mark_paid',
            'expenses.view', 'expenses.create', 'expenses.update',
            'currencies.view', 'currencies.create', 'currencies.update',
            'exchange-rates.view', 'exchange-rates.create',
            'reports.view',
        ],

        'stock_keeper' => [
            'products.view', 'products.create', 'products.update',
            'inventory.view', 'inventory.adjust',
            'purchases.view', 'purchases.create', 'purchases.update',
            'stock-transfers.view', 'stock-transfers.create',
            'suppliers.view', 'suppliers.create',
            'brands.view', 'brands.create',
        ],

        'cashier' => [
            'sales.view', 'sales.create', 'sales.update',
            'customers.view', 'customers.create',
            'inventory.view',
        ],

        // ⭐ فروشنده — فقط می‌تواند فروش ایجاد کند و ببیند
        'salesperson' => [
            'sales.view', 'sales.create',
            'customers.view', 'customers.create',
        ],

        'investor' => [
            'investments.view',
            'profit-entries.view',
            'transactions.view',
        ],
    ],

    /* =========================================================
     |  نقش‌های قابل ایجاد توسط هر نقش (سلسله مراتب)
     ========================================================= */

    'creatable_roles' => [

        'super_admin' => [
            'admin',
            'branch_manager',
            'cashier',
            'accountant',
            'investor',
            'salesperson',
            'stock_keeper',
        ],

        'admin' => [
            'branch_manager',
            'cashier',
            'accountant',
            'investor',
            'salesperson',
            'stock_keeper',
        ],

        'branch_manager' => [
            'cashier',
            'salesperson',
            'stock_keeper',
        ],
    ],
];