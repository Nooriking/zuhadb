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

    private const LEGACY_COLUMNS = [
        'source_type',
        'investment_number',
        'parent_investment_id',
        'currency_code',
        'currency_symbol',
        'capital_amount_currency',
        'capital_amount_afn',
        'capital_amount_usd',
        'capital_amount_aed',
        'available_amount_currency',
        'available_amount_afn',
        'allocated_amount_afn',
        'profit_amount_currency',
        'profit_amount_afn',
        'withdrawn_amount_afn',
        'user_id',
        'usd_to_afn_rate',
        'aed_to_afn_rate',
        'usd_to_aed_rate',
        'rate_effective_at',
        'rate_source',
    ];

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

    public function configure(): static
    {
        return $this->afterMaking(function (Investment $investment) {
            $attrs = $investment->getAttributes();

            // Map legacy capital aliases into slim schema columns when tests still pass them.
            if (($attrs['amount_afn'] ?? null) === null && array_key_exists('capital_amount_afn', $attrs)) {
                $investment->amount_afn = $attrs['capital_amount_afn'];
            }
            if (($attrs['amount_currency'] ?? null) === null && array_key_exists('capital_amount_currency', $attrs)) {
                $investment->amount_currency = $attrs['capital_amount_currency'];
            }

            foreach (self::LEGACY_COLUMNS as $column) {
                if (array_key_exists($column, $investment->getAttributes())) {
                    unset($investment->$column);
                }
            }
        });
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
