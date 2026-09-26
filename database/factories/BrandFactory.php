<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company().' Perfume',
            'country' => 'France',
            'description' => 'Demo perfume brand',
            'status' => 'active',
            'trash' => false,
        ];
    }
}
