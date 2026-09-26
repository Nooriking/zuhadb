<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseItem> */
class PurchaseItemFactory extends Factory
{
    protected $model = PurchaseItem::class;

    public function definition(): array
    {
        return [
            'purchase_id' => Product::factory(),
            'variant_id' => ProductVariant::factory(),
            'quantity' => 2,
            'unit_cost_afn' => 3000,
            'discount_afn' => 0,
            'total_cost_afn' => 6000,
            'profit_per_unit_afn' => 10,
        ];
    }
}
