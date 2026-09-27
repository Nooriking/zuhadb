<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Machine-bound license lock
    |--------------------------------------------------------------------------
    |
    | When enabled, the app only runs on the PC that was activated with a
    | signed license key. Copying the project to another computer will lock it.
    |
    | Delivery workflow:
    | 1) On client PC: php artisan license:machine-id
    | 2) On your PC (with LICENSE_SECRET): php artisan license:issue {machine-id} --customer="Shop"
    | 3) On client PC: php artisan license:activate "{key}"
    |    or open /license and paste the key.
    |
    | Keep LICENSE_SECRET private. Never give it to the client.
    |
    */

    'enabled' => (bool) env('LICENSE_ENABLED', env('APP_ENV') === 'production'),

    /*
    | Vendor PC only. Keep false on client installs so they cannot mint new keys.
    */
    'allow_issue' => (bool) env('LICENSE_ALLOW_ISSUE', false),

    'secret' => (string) env('LICENSE_SECRET', env('APP_KEY')),

    'product' => 'zuha-perfume-pos',

    'file' => storage_path('app/license.json'),

    'support_phone' => (string) env('LICENSE_SUPPORT_PHONE', env('BUSINESS_SUPPORT_PHONE', '0744442863')),

    'support_email' => (string) env('LICENSE_SUPPORT_EMAIL', 'zuhaperfume.af@gmail.com'),

];
