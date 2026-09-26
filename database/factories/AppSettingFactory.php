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
            'name' => 'Zuha Perfume',
            'company_name' => 'Zuha Perfume Shop',
            'phone' => '0700000000',
            'email' => 'shop@zuha.test',
            'address' => 'Kabul, Afghanistan',
        ];
    }
}
