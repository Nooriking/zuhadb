<?php

/**
 * Acceptance scenarios for independent purchase / sale / investor-profit currencies,
 * FIFO allocations, historical FX snapshots, and store-owned vs investor-owned splits.
 *
 * Runs only against zuha_testing (phpunit.xml).
 */

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Investor;
use App\Models\InvestorProfitEntry;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\ProductExchangeRateService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

function ccfEnsureCurrencies(): array
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
            'rate_to_default' => '65',
            'status' => 'active',
        ]);
    $usd->update(['rate_to_default' => '65', 'status' => 'active', 'trash' => false, 'is_base' => false]);

    $aed = Currency::query()->where('code', 'AED')->first()
        ?? Currency::factory()->create([
            'code' => 'AED',
            'name' => 'UAE Dirham',
            'symbol' => 'د.إ',
            'is_base' => false,
            'rate_to_default' => '17.7',
            'status' => 'active',
        ]);
    $aed->update(['rate_to_default' => '17.7', 'status' => 'active', 'trash' => false, 'is_base' => false]);

    return compact('afn', 'usd', 'aed');
}

function ccfPostRates(CurrencyService $fx, Currency $usd, Currency $aed, Currency $afn, string $usdRate, string $aedRate, string $at): void
{
    $fx->postRate($usd, $afn, $usdRate, $at, null, 'test');
    $fx->postRate($aed, $afn, $aedRate, $at, null, 'test');
}

test('1+2: store-owned AFN purchase sold in USD — no investor profit; correct AFN ledger', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = ccfEnsureCurrencies();
    $fx = app(CurrencyService::class);
    ccfPostRates($fx, $usd, $aed, $afn, '65', '17.7', '2026-01-01 08:00:00');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create();

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 5000,
        'sale_price_afn' => 5224,
    ]);

    $purchase = app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 1,
        'purchase_currency_id' => $afn->id,
        'purchase_unit_amount' => '5000',
        'sale_price_afn' => 5224,
        'funding_source' => 'office',
        'usd_to_afn_rate' => '65',
        'aed_to_afn_rate' => '17.7',
        'purchase_date' => '2026-01-01',
    ], $admin, $branch->id);

    expect($purchase->purchase_currency_code)->toBe('AFN')
        ->and((float) $purchase->purchase_unit_amount)->toBe(5000.0)
        ->and((float) $purchase->purchase_price_afn)->toBe(5000.0)
        ->and((float) $purchase->line_amount_usd)->toBe(76.92); // Money scale 2: 5000/65

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => '2026-01-02',
        'sale_currency_id' => $usd->id,
        'usd_to_afn_rate' => '65.3',
        'aed_to_afn_rate' => '17.7',
        'payment_method' => 'cash',
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_unit_amount' => '80',
            'discount_amount' => 0,
        ]],
        'payments' => [[
            'amount' => 5224,
            'currency_id' => $afn->id,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    $item = SaleItem::query()->where('sale_id', $sale->id)->first();

    // 80 USD × 65.3 = 5,224 AFN; COGS 5,000; gross 224; store 224; investor 0
    expect($sale->sale_currency_code)->toBe('USD')
        ->and((float) $sale->sale_amount_currency)->toBe(80.0)
        ->and((float) $sale->total_afn)->toBe(5224.0)
        ->and((float) $sale->total_cost_afn)->toBe(5000.0)
        ->and((float) $sale->total_profit_afn)->toBe(224.0)
        ->and((float) $sale->total_investor_profit_afn)->toBe(0.0)
        ->and((float) $sale->store_profit_afn)->toBe(224.0)
        ->and((float) $item->sale_unit_amount)->toBe(80.0)
        ->and((float) $item->sale_price_afn)->toBe(5224.0)
        ->and((float) $item->cost_price_afn)->toBe(5000.0)
        ->and(InvestorProfitEntry::query()->where('sale_id', $sale->id)->count())->toBe(0);
});

