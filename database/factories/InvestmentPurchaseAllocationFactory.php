<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\InvestmentPurchaseAllocation;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvestmentPurchaseAllocation> */
class InvestmentPurchaseAllocationFactory extends Factory
{
    protected $model = InvestmentPurchaseAllocation::class;

    public function definition(): array
    {
        return [
            'investment_id' => Investment::factory(),
            'purchase_id' => Product::factory(),
            'allocated_amount_afn' => 700,
            'notes' => 'Factory purchase allocation',
        ];
    }
}
