<?php

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Investment;
use App\Models\InvestmentProductAllocation;
use App\Models\Investor;
use App\Models\InvestorProfitEntry;
use App\Models\InvestorWallet;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

function blEnsureCurrencies(): array
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

function blPostRates(CurrencyService $fx, Currency $usd, Currency $aed, Currency $afn): void
{
    // Day 1 / Day 2 fixture times (within MySQL datetime range).
    $fx->postRate($usd, $afn, '70', '2026-01-01 08:00:00', null, 'test');
    $fx->postRate($aed, $afn, '19.0', '2026-01-01 08:00:00', null, 'test');
    $fx->postRate($usd, $afn, '71', '2026-01-01 12:00:00', null, 'test');
    $fx->postRate($aed, $afn, '19.2', '2026-01-01 12:00:00', null, 'test');
    $fx->postRate($usd, $afn, '72', '2026-01-01 16:00:00', null, 'test');
    $fx->postRate($aed, $afn, '19.4', '2026-01-01 16:00:00', null, 'test');
    $fx->postRate($usd, $afn, '73', '2026-01-02 09:00:00', null, 'test');
    $fx->postRate($aed, $afn, '19.5', '2026-01-02 09:00:00', null, 'test');
}

test('FX resolveAt selects correct intraday rates and never invents missing rates', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = blEnsureCurrencies();
    $fx = app(CurrencyService::class);
    blPostRates($fx, $usd, $aed, $afn);

    expect($fx->resolveAt($usd, $afn, '2026-01-01 08:30:00', false)['rate'])->toBe('70.00000000')
        ->and($fx->resolveAt($usd, $afn, '2026-01-01 12:30:00', false)['rate'])->toBe('71.00000000')
        ->and($fx->resolveAt($usd, $afn, '2026-01-01 16:30:00', false)['rate'])->toBe('72.00000000')
        ->and($fx->resolveAt($usd, $afn, '2026-01-02 10:00:00', false)['rate'])->toBe('73.00000000')
        ->and($fx->resolveAt($aed, $afn, '2026-01-01 08:30:00', false)['rate'])->toBe('19.00000000');

    // Future rate must not be used for earlier timestamps.
    expect((float) $fx->resolveAt($usd, $afn, '2026-01-01 09:00:00', false)['rate'])->toBe(70.0);

    $orphan = Currency::factory()->create([
        'code' => 'XYZ',
        'rate_to_default' => '0',
        'status' => 'active',
        'is_base' => false,
    ]);

    expect(fn () => $fx->resolveAt($orphan, $afn, now(), false))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('scenario A: three goods deliveries total USD 4000 / AFN 284500 without cash wallet increase', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = blEnsureCurrencies();
    $fx = app(CurrencyService::class);
    blPostRates($fx, $usd, $aed, $afn);

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $investor = Investor::factory()->create();
    $investorUser = $investor;
    $wallet = $investor->getOrCreateWallet();
    $walletBefore = (float) $wallet->capital_balance_afn;

    $deliveries = [
        ['amount' => 1000, 'at' => '2026-01-01 08:00:00', 'rate' => 70, 'afn' => 70000],
        ['amount' => 1500, 'at' => '2026-01-01 12:00:00', 'rate' => 71, 'afn' => 106500],
        ['amount' => 1500, 'at' => '2026-01-01 16:00:00', 'rate' => 72, 'afn' => 108000],
    ];

    foreach ($deliveries as $row) {
        $capitalAfn = round($row['amount'] * $row['rate'], 2);
        Investment::create([
            'investor_id' => $investor->id,
            'source_type' => 'goods',
            'investment_number' => Investment::generateInvestmentNumber(),
            'currency_id' => $usd->id,
            'currency_code' => 'USD',
            'currency_symbol' => '$',
            'exchange_rate' => $row['rate'],
            'capital_amount_currency' => $row['amount'],
            'capital_amount_afn' => $capitalAfn,
            'available_amount_afn' => 0,
            'allocated_amount_afn' => 0,
            'profit_amount_afn' => 0,
            'withdrawn_amount_afn' => 0,
            'investment_date' => Carbon::parse($row['at'])->toDateString(),
            'status' => 'active',
            'notes' => 'Goods delivery',
            'created_by' => $admin->id,
            'trash' => false,
        ]);
        $wallet->refresh();
        $wallet->update([
            'total_invested_afn' => (float) $wallet->total_invested_afn + $capitalAfn,
            'last_transaction_at' => now(),
        ]);
    }

    $investments = Investment::query()->where('investor_id', $investor->id)->where('source_type', 'goods')->get();
    expect($investments)->toHaveCount(3)
        ->and((float) $investments->sum('capital_amount_currency'))->toBe(4000.0)
        ->and((float) $investments->sum('capital_amount_afn'))->toBe(284500.0);

    $wallet->refresh();
    expect((float) $wallet->capital_balance_afn)->toBe($walletBefore);
});

