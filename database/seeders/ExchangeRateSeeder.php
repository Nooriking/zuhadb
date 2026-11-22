<?php

namespace Database\Seeders;

use App\Models\ExchangeRate;
use Illuminate\Database\Seeder;

class ExchangeRateSeeder extends Seeder
{
    /**
     * Currency IDs: 1 = AFN, 2 = USD, 3 = AED.
     */
    public function run(): void
    {
        $rates = [
            ['from' => 2, 'to' => 1, 'rate' => 56000],   // USD -> AFN
            ['from' => 3, 'to' => 1, 'rate' => 15250],   // AED -> AFN
            ['from' => 2, 'to' => 3, 'rate' => 3.6721],  // USD -> AED
        ];

        foreach ($rates as $row) {
            ExchangeRate::updateOrCreate(
                [
                    'from_currency_id' => $row['from'],
                    'to_currency_id' => $row['to'],
                    'trash' => 0,
                ],
                [
                    'rate' => $row['rate'],
                    'effective_at' => now(),
                    'source' => 'manual',
                    'created_by' => 1,
                ]
            );
        }
    }
}
