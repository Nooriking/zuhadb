<?php

use App\Models\Sale;
use App\Support\OriginalCurrencyMoney;

test('forSale uses sale_amount_currency and sale_currency_code without conversion', function () {
    $sale = new Sale([
        'total_afn' => 7000,
        'sale_currency_code' => 'USD',
        'sale_currency_symbol' => '$',
        'sale_amount_currency' => 100,
        'usd_to_afn_rate' => 70,
        'aed_to_afn_rate' => 19,
    ]);

    $money = OriginalCurrencyMoney::forSale($sale);

    expect($money['known'])->toBeTrue()
        ->and($money['code'])->toBe('USD')
        ->and($money['amount'])->toBe(100.0)
        ->and($money['ledger_afn'])->toBe(7000.0);
});

test('unknown sale currency is not silently remapped to AFN', function () {
    $sale = new Sale([
        'total_afn' => 500,
        'sale_currency_code' => null,
        'sale_amount_currency' => null,
    ]);

    $money = OriginalCurrencyMoney::forSale($sale);

    expect($money['known'])->toBeFalse()
        ->and($money['code'])->toBeNull()
        ->and($money['amount'])->toBeNull()
        ->and($money['label'])->toBe('Unknown');
});

test('sumByCurrency keeps AFN USD AED separate and never cross-adds', function () {
    $sum = OriginalCurrencyMoney::sumByCurrency([
        ['code' => 'AFN', 'amount' => 1000],
        ['code' => 'USD', 'amount' => 20],
        ['code' => 'AED', 'amount' => 50],
        ['code' => 'USD', 'amount' => 5],
    ]);

    expect($sum['buckets']['AFN']['total'])->toBe(1000.0)
        ->and($sum['buckets']['USD']['total'])->toBe(25.0)
        ->and($sum['buckets']['AED']['total'])->toBe(50.0);
});

test('netBuckets subtracts returns per currency only', function () {
    $sales = OriginalCurrencyMoney::sumByCurrency([
        ['code' => 'USD', 'amount' => 100],
        ['code' => 'AFN', 'amount' => 7000],
    ])['buckets'];

    $returns = OriginalCurrencyMoney::sumByCurrency([
        ['code' => 'USD', 'amount' => 10],
        ['code' => 'AED', 'amount' => 5],
    ])['buckets'];

    $net = OriginalCurrencyMoney::netBuckets($sales, $returns, true);
    $byCode = collect($net)->keyBy('code');

    expect((float) $byCode['USD']['total'])->toBe(90.0)
        ->and((float) $byCode['AFN']['total'])->toBe(7000.0)
        ->and((float) $byCode['AED']['total'])->toBe(-5.0);
});

test('fromLedgerAfn converts with the sale FX snapshot only', function () {
    $sale = new Sale([
        'sale_currency_code' => 'USD',
        'usd_to_afn_rate' => 70,
        'aed_to_afn_rate' => 19,
    ]);

    expect(OriginalCurrencyMoney::fromLedgerAfn(1400, 'USD', $sale))->toBe(20.0)
        ->and(OriginalCurrencyMoney::fromLedgerAfn(380, 'AED', $sale))->toBe(20.0)
        ->and(OriginalCurrencyMoney::fromLedgerAfn(100, 'AFN', $sale))->toBe(100.0);
});
