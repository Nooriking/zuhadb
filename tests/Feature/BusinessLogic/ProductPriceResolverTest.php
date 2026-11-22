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

function ppEnsureRates(): array
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
            'rate_to_default' => '56000',
            'status' => 'active',
        ]);
    $usd->update(['rate_to_default' => '56000.00000000', 'status' => 'active', 'trash' => false, 'is_base' => false]);

    $aed = Currency::query()->where('code', 'AED')->first()
        ?? Currency::factory()->create([
            'code' => 'AED',
            'name' => 'UAE Dirham',
            'symbol' => 'د.إ',
            'is_base' => false,
            'rate_to_default' => '15250',
            'status' => 'active',
        ]);
    $aed->update(['rate_to_default' => '15250.00000000', 'status' => 'active', 'trash' => false, 'is_base' => false]);

    // Sales/PriceResolver require Central Exchange Board rows — never catalog-only.
    ExchangeRate::query()->delete();
    ExchangeRate::query()->create([
        'from_currency_id' => $usd->id,
        'to_currency_id' => $afn->id,
        'rate' => 56000,
        'effective_at' => now()->subHour(),
        'source' => 'test-board',
        'trash' => false,
    ]);
    ExchangeRate::query()->create([
        'from_currency_id' => $aed->id,
        'to_currency_id' => $afn->id,
        'rate' => 15250,
        'effective_at' => now()->subHour(),
        'source' => 'test-board',
        'trash' => false,
    ]);

    PriceResolver::clearRateCache();

    return compact('afn', 'usd', 'aed');
}

function ppSeedSalePrice(ProductVariant $variant, Currency $currency, float $amount): ProductPrice
{
    return ProductPrice::query()->create([
        'product_id' => $variant->product_id,
        'variant_id' => $variant->id,
        'price_type' => 'sale',
        'currency_id' => $currency->id,
        'currency_code' => $currency->code,
        'amount' => $amount,
        'min_qty' => 0,
        'is_active' => true,
    ]);
}

test('PriceResolver resolveUnitPrice returns direct USD catalog price', function () {
    ['afn' => $afn, 'usd' => $usd] = ppEnsureRates();

    $variant = ProductVariant::factory()->create();
    ppSeedSalePrice($variant, $afn, 56000);
    ppSeedSalePrice($variant, $usd, 12.5);

    $resolved = app(PriceResolver::class)->resolveUnitPrice($variant->id, 'USD', 1);

    expect($resolved['source'])->toBe('direct')
        ->and($resolved['currency'])->toBe('USD')
        ->and((float) $resolved['amount'])->toBe(12.5);
});

test('PriceResolver converts from AFN when USD price is missing', function () {
    ['afn' => $afn] = ppEnsureRates();

    $variant = ProductVariant::factory()->create();
    ppSeedSalePrice($variant, $afn, 112000);

    $resolved = app(PriceResolver::class)->resolveUnitPrice($variant->id, 'USD', 1);

    expect($resolved['source'])->toBe('converted_from_afn')
        ->and($resolved['currency'])->toBe('USD')
        ->and((float) $resolved['amount'])->toBe(2.0);
});

test('PriceResolver getTodayRate uses exchange_rates board and fails without it', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = ppEnsureRates();
    $resolver = app(PriceResolver::class);

    PriceResolver::clearRateCache();
    expect($resolver->getTodayRate('USD', 'AFN'))->toBe(56000.0);
    expect(round($resolver->getTodayRate('AFN', 'USD'), 8))->toBe(round(1 / 56000, 8));
    expect($resolver->getTodayRate('USD', 'USD'))->toBe(1.0);

    ExchangeRate::query()->delete();
    PriceResolver::clearRateCache();

    expect(fn () => $resolver->getTodayRate('USD', 'AFN'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('USD sale stores AFN ledger from board and FX snapshot on sale', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = ppEnsureRates();

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create();

    $variant = ProductVariant::factory()->create();
    ppSeedSalePrice($variant, $usd, 15);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 5,
        'purchase_currency_id' => $afn->id,
        'purchase_unit_amount' => '40000',
        'sale_price_afn' => 56000,
        'funding_source' => 'office',
        'usd_to_afn_rate' => '56000',
        'aed_to_afn_rate' => '15250',
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => now()->toDateString(),
        'sale_currency_id' => $usd->id,
        'usd_to_afn_rate' => '56000',
        'aed_to_afn_rate' => '15250',
        'payment_method' => 'cash',
        'discount_amount' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'use_catalog_price' => true,
            'discount_amount' => 0,
        ]],
        'payments' => [[
            'amount' => 15,
            'currency_id' => $usd->id,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    $item = $sale->items()->first();

    expect((float) $item->sale_price_afn)->toBe(840000.0)
        ->and((float) $sale->total_afn)->toBe(840000.0)
        ->and((float) $sale->sale_amount_currency)->toBe(15.0)
        ->and($sale->sale_currency_code)->toBe('USD')
        ->and((float) $sale->usd_to_afn_rate)->toBe(56000.0)
        ->and((float) $sale->aed_to_afn_rate)->toBe(15250.0)
        ->and($sale->rate_source)->not->toBeEmpty();
});

test('USD payment against AFN sale reduces remaining_due_afn correctly', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = ppEnsureRates();

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create(['current_due_afn' => 0]);

    $variant = ProductVariant::factory()->create();
    ppSeedSalePrice($variant, $afn, 112000);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 2,
        'purchase_currency_id' => $afn->id,
        'purchase_unit_amount' => '10000',
        'sale_price_afn' => 112000,
        'funding_source' => 'office',
        'usd_to_afn_rate' => '56000',
        'aed_to_afn_rate' => '15250',
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => now()->toDateString(),
        'sale_currency_id' => $afn->id,
        'usd_to_afn_rate' => '56000',
        'aed_to_afn_rate' => '15250',
        'payment_method' => 'credit',
        'discount_amount' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_unit_amount' => '112000',
            'discount_amount' => 0,
        ]],
        'payments' => [],
    ], $admin);

    expect((float) $sale->remaining_due_afn)->toBe(112000.0);

    $receipt = app(SaleService::class)->addPayment($sale->fresh(), [
        'amount' => 1,
        'currency_id' => $usd->id,
        'payment_method' => 'cash',
    ], $admin);

    $sale->refresh();
    $customer->refresh();

    expect((float) $receipt->amount_afn)->toBe(56000.0)
        ->and((float) $sale->paid_afn)->toBe(56000.0)
        ->and((float) $sale->remaining_due_afn)->toBe(56000.0)
        ->and((float) $customer->current_due_afn)->toBe(56000.0);
});
