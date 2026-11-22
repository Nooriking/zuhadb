<?php

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorWallet;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Support\Carbon;

function tcEnsureCurrencies(): array
{
    $afn = Currency::query()->where('code', 'AFN')->first()
        ?? Currency::factory()->create([
            'code' => 'AFN',
            'name' => 'Afghan Afghani',
            'symbol' => '؋',
            'is_base' => true,
            'rate_to_default' => '1',
            'status' => 'active',
        ]);
    $afn->update(['is_base' => true, 'rate_to_default' => '1', 'status' => 'active', 'trash' => false]);

    $usd = Currency::query()->where('code', 'USD')->first()
        ?? Currency::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'is_base' => false,
            'rate_to_default' => '70',
            'status' => 'active',
        ]);
    $usd->update(['rate_to_default' => '70', 'status' => 'active', 'trash' => false, 'is_base' => false]);

    $aed = Currency::query()->where('code', 'AED')->first()
        ?? Currency::factory()->create([
            'code' => 'AED',
            'name' => 'UAE Dirham',
            'symbol' => 'د.إ',
            'is_base' => false,
            'rate_to_default' => '19',
            'status' => 'active',
        ]);
    $aed->update(['rate_to_default' => '19', 'status' => 'active', 'trash' => false, 'is_base' => false]);

    return compact('afn', 'usd', 'aed');
}

function tcPostBaseRates(CurrencyService $fx, Currency $usd, Currency $aed, Currency $afn, string $at = '2026-01-01 08:00:00'): void
{
    $fx->postRate($usd, $afn, '70', $at, null, 'test');
    $fx->postRate($aed, $afn, '19', $at, null, 'test');
}

test('acceptance A: USD purchase preserves 100 USD and calculates AFN/AED with investor ownership', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = tcEnsureCurrencies();
    $fx = app(CurrencyService::class);
    tcPostBaseRates($fx, $usd, $aed, $afn);

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $investor = Investor::factory()->create();
    $wallet = $investor->getOrCreateWallet();
    $walletBefore = (float) $wallet->capital_balance_afn;

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 700,
        'sale_price_afn' => 1000,
    ]);

    $purchase = app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 10,
        'purchase_currency_id' => $usd->id,
        'purchase_unit_amount' => '10',
        'sale_price_afn' => 1000,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'profit_per_unit' => 50,
        'usd_to_afn_rate' => '70',
        'aed_to_afn_rate' => '19',
        'purchase_date' => '2026-01-01',
    ], $admin, $branch->id);

    expect($purchase->purchase_currency_code)->toBe('USD')
        ->and((float) $purchase->purchase_unit_amount)->toBe(10.0)
        ->and((float) $purchase->purchase_line_amount)->toBe(100.0)
        ->and((float) $purchase->purchase_price_afn)->toBe(7000.0)
        ->and((float) $purchase->line_amount_usd)->toBe(100.0)
        ->and((float) $purchase->line_amount_aed)->toBe(368.42)
        ->and((float) $purchase->usd_to_afn_rate)->toBe(70.0)
        ->and((float) $purchase->aed_to_afn_rate)->toBe(19.0)
        ->and((int) $purchase->investor_id)->toBe($investor->id);

    $batch = ProductBatch::query()->where('purchase_item_id', $purchase->id)->first();
    expect($batch)->not->toBeNull()
        ->and((int) $batch->investor_id)->toBe($investor->id)
        ->and((float) $batch->unit_cost_afn)->toBe(700.0)
        ->and((float) $batch->unit_cost_currency)->toBe(10.0);

    $goods = Investment::query()->where('investor_id', $investor->id)->where('source_type', 'goods')->first();
    expect($goods)->not->toBeNull()
        ->and((float) $goods->capital_amount_currency)->toBe(100.0)
        ->and((float) $goods->capital_amount_afn)->toBe(7000.0)
        ->and((float) $goods->capital_amount_usd)->toBe(100.0);

    $wallet->refresh();
    expect((float) $wallet->capital_balance_afn)->toBe($walletBefore);
});

test('acceptance B: AFN purchase preserves 7000 AFN with USD/AED equivalents', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = tcEnsureCurrencies();
    $fx = app(CurrencyService::class);
    tcPostBaseRates($fx, $usd, $aed, $afn);

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $investor = Investor::factory()->create();

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 700,
        'sale_price_afn' => 1000,
    ]);

    $purchase = app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 10,
        'purchase_currency_id' => $afn->id,
        'purchase_unit_amount' => '700',
        'sale_price_afn' => 1000,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'profit_per_unit' => 20,
        'usd_to_afn_rate' => '70',
        'aed_to_afn_rate' => '19',
        'purchase_date' => '2026-01-01',
    ], $admin, $branch->id);

    expect($purchase->purchase_currency_code)->toBe('AFN')
        ->and((float) $purchase->purchase_line_amount)->toBe(7000.0)
        ->and((float) $purchase->purchase_price_afn)->toBe(7000.0)
        ->and((float) $purchase->line_amount_usd)->toBe(100.0)
        ->and((float) $purchase->line_amount_aed)->toBe(368.42)
        ->and(ProductBatch::query()->where('purchase_item_id', $purchase->id)->where('investor_id', $investor->id)->exists())->toBeTrue();
});

