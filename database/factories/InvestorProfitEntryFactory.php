<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorProfitEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvestorProfitEntry> */
class InvestorProfitEntryFactory extends Factory
{
    protected $model = InvestorProfitEntry::class;

    public function definition(): array
    {
        return [
            'investor_id' => Investor::factory(),
            'investment_id' => Investment::factory(),
            'quantity' => 1,
            'profit_per_unit_afn' => 10,
            'profit_amount_afn' => 10,
            'entry_type' => 'profit',
            'status' => 'pending',
            'earned_at' => now(),
            'notes' => 'Factory profit',
        ];
    }
}
