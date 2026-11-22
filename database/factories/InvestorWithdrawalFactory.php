<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Investor;
use App\Models\InvestorWithdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvestorWithdrawal> */
class InvestorWithdrawalFactory extends Factory
{
    protected $model = InvestorWithdrawal::class;

    public function definition(): array
    {
        $ledger = Currency::ledger();
        $amount = 20;

        return [
            'investor_id' => Investor::factory(),
            'currency_id' => $ledger?->id,
            'currency_code' => $ledger?->code ?? 'AFN',
            'currency_symbol' => $ledger?->symbol ?? '؋',
            'exchange_rate' => 1,
            'withdrawal_number' => 'WD-'.$this->faker->unique()->numerify('######'),
            'type' => 'profit',
            'amount_currency' => $amount,
            'amount_afn' => $amount,
            'balance_before_afn' => 50,
            'balance_after_afn' => 30,
            'withdrawal_date' => now()->toDateString(),
            'status' => 'pending',
            'notes' => 'Factory withdrawal',
        ];
    }
}
