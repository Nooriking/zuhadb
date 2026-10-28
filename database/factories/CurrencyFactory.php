<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Currency> */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        $code = strtoupper($this->faker->unique()->lexify('???'));

        return [
            'code' => $code,
            'name' => $code.' currency',
            'symbol' => $code,
            'decimal_places' => 2,
            'rate_to_default' => '1.00000000',
            'is_base' => false,
            'status' => 'active',
            'trash' => false,
        ];
    }
}
