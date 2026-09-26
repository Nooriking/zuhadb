<?php

namespace Database\Factories;

use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBarcode>
 */
class ProductBarcodeFactory extends Factory
{
    protected $model = ProductBarcode::class;

    public function definition(): array
    {
        return [
            'variant_id' => ProductVariant::factory(),
            'barcode' => $this->faker->unique()->numerify('890##########'),
            'barcode_type' => 'EAN13',
            'is_primary' => true,
            'status' => 'active',
            'current_stock' => 25,
        ];
    }
}
