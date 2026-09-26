<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SaleItem> */
class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'variant_id' => ProductVariant::factory(),
            'product_name_snapshot' => 'Factory Perfume',
            'sku_snapshot' => 'SKU-FACTORY',
            'barcode_snapshot' => '8900000099999',
            'quantity' => 1,
            'cost_price_afn' => 3000,
            'sale_price_afn' => 4500,
            'discount_afn' => 0,
            'line_total_afn' => 4500,
            'store_profit_afn' => 1500,
            'investor_profit_afn' => 0,
        ];
    }
}