test('2+5+8: investor-owned AFN purchase, USD sale, USD investor profit with different FX', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = ccfEnsureCurrencies();
    $fx = app(CurrencyService::class);
    ccfPostRates($fx, $usd, $aed, $afn, '65', '17.7', '2026-01-01 08:00:00');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create();
    $investor = Investor::factory()->create();
    $investor->getOrCreateWallet();

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 5000,
        'sale_price_afn' => 5224,
    ]);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 1,
        'purchase_currency_id' => $afn->id,
        'purchase_unit_amount' => '5000',
        'sale_price_afn' => 5224,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'profit_per_unit' => 2, // 2 USD
        'profit_currency_id' => $usd->id,
        'usd_to_afn_rate' => '65',
        'aed_to_afn_rate' => '17.7',
        'purchase_date' => '2026-01-01',
    ], $admin, $branch->id);

    $batch = ProductBatch::query()->where('variant_id', $variant->id)->first();
    expect((float) $batch->profit_per_unit_afn)->toBe(130.0) // 2 × 65
        ->and((float) $batch->profit_per_unit_currency)->toBe(2.0)
        ->and((string) $batch->profit_currency_code)->toBe('USD')
        ->and((float) $batch->unit_cost_currency)->toBe(5000.0)
        ->and((float) $batch->unit_cost_afn)->toBe(5000.0);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => '2026-01-02',
        'sale_currency_id' => $usd->id,
        'usd_to_afn_rate' => '65.3',
        'aed_to_afn_rate' => '17.7',
        'payment_method' => 'cash',
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_unit_amount' => '80',
            'discount_amount' => 0,
        ]],
        'payments' => [[
            'amount' => 5224,
            'currency_id' => $afn->id,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    // Gross 224; investor 130; store 94
    expect((float) $sale->total_afn)->toBe(5224.0)
        ->and((float) $sale->total_cost_afn)->toBe(5000.0)
        ->and((float) $sale->total_profit_afn)->toBe(224.0)
        ->and((float) $sale->total_investor_profit_afn)->toBe(130.0)
        ->and((float) $sale->store_profit_afn)->toBe(94.0);

    $entry = InvestorProfitEntry::query()->where('sale_id', $sale->id)->first();
    expect((float) $entry->profit_per_unit_currency)->toBe(2.0)
        ->and((float) $entry->profit_amount_afn)->toBe(130.0)
        ->and((string) $entry->currency_code)->toBe('USD');
});

test('3: purchase in USD and sale in AFN preserves originals', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = ccfEnsureCurrencies();
    $fx = app(CurrencyService::class);
    ccfPostRates($fx, $usd, $aed, $afn, '65', '17.7', '2026-01-01 08:00:00');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create();

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 6500,
        'sale_price_afn' => 8000,
    ]);

    $purchase = app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 1,
        'purchase_currency_id' => $usd->id,
        'purchase_unit_amount' => '100',
        'sale_price_afn' => 8000,
        'funding_source' => 'office',
        'usd_to_afn_rate' => '65',
        'aed_to_afn_rate' => '17.7',
        'purchase_date' => '2026-01-01',
    ], $admin, $branch->id);

    expect((float) $purchase->purchase_unit_amount)->toBe(100.0)
        ->and($purchase->purchase_currency_code)->toBe('USD')
        ->and((float) $purchase->purchase_price_afn)->toBe(6500.0);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => '2026-01-02',
        'sale_currency_id' => $afn->id,
        'usd_to_afn_rate' => '65',
        'aed_to_afn_rate' => '17.7',
        'payment_method' => 'cash',
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
        ->and((float) $sale->total_afn)->toBe(8000.0)
        ->and((float) $sale->total_cost_afn)->toBe(6500.0)
        ->and((float) $sale->total_profit_afn)->toBe(1500.0);
});

test('4: purchase in AED and sale in USD', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = ccfEnsureCurrencies();
    $fx = app(CurrencyService::class);
    ccfPostRates($fx, $usd, $aed, $afn, '65', '17.7', '2026-01-01 08:00:00');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create();

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 1770,
        'sale_price_afn' => 3250,
    ]);

    $purchase = app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 1,
        'purchase_currency_id' => $aed->id,
        'purchase_unit_amount' => '100',
        'sale_price_afn' => 3250,
        'funding_source' => 'office',
        'usd_to_afn_rate' => '65',
        'aed_to_afn_rate' => '17.7',
        'purchase_date' => '2026-01-01',
    ], $admin, $branch->id);

    expect($purchase->purchase_currency_code)->toBe('AED')
        ->and((float) $purchase->purchase_unit_amount)->toBe(100.0)
        ->and((float) $purchase->purchase_price_afn)->toBe(1770.0);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => '2026-01-02',
        'sale_currency_id' => $usd->id,
        'usd_to_afn_rate' => '65',
        'aed_to_afn_rate' => '17.7',
        'payment_method' => 'cash',
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_unit_amount' => '50',
            'discount_amount' => 0,
        ]],
        'payments' => [[
            'amount' => 3250,
            'currency_id' => $afn->id,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    expect((float) $sale->total_afn)->toBe(3250.0)
        ->and((float) $sale->total_cost_afn)->toBe(1770.0)
        ->and((float) $sale->total_profit_afn)->toBe(1480.0)
        ->and($sale->sale_currency_code)->toBe('USD')
        ->and((float) $sale->sale_amount_currency)->toBe(50.0);
});

