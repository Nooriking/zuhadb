<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Setting> */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'key' => 'factory.'.$this->faker->unique()->lexify('????'),
            'value' => '1',
            'type' => 'string',
            'group' => 'general',
            'description' => 'Factory setting',
            'is_public' => false,
        ];
    }
}
