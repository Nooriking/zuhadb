<?php

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorProfitEntry;
use App\Models\InvestorWithdrawal;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\InvestorMultiCurrencyDashboardService;
use App\Services\PurchaseService;
use App\Services\SaleService;

function mcdCurrencies(): array
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
            'rate_to_default' => '19',
            'status' => 'active',
        ]);
    $aed->update(['rate_to_default' => '19', 'status' => 'active', 'trash' => false, 'is_base' => false]);

    return compact('afn', 'usd', 'aed');
}

function mcdPostRates(CurrencyService $fx, Currency $usd, Currency $aed, Currency $afn): void
{
    $fx->postRate($usd, $afn, '65', '2026-01-01 08:00:00', null, 'test');
    $fx->postRate($aed, $afn, '19', '2026-01-01 08:00:00', null, 'test');
}

test('investor dashboard separates original AFN USD and AED purchase values', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = mcdCurrencies();
    $fx = app(CurrencyService::class);
    mcdPostRates($fx, $usd, $aed, $afn);

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $investor = Investor::factory()->create();

    $receive = function (Currency $currency, string $unit, int $qty, string $rateUsd, string $rateAed) use ($admin, $branch, $investor, $usd, $aed) {
        $variant = ProductVariant::factory()->create([
            'purchase_price_afn' => 100,
            'sale_price_afn' => 200,
        ]);

        return app(PurchaseService::class)->receiveStock([
            'variant_id' => $variant->id,
            'quantity' => $qty,
            'purchase_currency_id' => $currency->id,
            'purchase_unit_amount' => $unit,
            'sale_price_afn' => 200,
            'funding_source' => 'investor',
            'investor_id' => $investor->id,
            'profit_per_unit' => 5,
            'usd_to_afn_rate' => $rateUsd,
            'aed_to_afn_rate' => $rateAed,
            'purchase_date' => '2026-01-01',
        ], $admin, $branch->id);
    };

    $usdPurchase = $receive($usd, '1', 10, '65', '19');
    $afnPurchase = $receive($afn, '100', 5, '65', '19');
    $aedPurchase = $receive($aed, '10', 4, '65', '19');

    expect($usdPurchase->purchase_currency_code)->toBe('USD')
        ->and((float) $usdPurchase->purchase_line_amount)->toBe(10.0)
        ->and($afnPurchase->purchase_currency_code)->toBe('AFN')
        ->and((float) $afnPurchase->purchase_line_amount)->toBe(500.0)
        ->and($aedPurchase->purchase_currency_code)->toBe('AED')
        ->and((float) $aedPurchase->purchase_line_amount)->toBe(40.0);

    $summary = app(InvestorMultiCurrencyDashboardService::class)->summarize($investor->id);

    expect((float) $summary['currency_sections']['USD']['goods_purchase_value'])->toBe(10.0)
        ->and((float) $summary['currency_sections']['AFN']['goods_purchase_value'])->toBe(500.0)
        ->and((float) $summary['currency_sections']['AED']['goods_purchase_value'])->toBe(40.0)
        ->and((float) $summary['currency_sections']['USD']['inventory_value'])->toBe(10.0)
        ->and((float) $summary['currency_sections']['AFN']['inventory_value'])->toBe(500.0)
        ->and((float) $summary['currency_sections']['AED']['inventory_value'])->toBe(40.0);

    // Never treat the sum of originals as a single currency total.
    $mixed = (float) $summary['currency_sections']['USD']['goods_purchase_value']
        + (float) $summary['currency_sections']['AFN']['goods_purchase_value']
        + (float) $summary['currency_sections']['AED']['goods_purchase_value'];
    expect($mixed)->toBe(550.0); // numeric coincidence only — dashboard must keep them separate
    expect($summary['currency_sections']['USD']['goods_purchase_value'])->not->toBe($summary['currency_sections']['AFN']['goods_purchase_value']);

    $this->actingAs($admin)
        ->get('/investors/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('investors/dashboard')
            ->has('currencySections.USD')
            ->has('currencySections.AFN')
            ->has('currencySections.AED')
            ->has('exchangeValuation')
            ->where('currencySections.USD.goods_purchase_value', fn ($v) => (float) $v === 10.0)
            ->where('currencySections.AFN.goods_purchase_value', fn ($v) => (float) $v === 500.0)
            ->where('currencySections.AED.goods_purchase_value', fn ($v) => (float) $v === 40.0)
        );
});

