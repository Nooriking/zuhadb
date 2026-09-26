<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\Investor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investment>
 */
class InvestmentFactory extends Factory
{
    protected $model = Investment::class;

    public function definition(): array
    {
        return [
            'investor_id' => Investor::factory(),
            'investment_number' => 'INV-'.$this->faker->unique()->numerify('######'),
            'capital_amount_afn' => 10000,
            'available_amount_afn' => 10000,
            'allocated_amount_afn' => 0,
            'profit_amount_afn' => 0,
            'withdrawn_amount_afn' => 0,
            'investment_date' => now()->toDateString(),
            'status' => 'active',
            'notes' => 'Investor capital in USD',
            'trash' => false,
        ];
    }
}
