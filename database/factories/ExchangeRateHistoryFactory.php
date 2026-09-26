<?php

namespace Database\Factories;

use App\Models\ExchangeRateHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExchangeRateHistory> */
class ExchangeRateHistoryFactory extends Factory
{
    protected $model = ExchangeRateHistory::class;

    public function definition(): array
    {
        return [
            'rate_date' => now()->toDateString(),
            'usd_to_afn' => 70,
            'notes' => 'Factory rate',
        ];
    }
}
