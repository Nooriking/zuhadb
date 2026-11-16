<?php

use App\Services\CurrencyService;
use App\Services\InvestorMultiCurrencyDashboardService;

test('weighted average FX from AFN equivalents matches the 65.20 example', function () {
    $fx = app(CurrencyService::class);

    // 10 @ 65, 5 @ 65.30, 5 @ 65.50 → 1304 AFN / 20 USD = 65.20
    $rate = $fx->weightedAverageFxFromEquivalents([
        ['original_amount' => '10', 'afn_amount' => '650'],
        ['original_amount' => '5', 'afn_amount' => '326.50'],
        ['original_amount' => '5', 'afn_amount' => '327.50'],
    ]);

    expect($rate)->not->toBeNull()
        ->and((float) $rate)->toBe(65.2);

    $equivalentRate = $fx->weightedAverageRate([
        ['rate' => '65', 'amount' => '10'],
        ['rate' => '65.30', 'amount' => '5'],
        ['rate' => '65.50', 'amount' => '5'],
    ]);

    expect((float) $equivalentRate)->toBe(65.2);
});

test('weighted average FX refuses empty or zero rows', function () {
    $fx = app(CurrencyService::class);

    expect($fx->weightedAverageFxFromEquivalents([]))->toBeNull()
        ->and($fx->weightedAverageFxFromEquivalents([
            ['original_amount' => '0', 'afn_amount' => '100'],
        ]))->toBeNull();
});

test('remaining inventory weighted average uses remaining quantities only', function () {
    $service = app(InvestorMultiCurrencyDashboardService::class);

    // Full batch: 10 USD @ 65 → remaining 5 after sale keeps half of AFN cost.
    $result = $service->weightedAverageFromRemaining([
        ['original_amount' => '5', 'afn_amount' => '325', 'quantity' => 5],
        ['original_amount' => '5', 'afn_amount' => '326.50', 'quantity' => 5],
        ['original_amount' => '5', 'afn_amount' => '327.50', 'quantity' => 5],
    ]);

    expect($result['available'])->toBeTrue()
        ->and((float) $result['original_amount'])->toBe(15.0)
        ->and((float) $result['historical_afn'])->toBe(979.0)
        ->and(round((float) $result['rate'], 4))->toBe(65.2667)
        ->and($result['units'])->toBe(15)
        ->and($result['kind'])->toBe('historical_weighted_average');
});