test('weighted average FX across three USD purchases equals 65.20 and survives later rate changes', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = mcdCurrencies();
    $fx = app(CurrencyService::class);
    mcdPostRates($fx, $usd, $aed, $afn);

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $investor = Investor::factory()->create();
    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 65,
        'sale_price_afn' => 100,
    ]);

    $purchases = [
        ['qty' => 10, 'rate' => '65.00'],
        ['qty' => 5, 'rate' => '65.30'],
        ['qty' => 5, 'rate' => '65.50'],
    ];

    $snapshots = [];
    foreach ($purchases as $row) {
        $purchase = app(PurchaseService::class)->receiveStock([
            'variant_id' => $variant->id,
            'quantity' => $row['qty'],
            'purchase_currency_id' => $usd->id,
            'purchase_unit_amount' => '1',
            'sale_price_afn' => 100,
            'funding_source' => 'investor',
            'investor_id' => $investor->id,
            'profit_per_unit' => 2,
            'usd_to_afn_rate' => $row['rate'],
            'aed_to_afn_rate' => '19',
            'purchase_date' => '2026-01-01',
        ], $admin, $branch->id);

        $snapshots[] = [
            'line' => (float) $purchase->purchase_line_amount,
            'afn' => (float) $purchase->purchase_price_afn,
            'rate' => (float) $purchase->usd_to_afn_rate,
        ];
    }

    $summary = app(InvestorMultiCurrencyDashboardService::class)->summarize($investor->id);
    $usdInv = $summary['exchange_valuation']['inventory_by_currency']['USD'];

    expect((float) $usdInv['original_amount'])->toBe(20.0)
        ->and((float) $usdInv['historical_afn'])->toBe(1304.0)
        ->and(round((float) $usdInv['weighted_average_fx_to_afn'], 2))->toBe(65.2)
        ->and((int) $usdInv['units'])->toBe(20)
        ->and($usdInv['kind'])->toBe('historical_weighted_average')
        ->and($summary['exchange_valuation']['basis'])->toBe('historical_batch_cost');

    $fx->postRate($usd, $afn, '99', '2026-01-05 09:00:00', $admin->id, 'test');

    foreach (Product::query()->where('investor_id', $investor->id)->whereNotNull('variant_id')->orderBy('id')->get() as $i => $purchase) {
        expect((float) $purchase->purchase_line_amount)->toBe($snapshots[$i]['line'])
            ->and((float) $purchase->purchase_price_afn)->toBe($snapshots[$i]['afn'])
            ->and((float) $purchase->usd_to_afn_rate)->toBe($snapshots[$i]['rate']);
    }

    $after = app(InvestorMultiCurrencyDashboardService::class)->summarize($investor->id);
    expect(round((float) $after['exchange_valuation']['weighted_average_usd_afn'], 2))->toBe(65.2);
});

test('partial sale recalculates remaining inventory weighted average without changing FIFO cost snapshots', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = mcdCurrencies();
    $fx = app(CurrencyService::class);
    mcdPostRates($fx, $usd, $aed, $afn);

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create();
    $investor = Investor::factory()->create();
    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 65,
        'sale_price_afn' => 100,
    ]);

    foreach ([
        ['qty' => 10, 'rate' => '65.00'],
        ['qty' => 5, 'rate' => '65.30'],
        ['qty' => 5, 'rate' => '65.50'],
    ] as $row) {
        app(PurchaseService::class)->receiveStock([
            'variant_id' => $variant->id,
            'quantity' => $row['qty'],
            'purchase_currency_id' => $usd->id,
            'purchase_unit_amount' => '1',
            'sale_price_afn' => 100,
            'funding_source' => 'investor',
            'investor_id' => $investor->id,
            'profit_per_unit' => 2,
            'usd_to_afn_rate' => $row['rate'],
            'aed_to_afn_rate' => '19',
            'purchase_date' => '2026-01-01',
        ], $admin, $branch->id);
    }

    $firstBatch = ProductBatch::query()
        ->where('investor_id', $investor->id)
        ->where('variant_id', $variant->id)
        ->orderBy('id')
        ->first();
    $firstUnitCost = (float) $firstBatch->unit_cost_afn;
    $firstRate = (float) $firstBatch->exchange_rate;

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => '2026-01-02',
        'sale_currency_id' => $afn->id,
        'usd_to_afn_rate' => '65',
        'aed_to_afn_rate' => '19',
        'payment_method' => 'cash',
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 5,
            'sale_unit_amount' => '100',
            'discount_amount' => 0,
        ]],
        'payments' => [[
            'amount' => 500,
            'currency_id' => $afn->id,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    // FIFO: first batch unit cost 65 AFN.
    expect((float) $sale->total_cost_afn)->toBe(325.0);

    $firstBatch->refresh();
    expect((float) $firstBatch->unit_cost_afn)->toBe($firstUnitCost)
        ->and((float) $firstBatch->exchange_rate)->toBe($firstRate)
        ->and((int) $firstBatch->remaining_quantity)->toBe(5);

    $summary = app(InvestorMultiCurrencyDashboardService::class)->summarize($investor->id);
    $usdInv = $summary['exchange_valuation']['inventory_by_currency']['USD'];

    expect((float) $usdInv['original_amount'])->toBe(15.0)
        ->and((float) $usdInv['historical_afn'])->toBe(979.0)
        ->and(round((float) $usdInv['weighted_average_fx_to_afn'], 4))->toBe(65.2667)
        ->and((float) $summary['currency_sections']['USD']['sold_goods_value'])->toBe(5.0)
        ->and((float) $summary['currency_sections']['USD']['inventory_value'])->toBe(15.0);
});

