<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvestorTransaction> */
class InvestorTransactionFactory extends Factory
{
    protected $model = InvestorTransaction::class;

    public function definition(): array
    {
        return [
            'investor_id' => Investor::factory(),
            'investment_id' => Investment::factory(),
            'type' => 'capital_deposit',
            'amount_afn' => 1000,
            'balance_type' => 'capital',
            'balance_before_afn' => 0,
            'balance_after_afn' => 1000,
            'description' => 'Factory capital deposit',
            'transaction_date' => now()->toDateString(),
        ];
    }
}
