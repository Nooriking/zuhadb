<?php

use App\Support\Money;

test('money multiplies and divides with a fixed scale', function () {
    expect(Money::mul('70.5', '2', 4))->toBe('141.0000')
        ->and(Money::div('100', '4', 2))->toBe('25.00');
});

test('money adds and subtracts with a fixed scale', function () {
    expect(Money::add('10.25', '0.75', 2))->toBe('11.00')
        ->and(Money::sub('11.00', '0.50', 2))->toBe('10.50')
        ->and(Money::maxZero('-1.25'))->toBe('0.00000000');
});
