<?php

use App\Support\Money;

test('money multiplies and divides with a fixed scale', function () {
    expect(Money::mul('70.5', '2', 4))->toBe('141.0000')
        ->and(Money::div('100', '4', 2))->toBe('25.00')
        ->and(Money::round('1.20', 2))->toBe('1.20');
});
