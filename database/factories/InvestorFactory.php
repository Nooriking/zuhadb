<?php

namespace Database\Factories;

use App\Models\Investor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investor>
 */
class InvestorFactory extends Factory
{
    protected $model = Investor::class;

    public function definition(): array
    {
        return [
            'full_name' => $this->faker->name(),
            'phone' => $this->faker->unique()->numerify('070#######'),
            'email' => $this->faker->unique()->safeEmail(),
            'address' => 'Kabul',
            'status' => 'active',
            'trash' => false,
        ];
    }
}
