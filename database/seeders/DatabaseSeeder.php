<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Clean handoff seed: only Super Admin + empty company settings.
 * Demo / factory seeders are optional and NOT called here.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SuperAdminSeeder::class,
            CompanySettingSeeder::class,
        ]);
    }
}
