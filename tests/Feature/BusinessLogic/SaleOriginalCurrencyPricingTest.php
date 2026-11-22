<?php

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\PriceResolver;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Validation\ValidationException;

function saleFxEnsureCurrencies(): array
{
    PriceResolver::clearRateCache();

    $afn = Currency::query()->where('code', 'AFN')->first()
        ?? Currency::factory()->create([
            'code' => 'AFN',
            'name' => 'Afghan Afghani',
            'symbol' => '؋',
            'is_base' => true,
            'rate_to_default' => '1',
            'status' => 'active',
        ]);
    $afn->update(['is_base' => true, 'rate_to_default' => '1.00000000', 'status' => 'active', 'trash' => false]);

    $usd = Currency::query()->where('code', 'USD')->first()
        ?? Currency::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'is_base' => false,
            'rate_to_default' => '75',
            'status' => 'active',
        ]);
    $usd->update(['rate_to_default' => '75.00000000', 'status' => 'active', 'trash' => false, 'is_base' => false]);

    $aed = Currency::query()->where('code', 'AED')->first()
        ?? Currency::factory()->create([
            'code' => 'AED',
            'name' => 'UAE Dirham',
            'symbol' => 'د.إ',
            'is_base' => false,
            'rate_to_default' => '20.56',
            'status' => 'active',
        ]);
    $aed->update(['rate_to_default' => '20.56000000', 'status' => 'active', 'trash' => false, 'is_base' => false]);

    ExchangeRate::query()->delete();
    ExchangeRate::query()->create([
        'from_currency_id' => $usd->id,
        'to_currency_id' => $afn->id,
        'rate' => 75,
        'effective_at' => now()->subHour(),
        'source' => 'test-board',
        'trash' => false,
    ]);
    ExchangeRate::query()->create([
        'from_currency_id' => $aed->id,
        'to_currency_id' => $afn->id,
        'rate' => 20.56,
        'effective_at' => now()->subHour(),
        'source' => 'test-board',
        'trash' => false,
    ]);

    PriceResolver::clearRateCache();

    return compact('afn', 'usd', 'aed');
}

function saleFxSeedAfnCatalog(ProductVariant $variant, Currency $afn, float $amount = 1000): void
{
    ProductPrice::query()->create([
        'product_id' => $variant->product_id,
        'variant_id' => $variant->id,
        'price_type' => 'sale',
        'currency_id' => $afn->id,
        'currency_code' => 'AFN',
        'amount' => $amount,
        'min_qty' => 0,
        'is_active' => true,
    ]);
}

test('PriceResolver converts AFN catalog amount to USD and AED via board', function () {
    ['afn' => $afn] = saleFxEnsureCurrencies();

    $variant = ProductVariant::factory()->create();
    saleFxSeedAfnCatalog($variant, $afn, 1000);

    $resolver = app(PriceResolver::class);

    expect((float) $resolver->resolveUnitPrice($variant->id, 'AFN', 1)['amount'])->toBe(1000.0)
        ->and($resolver->resolveUnitPrice($variant->id, 'AFN', 1)['source'])->toBe('direct');

    $usdResolved = $resolver->resolveUnitPrice($variant->id, 'USD', 1);
    expect($usdResolved['source'])->toBe('converted_from_afn')
        ->and((float) $usdResolved['amount'])->toBe(round(1000 / 75, 4));

    $aedResolved = $resolver->resolveUnitPrice($variant->id, 'AED', 1);
    expect($aedResolved['source'])->toBe('converted_from_afn')
        ->and((float) $aedResolved['amount'])->toBe(round(1000 / 20.56, 4));
});

test('AFN catalog sale in USD stores board FX snapshot and AFN ledger total', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = saleFxEnsureCurrencies();

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create();

    $variant = ProductVariant::factory()->create();
    saleFxSeedAfnCatalog($variant, $afn, 1000);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 3,
        'purchase_currency_id' => $afn->id,
        'purchase_unit_amount' => '500',
        'sale_price_afn' => 1000,
        'funding_source' => 'office',
        'usd_to_afn_rate' => '75',
        'aed_to_afn_rate' => '20.56',
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => now()->toDateString(),
        'sale_currency_id' => $usd->id,
        'payment_method' => 'cash',
        'discount_amount' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'use_catalog_price' => true,
            'discount_amount' => 0,
        ]],
        'payments' => [[
            'amount' => round(1000 / 75, 2),
            'currency_id' => $usd->id,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    $item = $sale->items()->first();
    $expectedUsd = round(1000 / 75, 4);
    $expectedAfn = round($expectedUsd * 75, 2);

    expect((float) $item->sale_price_afn)->toBe($expectedAfn)
        ->and((float) $sale->total_afn)->toBe($expectedAfn)
        ->and((float) $sale->sale_amount_currency)->toBe(round($expectedUsd, 2))
        ->and($sale->sale_currency_code)->toBe('USD')
        ->and((float) $sale->usd_to_afn_rate)->toBe(75.0)
        ->and((float) $sale->aed_to_afn_rate)->toBe(20.56)
        ->and($sale->rate_source)->not->toBeEmpty()
        ->and($sale->rate_effective_at)->not->toBeNull();
});

test('createSale fails clearly when exchange_rates board is empty', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = saleFxEnsureCurrencies();

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create();
    $variant = ProductVariant::factory()->create();
    saleFxSeedAfnCatalog($variant, $afn, 1000);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 1,
        'purchase_currency_id' => $afn->id,
        'purchase_unit_amount' => '500',
        'sale_price_afn' => 1000,
        'funding_source' => 'office',
        'usd_to_afn_rate' => '75',
        'aed_to_afn_rate' => '20.56',
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    ExchangeRate::query()->delete();
    PriceResolver::clearRateCache();

    try {
        app(SaleService::class)->createSale([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'sale_date' => now()->toDateString(),
            'sale_currency_id' => $usd->id,
            'payment_method' => 'cash',
            'discount_amount' => 0,
            'items' => [[
                'variant_id' => $variant->id,
                'quantity' => 1,
                'sale_unit_amount' => '10',
                'discount_amount' => 0,
            ]],
            'payments' => [],
        ], $admin);
        expect(false)->toBeTrue('Expected ValidationException for missing board');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('exchange_rate')
            ->and(implode(' ', $e->errors()['exchange_rate']))
            ->toContain('Central exchange rate board is unavailable');
    }
});

test('getTodayRate uses effective_at board rates not future-dated rows', function () {
    ['afn' => $afn, 'usd' => $usd] = saleFxEnsureCurrencies();

    ExchangeRate::query()->create([
        'from_currency_id' => $usd->id,
        'to_currency_id' => $afn->id,
        'rate' => 99999,
        'effective_at' => now()->addDay(),
        'source' => 'future-should-ignore',
        'trash' => false,
    ]);

    PriceResolver::clearRateCache();
    $rate = app(PriceResolver::class)->getTodayRate('USD', 'AFN');

    expect($rate)->toBe(75.0);
});
