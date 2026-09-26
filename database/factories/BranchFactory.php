<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'name' => 'Branch '.$this->faker->unique()->numerify('###'),
            'code' => 'BR-'.$this->faker->unique()->numerify('####'),
            'phone' => '0700000000',
            'email' => $this->faker->unique()->safeEmail(),
            'country' => 'Afghanistan',
            'province' => 'Kabul',
            'district' => 'District 10',
            'address' => 'Kabul, Afghanistan',
            'status' => 'active',
            'trash' => false,
        ];
    }
}
