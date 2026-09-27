<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */
    'default_currency' => env('BUSINESS_CURRENCY', 'AFN'),
    'usd_currency' => 'USD',

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    */
    'default_low_stock_threshold' => (int) env('BUSINESS_LOW_STOCK', 5),

    /*
    |--------------------------------------------------------------------------
    | Sales / receipts
    |--------------------------------------------------------------------------
    */
    'sale_exchange_window_days' => (int) env('BUSINESS_EXCHANGE_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Manufacturer / support (software vendor)
    |--------------------------------------------------------------------------
    */
    'manufacturer' => env('BUSINESS_MANUFACTURER', 'Tezhoshan'),
    'support_phone' => env('BUSINESS_SUPPORT_PHONE', '0744442863'),

    /*
    |--------------------------------------------------------------------------
    | Login brute-force protection
    |--------------------------------------------------------------------------
    */
    'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
    'login_ip_max_attempts' => (int) env('LOGIN_IP_MAX_ATTEMPTS', 10),
    'login_identity_max_attempts' => (int) env('LOGIN_IDENTITY_MAX_ATTEMPTS', 8),
    'login_decay_seconds' => (int) env('LOGIN_DECAY_SECONDS', 900),

    /*
    |--------------------------------------------------------------------------
    | Database backups
    |--------------------------------------------------------------------------
    */
    'backup_keep' => (int) env('BACKUP_KEEP', 14),

];
