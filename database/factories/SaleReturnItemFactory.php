<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SaleReturnItem> */
class SaleReturnItemFactory extends Factory
{
    protected $model = SaleReturnItem::class;

    public function definition(): array
    {
        return [
            'sale_return_id' => SaleReturn::factory(),
            'sale_item_id' => SaleItem::factory(),
            'variant_id' => ProductVariant::factory(),
            'quantity' => 1,
            'unit_price_afn' => 4500,
            'total_afn' => 4500,
            'condition' => 'good',
            'reason' => 'Factory return item',
            'expense_amount_afn' => 0,
        ];
    }
}
