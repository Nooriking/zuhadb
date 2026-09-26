<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'phone' => '070'.$this->faker->numerify('#######'),
            'address' => 'Kabul',
            'notes' => null,
            'current_due_afn' => 0,
            'credit_limit_afn' => 0,
            'status' => 'active',
            'trash' => false,
        ];
    }
}