test('legacy profit without original currency is not invented into USD or AED', function () {
    ['afn' => $afn] = mcdCurrencies();
    $investor = Investor::factory()->create();

    InvestorProfitEntry::factory()->create([
        'investor_id' => $investor->id,
        'currency_code' => null,
        'profit_amount_currency' => null,
        'profit_amount_afn' => 77,
        'status' => 'pending',
        'earned_at' => now(),
    ]);

    InvestorWithdrawal::factory()->create([
        'investor_id' => $investor->id,
        'currency_code' => null,
        'amount_currency' => null,
        'amount_afn' => 12,
        'status' => 'paid',
        'type' => 'profit',
        'withdrawal_date' => now()->toDateString(),
    ]);

    $summary = app(InvestorMultiCurrencyDashboardService::class)->summarize($investor->id);

    expect((float) $summary['legacy']['profit_afn_without_currency'])->toBe(77.0)
        ->and((float) $summary['legacy']['withdrawals_afn_without_currency'])->toBe(12.0)
        ->and((float) $summary['currency_sections']['USD']['investor_profit'])->toBe(0.0)
        ->and((float) $summary['currency_sections']['AED']['investor_profit'])->toBe(0.0)
        ->and((float) $summary['currency_sections']['AFN']['investor_profit'])->toBe(0.0);
});

test('investor details page includes currency sections props', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $investor = Investor::factory()->create();

    Investment::factory()->create([
        'investor_id' => $investor->id,
        'source_type' => 'cash',
        'currency_code' => 'USD',
        'capital_amount_currency' => 50,
        'capital_amount_afn' => 3250,
        'available_amount_currency' => 50,
        'available_amount_afn' => 3250,
    ]);

    $this->actingAs($admin)
        ->get('/investors/'.$investor->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('investors/show')
            ->has('currencySections.USD')
            ->has('exchangeValuation')
            ->where('currencySections.USD.cash_capital', fn ($v) => (float) $v === 50.0)
        );
});

test('investors index includes separate AFN USD AED currency sections without cross-adding', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = mcdCurrencies();
    $fx = app(CurrencyService::class);
    mcdPostRates($fx, $usd, $aed, $afn);

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $investor = Investor::factory()->create();

    Investment::factory()->create([
        'investor_id' => $investor->id,
        'source_type' => 'cash',
        'currency_code' => 'USD',
        'capital_amount_currency' => 40,
        'capital_amount_afn' => 2600,
        'available_amount_currency' => 40,
        'available_amount_afn' => 2600,
    ]);

    Investment::factory()->create([
        'investor_id' => $investor->id,
        'source_type' => 'goods',
        'currency_code' => 'AED',
        'capital_amount_currency' => 100,
        'capital_amount_afn' => 1900,
        'available_amount_currency' => 0,
        'available_amount_afn' => 0,
    ]);

    $this->actingAs($admin)
        ->get('/investors')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('investors/index')
            ->has('currencySections.AFN')
            ->has('currencySections.USD')
            ->has('currencySections.AED')
            ->has('exchangeValuation')
            ->has('legacy')
            ->has('stats.current_balance')
            ->where('currencySections.USD.cash_capital', fn ($v) => (float) $v === 40.0)
            ->where('currencySections.AED.goods_purchase_value', fn ($v) => (float) $v === 100.0)
        );
});
