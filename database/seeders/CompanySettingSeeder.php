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
            'company_name_local' => null,
            'phone' => '0744442863',
            'email' => 'zuhaperfume.af@gmail.com',
            'address' => '',
            'logo' => null,
            'invoice_title' => 'Invoice',
            'thank_you_message' => 'Thank you for shopping with us',
            'invoice_footer_text' => '',
            'invoice_show_logo' => true,
        ];

        if ($setting) {
            // Clear legacy vendor placeholder mistakenly stored as store local name.
            if (is_string($setting->company_name_local) && preg_match('/tezhoshan/i', $setting->company_name_local)) {
                $payload['company_name_local'] = null;
            }
            $setting->update($payload);
        } else {
            AppSetting::query()->create($payload);
        }
        AppSetting::forgetCache();

        $this->command?->info('Company settings placeholder ready (edit later in App Settings).');
    }
}