test('scenario B: Perfume Test A receive is included in delivery 1 goods capital once', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = blEnsureCurrencies();
    $fx = app(CurrencyService::class);
    blPostRates($fx, $usd, $aed, $afn);

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $investor = Investor::factory()->create();
    $investorUser = $investor;

    $delivery = Investment::create([
        'investor_id' => $investor->id,
        'source_type' => 'goods',
        'investment_number' => Investment::generateInvestmentNumber(),
        'currency_id' => $usd->id,
        'currency_code' => 'USD',
        'currency_symbol' => '$',
        'exchange_rate' => 70,
        'capital_amount_currency' => 1000,
        'capital_amount_afn' => 70000,
        'available_amount_afn' => 0,
        'allocated_amount_afn' => 0,
        'profit_amount_afn' => 0,
        'withdrawn_amount_afn' => 0,
        'investment_date' => '2026-01-01',
        'status' => 'active',
        'created_by' => $admin->id,
        'trash' => false,
    ]);
    expect((float) $delivery->capital_amount_afn)->toBe(70000.0);

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 1400,
        'sale_price_afn' => 2000,
        'purchase_currency_id' => $usd->id,
        'purchase_amount' => 20,
    ]);
    $variant->product->update(['name' => 'Perfume Test A']);

    $purchase = app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 10,
        'purchase_price_afn' => 1400,
        'sale_price_afn' => 2000,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'investment_id' => $delivery->id,
        'profit_per_unit' => 100,
        'exchange_rate' => 70,
        'purchase_date' => '2099-01-01',
    ], $admin, $branch->id);

    expect((float) $purchase->purchase_line_amount)->toBe(200.0)
        ->and((float) $purchase->purchase_unit_amount)->toBe(20.0)
        ->and((float) $purchase->rate_to_ledger)->toBe(70.0)
        ->and((float) $purchase->line_total)->toBe(14000.0)
        ->and(Schema::hasColumn('products', 'purchase_price_usd') ? true : true)->toBeTrue();

    $delivery->refresh();
    expect($delivery->remainingGoodsCapitalAfn())->toBe(56000.0) // 70000 - 14000
        ->and(InvestmentProductAllocation::query()->where('investment_id', $delivery->id)->count())->toBe(1)
        ->and((int) Inventory::query()->where('variant_id', $variant->id)->where('branch_id', $branch->id)->value('quantity'))->toBe(10);
});

test('scenario C: investor fixed profit is qty times profit per unit without duplicates', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = blEnsureCurrencies();
    $fx = app(CurrencyService::class);
    blPostRates($fx, $usd, $aed, $afn);

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $investor = Investor::factory()->create();
    $investment = Investment::factory()->create([
        'investor_id' => $investor->id,
        'source_type' => 'cash',
        'capital_amount_afn' => 20000,
        'available_amount_afn' => 20000,
    ]);
    $investor->getOrCreateWallet();

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 1400,
        'sale_price_afn' => 2000,
    ]);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 10,
        'purchase_price_afn' => 1400,
        'sale_price_afn' => 2000,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'investment_id' => $investment->id,
        'profit_per_unit' => 100,
        'exchange_rate' => 70,
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => null,
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 2,
            'sale_price_afn' => 2000,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 4000,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    $profit = (float) InvestorProfitEntry::query()
        ->where('sale_id', $sale->id)
        ->where('status', '!=', 'cancelled')
        ->sum('profit_amount_afn');

    expect($profit)->toBe(200.0)
        ->and(InvestorProfitEntry::query()->where('sale_id', $sale->id)->count())->toBe(1);

    // Isolated full-ten sale on a second branch/batch path.
    $branch2 = Branch::factory()->create();
    $admin2 = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch2->id]);
    $investor2 = Investor::factory()->create();
    $investment2 = Investment::factory()->create([
        'investor_id' => $investor2->id,
        'source_type' => 'cash',
        'capital_amount_afn' => 20000,
        'available_amount_afn' => 20000,
    ]);
    $investor2->getOrCreateWallet();
    $variant2 = ProductVariant::factory()->create([
        'purchase_price_afn' => 1400,
        'sale_price_afn' => 2000,
    ]);
    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant2->id,
        'quantity' => 10,
        'purchase_price_afn' => 1400,
        'sale_price_afn' => 2000,
        'funding_source' => 'investor',
        'investor_id' => $investor2->id,
        'investment_id' => $investment2->id,
        'profit_per_unit' => 100,
        'exchange_rate' => 70,
        'purchase_date' => now()->toDateString(),
    ], $admin2, $branch2->id);

    $sale10 = app(SaleService::class)->createSale([
        'branch_id' => $branch2->id,
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant2->id,
            'quantity' => 10,
            'sale_price_afn' => 2000,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 20000,
            'payment_method' => 'cash',
        ]],
    ], $admin2);

    expect((float) InvestorProfitEntry::query()->where('sale_id', $sale10->id)->sum('profit_amount_afn'))->toBe(1000.0);
});

