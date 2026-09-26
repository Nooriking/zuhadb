<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\InvestmentProductAllocation;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvestmentProductAllocation> */
class InvestmentProductAllocationFactory extends Factory
{
    protected $model = InvestmentProductAllocation::class;

    public function definition(): array
    {
        return [
            'investment_id' => Investment::factory(),
            'batch_id' => ProductBatch::factory(),
            'variant_id' => ProductVariant::factory(),
            'funded_quantity' => 2,
            'sold_quantity' => 0,
            'remaining_quantity' => 2,
            'cost_per_unit_afn' => 3000,
            'profit_per_unit_afn' => 10,
            'capital_recovered_afn' => 0,
            'profit_earned_afn' => 0,
            'status' => 'active',
        ];
    }
}
