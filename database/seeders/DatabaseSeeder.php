<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // ۱. Admin — اولین کاربر سیستم
            AdminSeeder::class,

            // ۲. ارزها
            UserSeeder::class,

            // ۳. برندها
            BrandSeeder::class,

            // ۵. دسته‌بندی‌ها
            

            // ۶. تنظیمات
            SettingSeeder::class,

            DemoDataSeeder::class,
            InvestorDashboardSeeder::class,
        ]);
    }
}