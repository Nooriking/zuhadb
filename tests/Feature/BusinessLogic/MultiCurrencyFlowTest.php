<?php

/**
 * End-to-end multi-currency scenario (sale -> payment -> return -> investment -> withdrawal).
 *
 * Rate convention: rate_to_default = AFN per 1 unit of foreign currency (1 USD = 56000 AFN).
 *   USD -> AFN : multiply   |   AFN -> USD : divide
 */

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\PriceResolver;
use App\Services\PurchaseService;
use App\Services\SaleService;

function mcEnsureCurrencies(): array
{
    PriceResolver::clearRateCache();

    $afn = Currency::query()->where('code', 'AFN')->first()
        ?? Currency::factory()->create([
            'code' => 'AFN', 'name' => 'Afghan Afghani', 'symbol' => '؋',
            'is_base' => true, 'rate_to_default' => '1', 'status' => 'active',
        ]);
    $afn->update(['is_base' => true, 'rate_to_default' => '1.00000000', 'status' => 'active', 'trash' => false]);

    $usd = Currency::query()->where('code', 'USD')->first()
        ?? Currency::factory()->create([
            'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$',
            'is_base' => false, 'rate_to_default' => '56000', 'status' => 'active',
        ]);
    $usd->update(['rate_to_default' => '56000.00000000', 'status' => 'active', 'trash' => false, 'is_base' => false]);

    $aed = Currency::query()->where('code', 'AED')->first()
        ?? Currency::factory()->create([
            'code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ',
            'is_base' => false, 'rate_to_default' => '15250', 'status' => 'active',
        ]);
    $aed->update(['rate_to_default' => '15250.00000000', 'status' => 'active', 'trash' => false, 'is_base' => false]);

    return compact('afn', 'usd', 'aed');
}

/** @return array{0: Branch, 1: User, 2: ProductVariant} */
function mcStockedVariant(Currency $afn, int $qty = 5): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 1000,
        'sale_price_afn' => 4500,
    ]);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => $qty,
        'purchase_currency_id' => $afn->id,
        'purchase_unit_amount' => '1000',
        'sale_price_afn' => 4500,
        'funding_source' => 'office',
        'usd_to_afn_rate' => '56000',
        'aed_to_afn_rate' => '15250',
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    return [$branch, $admin, $variant];
}

// ---------------------------------------------------------------------------
// Step 1: SaleController@store / SaleService::createSale
// ---------------------------------------------------------------------------

test('step1: USD sale at 0.0804 fills sale_items currency columns and customer USD balance', function () {
    ['afn' => $afn, 'usd' => $usd] = mcEnsureCurrencies();
    [$branch, $admin, $variant] = mcStockedVariant($afn);
    $customer = Customer::factory()->create(['current_due_afn' => 0]);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => now()->toDateString(),
        'sale_currency_id' => $usd->id,
        'usd_to_afn_rate' => '56000',
        'aed_to_afn_rate' => '15250',
        'payment_method' => 'credit',
        'discount_amount' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_unit_amount' => '0.0804',
            'discount_amount' => 0,
        ]],
        'payments' => [],
    ], $admin);

    $item = $sale->items()->first();

    // 0.0804 USD x 56000 = 4502.40 AFN
    expect($item->sale_currency_code)->toBe('USD')
        ->and((float) $item->sale_unit_amount)->toBe(0.0804)
        ->and((float) $item->sale_line_amount)->toBe(0.08)
        ->and((float) $item->sale_price_afn)->toBe(4502.4)
        ->and((float) $item->line_total_afn)->toBe(4502.4);

    $balance = CustomerBalance::query()
        ->where('customer_id', $customer->id)
        ->where('currency_code', 'USD')
        ->first();

    expect($balance)->not->toBeNull()
        ->and((float) $balance->balance_afn)->toBe(4502.4)
        ->and((float) $customer->fresh()->current_due_afn)->toBe(4502.4);
});

test('step1: catalog price (AFN 4500) is converted to USD via PriceResolver (AFN / 56000)', function () {
    ['afn' => $afn, 'usd' => $usd] = mcEnsureCurrencies();
    [$branch, $admin, $variant] = mcStockedVariant($afn);
    $customer = Customer::factory()->create(['current_due_afn' => 0]);

    ProductPrice::query()->create([
        'product_id' => $variant->product_id,
        'variant_id' => $variant->id,
        'price_type' => 'sale',
        'currency_id' => $afn->id,
        'currency_code' => 'AFN',
        'amount' => 4500,
        'min_qty' => 0,
        'is_active' => true,
    ]);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => now()->toDateString(),
        'sale_currency_id' => $usd->id,
        'usd_to_afn_rate' => '56000',
        'aed_to_afn_rate' => '15250',
        'payment_method' => 'credit',
        'discount_amount' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'use_catalog_price' => true,
            'discount_amount' => 0,
        ]],
        'payments' => [],
    ], $admin);

    $item = $sale->items()->first();

    expect($item->sale_currency_code)->toBe('USD')
        ->and((float) $item->sale_unit_amount)->toBe(0.0804)   // 4500 / 56000
        ->and((float) $item->sale_price_afn)->toBe(4502.4)
        ->and(CustomerBalance::query()->where('customer_id', $customer->id)->where('currency_code', 'USD')->exists())->toBeTrue();
});
