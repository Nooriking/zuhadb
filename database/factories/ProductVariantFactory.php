<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => '100ml',
            'sku' => 'SKU-'.$this->faker->unique()->numerify('######'),
            'volume_ml' => 100,
            'concentration' => 'edp',
            'stock_quantity' => 25,
            'trash' => false,
        ];
    }
}
