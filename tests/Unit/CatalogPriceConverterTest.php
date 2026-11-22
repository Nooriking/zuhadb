<?php

use App\Support\FxBoard;
use App\Support\Money;

test('fx board equivalents convert afn amounts into usd and aed', function () {
    $board = FxBoard::fromArray([
        'usd_to_afn' => '65',
        'aed_to_afn' => Money::round(Money::div('65', '3.67', 12), 8),
        'source' => 'test',
    ]);

    $purchase = $board->equivalents('7000', 'AFN', 2);
    $sale = $board->equivalents('9000', 'AFN', 2);
    $wholesale = $board->equivalents('8500', 'AFN', 2);

    expect((float) $purchase['afn'])->toBe(7000.0)
        ->and((float) $purchase['usd'])->toBe(107.69)
        ->and((float) $sale['usd'])->toBe(138.46)
        ->and((float) $wholesale['usd'])->toBe(130.77)
        ->and((float) $purchase['aed'])->toBeGreaterThan(0);
});

test('fx board equivalents preserve original usd and aed amounts', function () {
    $board = FxBoard::fromArray([
        'usd_to_afn' => '65',
        'aed_to_afn' => '19',
        'source' => 'test',
    ]);

    $fromUsd = $board->equivalents('100', 'USD', 2);
    $fromAed = $board->equivalents('400', 'AED', 2);

    expect((float) $fromUsd['usd'])->toBe(100.0)
        ->and((float) $fromUsd['afn'])->toBe(6500.0)
        ->and((float) $fromAed['aed'])->toBe(400.0)
        ->and((float) $fromAed['afn'])->toBe(7600.0);
});

test('fx board derives usd to aed from afn pivot rates', function () {
    $board = FxBoard::fromArray([
        'usd_to_afn' => '65',
        'aed_to_afn' => '19',
        'source' => 'test',
    ]);

    expect((float) $board->usdToAed)->toEqualWithDelta(65 / 19, 0.0000001);
});
