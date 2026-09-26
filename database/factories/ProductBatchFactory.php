<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Investor;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBatch>
 */
class ProductBatchFactory extends Factory
{
    protected $model = ProductBatch::class;

    public function definition(): array
    {
        $qty = 4;
        $unit = 2800;

        return [
            'branch_id' => Branch::factory(),
            'variant_id' => ProductVariant::factory(),
            'investor_id' => Investor::factory(),
            'batch_code' => 'B-'.$this->faker->unique()->numerify('########'),
            'quantity' => $qty,
            'remaining_quantity' => $qty,
            'unit_cost_afn' => $unit,
            'total_cost_afn' => $unit * $qty,
            'profit_per_unit_afn' => 10,
            'received_date' => now()->toDateString(),
            'status' => 'active',
            'trash' => false,
        ];
    }
}
