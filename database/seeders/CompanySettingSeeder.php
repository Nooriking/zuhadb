<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class CompanySettingSeeder extends Seeder
{
    public function run(): void
    {
        $setting = AppSetting::query()->first();

        $payload = [
            'name' => 'ZUHA PERFUME',
            'company_name' => 'ZUHA PERFUME',
            'company_name_local' => 'Tezhoshan Support',
            'phone' => '0744442863',
            'email' => 'zuhaperfume.af@gmail.com',
            'address' => '',
            'logo' => null,
        ];

        if ($setting) {
            $setting->update($payload);
        } else {
            AppSetting::query()->create($payload);
        }

        $this->command?->info('Company settings placeholder ready (edit later in App Settings).');
    }
}
