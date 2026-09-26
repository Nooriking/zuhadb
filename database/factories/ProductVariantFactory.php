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
            'purchase_price_afn' => 3000,
            'sale_price_afn' => 4500,
            'wholesale_price_afn' => 4000,
            'wholesale_min_qty' => 6,
            'profit_per_unit' => 1500,
            'stock_quantity' => 25,
            'minimum_stock' => 5,
            'trash' => false,
        ];
    }
}