test('acceptance C: AED purchase preserves 100 AED with AFN/USD equivalents', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = tcEnsureCurrencies();
    $fx = app(CurrencyService::class);
    tcPostBaseRates($fx, $usd, $aed, $afn);

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $investor = Investor::factory()->create();

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 380,
        'sale_price_afn' => 500,
    ]);

    $purchase = app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 5,
        'purchase_currency_id' => $aed->id,
        'purchase_unit_amount' => '20',
        'sale_price_afn' => 500,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'profit_per_unit' => 10,
        'usd_to_afn_rate' => '70',
        'aed_to_afn_rate' => '19',
        'purchase_date' => '2026-01-01',
    ], $admin, $branch->id);

    expect($purchase->purchase_currency_code)->toBe('AED')
        ->and((float) $purchase->purchase_line_amount)->toBe(100.0)
        ->and((float) $purchase->purchase_price_afn)->toBe(1900.0)
        ->and((float) $purchase->line_amount_usd)->toBe(27.14)
        ->and((float) $purchase->line_amount_aed)->toBe(100.0);
});

test('acceptance D: purchase USD sell AFN profit uses historical snapshots', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = tcEnsureCurrencies();
    $fx = app(CurrencyService::class);
    tcPostBaseRates($fx, $usd, $aed, $afn);

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create();

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 700,
        'sale_price_afn' => 8000,
    ]);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 1,
        'purchase_currency_id' => $usd->id,
        'purchase_unit_amount' => '100',
        'sale_price_afn' => 8000,
        'funding_source' => 'office',
        'usd_to_afn_rate' => '70',
        'aed_to_afn_rate' => '19',
        'purchase_date' => '2026-01-01',
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => '2026-01-02',
        'sale_currency_id' => $afn->id,
        'usd_to_afn_rate' => '70',
        'aed_to_afn_rate' => '19',
        'payment_method' => 'cash',
        'discount_amount' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_unit_amount' => '8000',
            'discount_amount' => 0,
        ]],
        'payments' => [[
            'amount' => 8000,
            'currency_id' => $afn->id,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    expect($sale->sale_currency_code)->toBe('AFN')
        ->and((float) $sale->sale_amount_currency)->toBe(8000.0)
        ->and((float) $sale->total_afn)->toBe(8000.0)
        ->and((float) $sale->total_cost_afn)->toBe(7000.0)
        ->and((float) $sale->total_profit_afn)->toBe(1000.0);

    $item = $sale->items()->first();
    expect((float) $item->sale_price_afn)->toBe(8000.0)
        ->and((float) $item->cost_price_afn)->toBe(7000.0);
});

test('acceptance E: sales in AFN USD and AED preserve originals and equivalents', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = tcEnsureCurrencies();
    $fx = app(CurrencyService::class);
    tcPostBaseRates($fx, $usd, $aed, $afn);

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create();

    $makeSale = function (Currency $currency, string $unit, float $expectedAfn) use ($branch, $admin, $customer, $afn, $usd, $aed) {
        $variant = ProductVariant::factory()->create([
            'purchase_price_afn' => 100,
            'sale_price_afn' => max(100, $expectedAfn),
        ]);
        app(PurchaseService::class)->receiveStock([
            'variant_id' => $variant->id,
            'quantity' => 2,
            'purchase_currency_id' => $afn->id,
            'purchase_unit_amount' => '100',
            'sale_price_afn' => max(100, $expectedAfn),
            'funding_source' => 'office',
            'usd_to_afn_rate' => '70',
            'aed_to_afn_rate' => '19',
            'purchase_date' => '2026-01-01',
        ], $admin, $branch->id);

        return app(SaleService::class)->createSale([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'sale_date' => '2026-01-02',
            'sale_currency_id' => $currency->id,
            'usd_to_afn_rate' => '70',
            'aed_to_afn_rate' => '19',
            'payment_method' => 'cash',
            'items' => [[
                'variant_id' => $variant->id,
                'quantity' => 1,
                'sale_unit_amount' => $unit,
                'discount_amount' => 0,
            ]],
            'payments' => [[
                'amount' => $expectedAfn,
                'currency_id' => $afn->id,
                'payment_method' => 'cash',
            ]],
        ], $admin);
    };

    $saleAfn = $makeSale($afn, '1400', 1400.0);
    expect($saleAfn->sale_currency_code)->toBe('AFN')
        ->and((float) $saleAfn->sale_amount_currency)->toBe(1400.0)
        ->and((float) $saleAfn->usd_equivalent_amount)->toBe(20.0);

    $saleUsd = $makeSale($usd, '20', 1400.0);
    expect($saleUsd->sale_currency_code)->toBe('USD')
        ->and((float) $saleUsd->sale_amount_currency)->toBe(20.0)
        ->and((float) $saleUsd->total_afn)->toBe(1400.0)
        ->and((float) $saleUsd->aed_equivalent_amount)->toBe(73.68);

    $saleAed = $makeSale($aed, '20', 380.0);
    expect($saleAed->sale_currency_code)->toBe('AED')
        ->and((float) $saleAed->sale_amount_currency)->toBe(20.0)
        ->and((float) $saleAed->total_afn)->toBe(380.0);
});