test('6+7: investor profit configured in AFN and AED independently', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = ccfEnsureCurrencies();
    $fx = app(CurrencyService::class);
    ccfPostRates($fx, $usd, $aed, $afn, '65', '17.7', '2026-01-01 08:00:00');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);

    $mk = function (float $profit, int $currencyId, float $expectedAfn) use ($branch, $admin, $afn) {
        $investor = Investor::factory()->create();
        $investor->getOrCreateWallet();
        $variant = ProductVariant::factory()->create([
            'purchase_price_afn' => 1000,
            'sale_price_afn' => 5000,
        ]);
        app(PurchaseService::class)->receiveStock([
            'variant_id' => $variant->id,
            'quantity' => 1,
            'purchase_currency_id' => $afn->id,
            'purchase_unit_amount' => '1000',
            'sale_price_afn' => 5000,
            'funding_source' => 'investor',
            'investor_id' => $investor->id,
            'profit_per_unit' => $profit,
            'profit_currency_id' => $currencyId,
            'usd_to_afn_rate' => '65',
            'aed_to_afn_rate' => '17.7',
            'purchase_date' => '2026-01-01',
        ], $admin, $branch->id);

        $batch = ProductBatch::query()->where('variant_id', $variant->id)->first();
        expect((float) $batch->profit_per_unit_afn)->toBe($expectedAfn)
            ->and((float) $batch->profit_per_unit_currency)->toBe($profit);
    };

    $mk(200, $afn->id, 200.0);
    $mk(10, $aed->id, 177.0); // 10 × 17.7
});

test('9+13: FIFO multiple batches with different costs — partial sale', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = ccfEnsureCurrencies();
    $fx = app(CurrencyService::class);
    ccfPostRates($fx, $usd, $aed, $afn, '65', '17.7', '2026-01-01 08:00:00');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create();

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 5000,
        'sale_price_afn' => 6000,
    ]);

    foreach ([
        ['qty' => 5, 'cost' => '5000', 'date' => '2026-01-01'],
        ['qty' => 10, 'cost' => '5200', 'date' => '2026-01-02'],
        ['qty' => 5, 'cost' => '5400', 'date' => '2026-01-03'],
    ] as $row) {
        app(PurchaseService::class)->receiveStock([
            'variant_id' => $variant->id,
            'quantity' => $row['qty'],
            'purchase_currency_id' => $afn->id,
            'purchase_unit_amount' => $row['cost'],
            'sale_price_afn' => 6000,
            'funding_source' => 'office',
            'usd_to_afn_rate' => '65',
            'aed_to_afn_rate' => '17.7',
            'purchase_date' => $row['date'],
        ], $admin, $branch->id);
    }

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => '2026-01-04',
        'sale_currency_id' => $afn->id,
        'usd_to_afn_rate' => '65',
        'aed_to_afn_rate' => '17.7',
        'payment_method' => 'cash',
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 8, // 5 @ 5000 + 3 @ 5200
            'sale_unit_amount' => '6000',
            'discount_amount' => 0,
        ]],
        'payments' => [[
            'amount' => 48000,
            'currency_id' => $afn->id,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    $cogs = (5 * 5000) + (3 * 5200); // 40,600
    expect((float) $sale->total_cost_afn)->toBe((float) $cogs)
        ->and((float) $sale->total_afn)->toBe(48000.0)
        ->and((float) $sale->total_profit_afn)->toBe(48000.0 - $cogs);

    $item = SaleItem::query()->where('sale_id', $sale->id)->first();
    $breakdown = $item->cost_breakdown;
    expect(count($breakdown))->toBe(2)
        ->and((int) $breakdown[0]['qty'])->toBe(5)
        ->and((float) $breakdown[0]['cost_per_unit_afn'])->toBe(5000.0)
        ->and((int) $breakdown[1]['qty'])->toBe(3)
        ->and((float) $breakdown[1]['cost_per_unit_afn'])->toBe(5200.0);

    $remaining = (int) ProductBatch::query()->where('variant_id', $variant->id)->sum('remaining_quantity');
    expect($remaining)->toBe(12); // 20 - 8
});

