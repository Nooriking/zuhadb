<?php

namespace Database\Factories;

use App\Models\ProductPriceHistory;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductPriceHistory> */
class ProductPriceHistoryFactory extends Factory
{
    protected $model = ProductPriceHistory::class;

    public function definition(): array
    {
        return [
            'variant_id' => ProductVariant::factory(),
            'old_purchase_price_afn' => 2800,
            'new_purchase_price_afn' => 3000,
            'old_sale_price_afn' => 4200,
            'new_sale_price_afn' => 4500,
            'change_type' => 'both',
            'reason' => 'Factory price change',
        ];
    }
}
