<?php

namespace Database\Factories;

use App\Models\Investor;
use App\Models\InvestorWithdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvestorWithdrawal> */
class InvestorWithdrawalFactory extends Factory
{
    protected $model = InvestorWithdrawal::class;

    public function definition(): array
    {
        return [
            'investor_id' => Investor::factory(),
            'withdrawal_number' => 'WD-'.$this->faker->unique()->numerify('######'),
            'type' => 'profit',
            'amount_afn' => 20,
            'balance_before_afn' => 50,
            'balance_after_afn' => 30,
            'withdrawal_date' => now()->toDateString(),
            'status' => 'pending',
            'notes' => 'Factory withdrawal',
        ];
    }
}