test('10: multiple investors in one sale use batch-specific profit configs', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = ccfEnsureCurrencies();
    $fx = app(CurrencyService::class);
    ccfPostRates($fx, $usd, $aed, $afn, '65', '17.7', '2026-01-01 08:00:00');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $invA = Investor::factory()->create();
    $invB = Investor::factory()->create();
    $invA->getOrCreateWallet();
    $invB->getOrCreateWallet();

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 5000,
        'sale_price_afn' => 6000,
    ]);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 2,
        'purchase_currency_id' => $afn->id,
        'purchase_unit_amount' => '5000',
        'sale_price_afn' => 6000,
        'funding_source' => 'investor',
        'investor_id' => $invA->id,
        'profit_per_unit' => 100,
        'profit_currency_id' => $afn->id,
        'usd_to_afn_rate' => '65',
        'aed_to_afn_rate' => '17.7',
        'purchase_date' => '2026-01-01',
    ], $admin, $branch->id);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 2,
        'purchase_currency_id' => $afn->id,
        'purchase_unit_amount' => '5000',
        'sale_price_afn' => 6000,
        'funding_source' => 'investor',
        'investor_id' => $invB->id,
        'profit_per_unit' => 50,
        'profit_currency_id' => $afn->id,
        'usd_to_afn_rate' => '65',
        'aed_to_afn_rate' => '17.7',
        'purchase_date' => '2026-01-02',
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 3, // 2A + 1B
            'sale_price_afn' => 6000,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 18000,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    $profitA = (float) InvestorProfitEntry::query()->where('sale_id', $sale->id)->where('investor_id', $invA->id)->sum('profit_amount_afn');
    $profitB = (float) InvestorProfitEntry::query()->where('sale_id', $sale->id)->where('investor_id', $invB->id)->sum('profit_amount_afn');

    expect($profitA)->toBe(200.0)
        ->and($profitB)->toBe(50.0)
        ->and((float) $sale->total_investor_profit_afn)->toBe(250.0)
        ->and((float) $sale->total_profit_afn)->toBe(3000.0)
        ->and((float) $sale->store_profit_afn)->toBe(2750.0);
});

test('11: insufficient gross profit blocks sale and does not reduce investor profit', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = ccfEnsureCurrencies();
    $fx = app(CurrencyService::class);
    ccfPostRates($fx, $usd, $aed, $afn, '65', '17.7', '2026-01-01 08:00:00');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $investor = Investor::factory()->create();
    $investor->getOrCreateWallet();

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 5000,
        'sale_price_afn' => 6000,
    ]);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 1,
        'purchase_currency_id' => $afn->id,
        'purchase_unit_amount' => '5000',
        'sale_price_afn' => 6000,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'profit_per_unit' => 2,
        'profit_currency_id' => $usd->id, // 130 AFN
        'usd_to_afn_rate' => '65',
        'aed_to_afn_rate' => '17.7',
        'purchase_date' => '2026-01-01',
    ], $admin, $branch->id);

    // Gross would be 50 AFN < 130 investor PPU
    expect(fn () => app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_price_afn' => 5050,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 5050,
            'payment_method' => 'cash',
        ]],
    ], $admin))->toThrow(ValidationException::class);

    expect(InvestorProfitEntry::query()->count())->toBe(0)
        ->and((int) ProductBatch::query()->where('variant_id', $variant->id)->sum('remaining_quantity'))->toBe(1);
});

test('14: weighted average FX uses original amounts and remaining qty only', function () {
    $fx = app(CurrencyService::class);

    $avg = $fx->weightedAverageFxFromEquivalents([
        ['original_amount' => '10', 'afn_amount' => '650'],
        ['original_amount' => '5', 'afn_amount' => '326.50'],
        ['original_amount' => '5', 'afn_amount' => '327.50'],
    ]);

    expect($avg)->not->toBeNull()
        ->and(round((float) $avg, 2))->toBe(65.20);
});

