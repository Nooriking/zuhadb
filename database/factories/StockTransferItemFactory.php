<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockTransferItem> */
class StockTransferItemFactory extends Factory
{
    protected $model = StockTransferItem::class;

    public function definition(): array
    {
        return [
            'stock_transfer_id' => StockTransfer::factory(),
            'variant_id' => ProductVariant::factory(),
            'quantity' => 1,
        ];
    }
}
