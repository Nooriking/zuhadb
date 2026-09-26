<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'name' => $this->faker->unique()->words(2, true),
            'gender' => 'unisex',
            'status' => 'active',
            'description' => 'Demo perfume',
            'trash' => false,
        ];
    }
}