test('acceptance F: updating exchange rates does not rewrite historical purchase/sale', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = tcEnsureCurrencies();
    $fx = app(CurrencyService::class);
    tcPostBaseRates($fx, $usd, $aed, $afn, '2026-01-01 08:00:00');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create();
    $investor = Investor::factory()->create();

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 700,
        'sale_price_afn' => 1000,
    ]);

    $purchase = app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 1,
        'purchase_currency_id' => $usd->id,
        'purchase_unit_amount' => '10',
        'sale_price_afn' => 1000,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'profit_per_unit' => 50,
        'profit_currency_id' => $afn->id,
        'usd_to_afn_rate' => '70',
        'aed_to_afn_rate' => '19',
        'purchase_date' => '2026-01-01',
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => '2026-01-01',
        'sale_currency_id' => $usd->id,
        'usd_to_afn_rate' => '70',
        'aed_to_afn_rate' => '19',
        'payment_method' => 'cash',
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_unit_amount' => '15',
            'discount_amount' => 0,
        ]],
        'payments' => [[
            'amount' => 1050,
            'currency_id' => $afn->id,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    $purchaseSnap = [
        'line' => (float) $purchase->purchase_line_amount,
        'afn' => (float) $purchase->purchase_price_afn,
        'usd_rate' => (float) $purchase->usd_to_afn_rate,
    ];
    $saleSnap = [
        'amount' => (float) $sale->sale_amount_currency,
        'afn' => (float) $sale->total_afn,
        'profit' => (float) $sale->total_profit_afn,
        'usd_rate' => (float) $sale->usd_to_afn_rate,
    ];
    $goods = Investment::query()->where('investor_id', $investor->id)->where('source_type', 'goods')->first();
    $goodsAfn = (float) $goods->capital_amount_afn;

    $fx->postRate($usd, $afn, '99', '2026-01-03 09:00:00', $admin->id, 'test');
    $fx->postRate($aed, $afn, '25', '2026-01-03 09:00:00', $admin->id, 'test');

    $purchase->refresh();
    $sale->refresh();
    $goods->refresh();

    expect((float) $purchase->purchase_line_amount)->toBe($purchaseSnap['line'])
        ->and((float) $purchase->purchase_price_afn)->toBe($purchaseSnap['afn'])
        ->and((float) $purchase->usd_to_afn_rate)->toBe($purchaseSnap['usd_rate'])
        ->and((float) $sale->sale_amount_currency)->toBe($saleSnap['amount'])
        ->and((float) $sale->total_afn)->toBe($saleSnap['afn'])
        ->and((float) $sale->total_profit_afn)->toBe($saleSnap['profit'])
        ->and((float) $sale->usd_to_afn_rate)->toBe($saleSnap['usd_rate'])
        ->and((float) $goods->capital_amount_afn)->toBe($goodsAfn);

    $board = $fx->boardAt('2026-01-03 10:00:00');
    expect((float) $board->usdToAfn)->toBe(99.0)
        ->and((float) $board->aedToAfn)->toBe(25.0);
});

test('FX board derives USD to AED consistently and rejects zero rates', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = tcEnsureCurrencies();
    $fx = app(CurrencyService::class);
    tcPostBaseRates($fx, $usd, $aed, $afn);

    $board = $fx->boardAt('2026-01-01 09:00:00');
    expect(round((float) $board->usdToAed, 8))->toBe(round(70 / 19, 8));

    expect(fn () => $fx->boardAt(now(), ['usd_to_afn' => 0, 'aed_to_afn' => 19]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
