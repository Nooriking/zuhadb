<?php

namespace Database\Factories;

use App\Models\Currency;
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
        $amount = 10000;

        return [
            'investor_id' => Investor::factory(),
            'currency_id' => fn () => Currency::ledger()?->id ?? Currency::factory(),
            'exchange_rate' => 1,
            'amount_currency' => $amount,
            'amount_afn' => $amount,
            'investment_date' => now()->toDateString(),
            'status' => 'active',
            'notes' => 'Factory investment',
            'trash' => false,
        ];
    }

    public function usd(float $amount = 100, float $rate = 56000): static
    {
        return $this->state(fn () => [
            'currency_id' => Currency::query()->where('code', 'USD')->value('id')
                ?? Currency::factory()->state(['code' => 'USD', 'symbol' => '$']),
            'exchange_rate' => $rate,
            'amount_currency' => $amount,
            'amount_afn' => $amount * $rate,
        ]);
    }

    public function aed(float $amount = 100, float $rate = 15250): static
    {
        return $this->state(fn () => [
            'currency_id' => Currency::query()->where('code', 'AED')->value('id')
                ?? Currency::factory()->state(['code' => 'AED', 'symbol' => 'د.إ']),
            'exchange_rate' => $rate,
            'amount_currency' => $amount,
            'amount_afn' => $amount * $rate,
        ]);
    }
}
