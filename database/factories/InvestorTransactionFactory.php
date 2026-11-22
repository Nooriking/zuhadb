<?php

namespace Database\Factories;

use App\Models\Currency;
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
        $ledger = Currency::ledger();
        $amount = 1000;

        return [
            'investor_id' => Investor::factory(),
            'investment_id' => Investment::factory(),
            'currency_id' => $ledger?->id,
            'currency_code' => $ledger?->code ?? 'AFN',
            'currency_symbol' => $ledger?->symbol ?? '؋',
            'exchange_rate' => 1,
            'type' => 'capital_deposit',
            'amount_currency' => $amount,
            'amount_afn' => $amount,
            'balance_type' => 'capital',
            'balance_before_afn' => 0,
            'balance_after_afn' => $amount,
            'description' => 'Factory capital deposit',
            'transaction_date' => now()->toDateString(),
        ];
    }

    public function capitalDeposit(): static
    {
        return $this->state(fn () => [
            'type' => 'capital_deposit',
            'balance_type' => 'capital',
        ]);
    }

    public function goodsDeposit(): static
    {
        return $this->state(fn () => [
            'type' => 'goods_deposit',
            'balance_type' => 'capital',
        ]);
    }

    public function profitEarned(): static
    {
        return $this->state(fn () => [
            'type' => 'profit_earned',
            'balance_type' => 'profit',
        ]);
    }
}