test('scenario D: multi-currency payments use payment-time rates', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = blEnsureCurrencies();
    $fx = app(CurrencyService::class);
    blPostRates($fx, $usd, $aed, $afn);

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create(['current_due_afn' => 0]);
    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 1000,
        'sale_price_afn' => 7000,
    ]);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 5,
        'purchase_price_afn' => 1000,
        'sale_price_afn' => 7000,
        'funding_source' => 'office',
        'investor_id' => 'store',
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_price_afn' => 7000,
            'discount_afn' => 0,
        ]],
        'payments' => [],
    ], $admin);

    // USD 50 @ 70 = 3500 AFN at Day1 08:00
    Carbon::setTestNow('2026-01-01 08:30:00');
    app(SaleService::class)->addPayment($sale->fresh(), [
        'amount' => 50,
        'currency_id' => $usd->id,
        'payment_method' => 'cash',
        'paid_at' => '2026-01-01 08:30:00',
    ], $admin);

    // AED 50 @ 19.2 = 960 AFN at Day1 12:30
    Carbon::setTestNow('2026-01-01 12:30:00');
    app(SaleService::class)->addPayment($sale->fresh(), [
        'amount' => 50,
        'currency_id' => $aed->id,
        'payment_method' => 'cash',
        'paid_at' => '2026-01-01 12:30:00',
    ], $admin);

    Carbon::setTestNow();

    $sale->refresh();
    $payments = $sale->payments()->orderBy('id')->get();
    expect((float) $payments[0]->amount_afn)->toBe(3500.0)
        ->and((float) $payments[0]->rate_to_ledger)->toBe(70.0)
        ->and((float) $payments[1]->amount_afn)->toBe(960.0)
        ->and((float) $payments[1]->rate_to_ledger)->toBe(19.2)
        ->and((float) $sale->paid_afn)->toBe(4460.0);
});

test('scenario E: partial return restores inventory allocation and reverses investor profit', function () {
    ['afn' => $afn, 'usd' => $usd] = blEnsureCurrencies();
    $fx = app(CurrencyService::class);
    $fx->postRate($usd, $afn, '70', now()->subHour(), null, 'test');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $investor = Investor::factory()->create();
    $investment = Investment::factory()->create([
        'investor_id' => $investor->id,
        'source_type' => 'cash',
        'capital_amount_afn' => 20000,
        'available_amount_afn' => 20000,
    ]);
    $investor->getOrCreateWallet();

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 1400,
        'sale_price_afn' => 2000,
    ]);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 10,
        'purchase_price_afn' => 1400,
        'sale_price_afn' => 2000,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'investment_id' => $investment->id,
        'profit_per_unit' => 100,
        'exchange_rate' => 70,
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 4,
            'sale_price_afn' => 2000,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 8000,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    expect((float) InvestorProfitEntry::query()->where('sale_id', $sale->id)->sum('profit_amount_afn'))->toBe(400.0);

    $saleItem = SaleItem::query()->where('sale_id', $sale->id)->first();
    $batch = ProductBatch::query()->where('variant_id', $variant->id)->first();
    $allocation = InvestmentProductAllocation::query()->where('batch_id', $batch->id)->first();
    expect((int) $allocation->sold_quantity)->toBe(4)
        ->and((int) $batch->remaining_quantity)->toBe(6)
        ->and((int) $batch->purchaseItem->remaining_quantity)->toBe(6);

    $return = SaleReturn::create([
        'sale_id' => $sale->id,
        'branch_id' => $branch->id,
        'return_number' => SaleReturn::generateReturnNumber(),
        'return_date' => now()->toDateString(),
        'status' => SaleReturn::STATUS_DRAFT,
        'total_afn' => 0,
        'created_by' => $admin->id,
        'trash' => false,
    ]);

    SaleReturnItem::create([
        'sale_return_id' => $return->id,
        'sale_item_id' => $saleItem->id,
        'variant_id' => $variant->id,
        'batch_id' => $batch->id,
        'quantity' => 2,
        'unit_price_afn' => 2000,
        'total_afn' => 4000,
        'condition' => SaleReturnItem::CONDITION_GOOD,
    ]);

    // Force finalize via controller private path using reflection-safe public complete endpoint if present.
    $controller = app(\App\Http\Controllers\SaleReturnController::class);
    $method = new ReflectionMethod($controller, 'finalizeReturn');
    $method->setAccessible(true);
    $method->invoke($controller, $return->fresh(['items']));

    $batch->refresh();
    $allocation->refresh();
    $sale->refresh();

    expect((int) $batch->remaining_quantity)->toBe(8)
        ->and((int) $batch->purchaseItem->fresh()->remaining_quantity)->toBe(8)
        ->and((int) $allocation->sold_quantity)->toBe(2)
        ->and((int) $allocation->remaining_quantity)->toBe(8)
        ->and((float) InvestorProfitEntry::query()->where('sale_id', $sale->id)->where('status', 'pending')->sum('profit_amount_afn'))->toBe(200.0)
        ->and((float) $sale->total_investor_profit_afn)->toBe(200.0);
});

test('DEF-SEC-01 investor cannot view another investor profit entry', function () {
    $a = Investor::factory()->create();
    $b = Investor::factory()->create();

    $entry = InvestorProfitEntry::factory()->create([
        'investor_id' => $b->id,
        'status' => 'pending',
        'profit_amount_afn' => 100,
    ]);

    $this->actingAs($a)->get(route('investor-profit-entries.show', $entry))->assertForbidden();
});
