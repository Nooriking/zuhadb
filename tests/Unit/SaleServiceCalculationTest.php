<?php

use App\Services\CustomerLedgerService;
use App\Services\SaleNotifier;
use App\Services\SaleService;

test('SaleService calculateTotal subtracts discount and floors at zero', function () {
    $service = new SaleService(
        app(CustomerLedgerService::class),
        app(SaleNotifier::class),
    );

    expect($service->calculateTotal(5000, 500))->toBe(4500.0)
        ->and($service->calculateTotal(100, 150))->toBe(0.0)
        ->and($service->calculateTotal(99.999, 0))->toBe(100.0);
});
