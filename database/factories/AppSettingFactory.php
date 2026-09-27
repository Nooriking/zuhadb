<?php

namespace Database\Factories;

use App\Models\AppSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AppSetting> */
class AppSettingFactory extends Factory
{
    protected $model = AppSetting::class;

    public function definition(): array
    {
        return [
            'name' => 'ZUHA PERFUME',
            'company_name' => 'ZUHA PERFUME',
            'company_name_local' => 'Tezhoshan Support',
            'phone' => '0744442863',
            'email' => 'support@tezhoshan.test',
            'address' => 'Kabul, Afghanistan',
            'website' => 'Tezhoshan',
        ];
    }
}
