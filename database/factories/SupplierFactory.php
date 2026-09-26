<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Supplies',
            'phone' => '078'.$this->faker->numerify('#######'),
            'email' => $this->faker->unique()->companyEmail(),
            'contact_person' => $this->faker->name(),
            'address' => 'Kabul',
            'opening_balance' => 0,
            'status' => 'active',
            'trash' => false,
        ];
    }
}