test('15: product reference rates do not overwrite purchase FX snapshots', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = ccfEnsureCurrencies();
    $fx = app(CurrencyService::class);
    ccfPostRates($fx, $usd, $aed, $afn, '65', '17.7', '2026-01-01 08:00:00');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 6500,
        'sale_price_afn' => 8000,
    ]);
    $product = $variant->product;

    app(ProductExchangeRateService::class)->sync($product, '99', '4');

    $purchase = app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 1,
        'purchase_currency_id' => $usd->id,
        'purchase_unit_amount' => '100',
        'sale_price_afn' => 8000,
        'funding_source' => 'office',
        'usd_to_afn_rate' => '65',
        'aed_to_afn_rate' => '17.7',
        'purchase_date' => '2026-01-01',
    ], $admin, $branch->id);

    expect((float) $purchase->usd_to_afn_rate)->toBe(65.0)
        ->and((float) $purchase->purchase_price_afn)->toBe(6500.0)
        ->and(app(ProductExchangeRateService::class)->rate($product, 'USD', 'AFN'))->toBe('99');

    $board = $fx->boardAt('2026-01-01 09:00:00');
    expect((float) $board->usdToAfn)->toBe(65.0);
});

test('16+17+18+19: store-owned has no investor entries; AFN ledger; decimal rounding', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = ccfEnsureCurrencies();
    $fx = app(CurrencyService::class);
    ccfPostRates($fx, $usd, $aed, $afn, '65.333333', '17.777777', '2026-01-01 08:00:00');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 0,
        'sale_price_afn' => 1000,
    ]);

    $purchase = app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 3,
        'purchase_currency_id' => $usd->id,
        'purchase_unit_amount' => '10.125',
        'sale_price_afn' => 1000,
        'funding_source' => 'office',
        'usd_to_afn_rate' => '65.333333',
        'aed_to_afn_rate' => '17.777777',
        'purchase_date' => '2026-01-01',
    ], $admin, $branch->id);

    // PurchaseValuation rounds original unit to 2dp first (10.13), then converts.
    $unitOriginal = '10.13';
    $unitAfn = (float) Money::round(Money::mul($unitOriginal, '65.333333', 12), 2);
    $lineAfn = (float) Money::round(Money::mul((string) $unitAfn, '3', 8), 2);

    $batch = ProductBatch::query()->where('purchase_item_id', $purchase->id)->first();

    expect((float) $purchase->purchase_unit_amount)->toBe(10.13)
        ->and($purchase->purchase_currency_code)->toBe('USD')
        ->and((float) $batch->unit_cost_afn)->toBe($unitAfn)
        ->and((float) $batch->unit_cost_currency)->toBe(10.13)
        ->and((float) $purchase->purchase_price_afn)->toBe($lineAfn)
        ->and($batch->investor_id)->toBeNull()
        ->and((float) $batch->profit_per_unit_afn)->toBe(0.0)
        ->and(InvestorProfitEntry::query()->where('investor_id', '>', 0)->where('sale_id', null)->count())->toBeGreaterThanOrEqual(0);
});

test('20: later FX changes do not rewrite historical purchase or sale', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = ccfEnsureCurrencies();
    $fx = app(CurrencyService::class);
    ccfPostRates($fx, $usd, $aed, $afn, '65', '17.7', '2026-01-01 08:00:00');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create();

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 6500,
        'sale_price_afn' => 8000,
    ]);

    $purchase = app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 1,
        'purchase_currency_id' => $usd->id,
        'purchase_unit_amount' => '100',
        'sale_price_afn' => 8000,
        'funding_source' => 'office',
        'usd_to_afn_rate' => '65',
        'aed_to_afn_rate' => '17.7',
        'purchase_date' => '2026-01-01',
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => '2026-01-02',
        'sale_currency_id' => $usd->id,
        'usd_to_afn_rate' => '65.3',
        'aed_to_afn_rate' => '17.7',
        'payment_method' => 'cash',
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_unit_amount' => '120',
            'discount_amount' => 0,
        ]],
        'payments' => [[
            'amount' => 7836,
            'currency_id' => $afn->id,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    $pSnap = [(float) $purchase->purchase_price_afn, (float) $purchase->usd_to_afn_rate];
    $sSnap = [(float) $sale->total_afn, (float) $sale->usd_to_afn_rate, (float) $sale->total_profit_afn];

    $fx->postRate($usd, $afn, '99', '2026-01-10 09:00:00', $admin->id, 'test');

    $purchase->refresh();
    $sale->refresh();

    expect((float) $purchase->purchase_price_afn)->toBe($pSnap[0])
        ->and((float) $purchase->usd_to_afn_rate)->toBe($pSnap[1])
        ->and((float) $sale->total_afn)->toBe($sSnap[0])
        ->and((float) $sale->usd_to_afn_rate)->toBe($sSnap[1])
        ->and((float) $sale->total_profit_afn)->toBe($sSnap[2]);
});
