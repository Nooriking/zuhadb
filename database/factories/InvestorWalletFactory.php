<?php

namespace Database\Factories;

use App\Models\Investor;
use App\Models\InvestorWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvestorWallet> */
class InvestorWalletFactory extends Factory
{
    protected $model = InvestorWallet::class;

    public function definition(): array
    {
        return [
            'investor_id' => Investor::factory(),
            'capital_balance_afn' => 1000,
            'profit_balance_afn' => 50,
            'total_invested_afn' => 1000,
            'total_profit_earned_afn' => 50,
            'total_withdrawn_afn' => 0,
            'last_transaction_at' => now(),
        ];
    }
}
