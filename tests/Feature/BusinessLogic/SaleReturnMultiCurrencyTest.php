<?php

/**
 * Dedicated sale-return financial integrity tests.
 * Runs only against zuha_testing (phpunit.xml / TestCase guard).
 *
 * Never recalculates historical returns with current FX or current investor profit settings.
 */

use App\Http\Controllers\SaleReturnController;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentProductAllocation;
use App\Models\Investor;
use App\Models\InvestorProfitEntry;
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
use Illuminate\Validation\ValidationException;

function srCurrencies(): array
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

    // ExchangeService reads only exchange_rates — seed the board explicitly.
    $fx = app(CurrencyService::class);
    $fx->postRate($usd, $afn, '70', '2026-01-01 08:00:00', null, 'test');
    $fx->postRate($aed, $afn, '19', '2026-01-01 08:00:00', null, 'test');

    return compact('afn', 'usd', 'aed');
}

function srFinalize(SaleReturn $return): void
{
    $controller = app(SaleReturnController::class);
    $method = new ReflectionMethod($controller, 'finalizeReturn');
    $method->setAccessible(true);
    $method->invoke($controller, $return->fresh(['items']));
}

function srCreateDraftReturn(Sale $sale, User $admin, SaleItem $saleItem, ProductBatch $batch, int $qty): SaleReturn
{
    $return = SaleReturn::create([
        'sale_id' => $sale->id,
        'branch_id' => $sale->branch_id,
        'return_number' => SaleReturn::generateReturnNumber(),
        'return_date' => now()->toDateString(),
        'status' => SaleReturn::STATUS_DRAFT,
        'total_afn' => 0,
        'created_by' => $admin->id,
        'trash' => false,
    ]);

    $item = new SaleReturnItem([
        'sale_return_id' => $return->id,
        'sale_item_id' => $saleItem->id,
        'variant_id' => $saleItem->variant_id,
        'batch_id' => $batch->id,
        'quantity' => $qty,
        'unit_price_afn' => (float) $saleItem->sale_price_afn,
        'total_afn' => round((float) $saleItem->sale_price_afn * $qty, 2),
        'condition' => SaleReturnItem::CONDITION_GOOD,
    ]);
    $item->calculateFromOriginalSaleItem();
    $item->save();

    return $return;
}

test('full return restores FIFO stock, cancels pending investor profit, and reverses store profit', function () {
    ['afn' => $afn, 'usd' => $usd] = srCurrencies();
    $fx = app(CurrencyService::class);
    $fx->postRate($usd, $afn, '70', '2026-03-01 08:00:00', null, 'test');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $investor = Investor::factory()->create();
    $investment = Investment::factory()->create([
        'investor_id' => $investor->id,
        'source_type' => 'cash',
        'capital_amount_afn' => 50000,
        'available_amount_afn' => 50000,
    ]);
    $investor->getOrCreateWallet();

    $variant = ProductVariant::factory()->create();

    $purchase = app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 5,
        'purchase_currency_id' => $usd->id,
        'purchase_unit_amount' => '20',
        'sale_price_afn' => 2000,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'investment_id' => $investment->id,
        'profit_per_unit' => 2,
        'profit_currency_id' => $usd->id,
        'usd_to_afn_rate' => '70',
        'aed_to_afn_rate' => '19',
        'purchase_date' => '2026-03-01',
    ], $admin, $branch->id);

    $historicalRate = (float) $purchase->exchange_rate;
    $historicalUnitCost = (float) ProductBatch::query()->where('variant_id', $variant->id)->value('unit_cost_afn');

    // Board moves after purchase — return must ignore current FX.
    $fx->postRate($usd, $afn, '99', '2026-03-10 08:00:00', null, 'test');

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 5,
            'sale_price_afn' => 2000,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 10000,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    $saleItem = SaleItem::query()->where('sale_id', $sale->id)->first();
    $batch = ProductBatch::query()->where('variant_id', $variant->id)->first();
    $storeBefore = (float) $sale->store_profit_afn;
    $investorBefore = (float) InvestorProfitEntry::query()->where('sale_id', $sale->id)->where('status', 'pending')->sum('profit_amount_afn');

    expect($investorBefore)->toBe(700.0) // 2 USD × 70 × 5
        ->and($storeBefore)->toBeGreaterThan(0);

    $return = srCreateDraftReturn($sale, $admin, $saleItem, $batch, 5);
    srFinalize($return);

    $batch->refresh();
    $sale->refresh();
    $purchase->refresh();

    expect((int) $batch->remaining_quantity)->toBe(5)
        ->and((float) $batch->unit_cost_afn)->toBe($historicalUnitCost)
        ->and((float) $purchase->exchange_rate)->toBe($historicalRate)
        ->and((float) InvestorProfitEntry::query()->where('sale_id', $sale->id)->where('status', 'pending')->sum('profit_amount_afn'))->toBe(0.0)
        ->and((float) $sale->store_profit_afn)->toBe(0.0)
        ->and((float) $sale->total_investor_profit_afn)->toBe(0.0);
});

test('partial return preserves original-currency pending profit remainder and historical purchase FX', function () {
    ['afn' => $afn, 'usd' => $usd] = srCurrencies();
    $fx = app(CurrencyService::class);
    $fx->postRate($usd, $afn, '70', '2026-03-01 08:00:00', null, 'test');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $investor = Investor::factory()->create();
    $investment = Investment::factory()->create([
        'investor_id' => $investor->id,
        'source_type' => 'cash',
        'capital_amount_afn' => 50000,
        'available_amount_afn' => 50000,
    ]);
    $investor->getOrCreateWallet();

    $variant = ProductVariant::factory()->create();

    $purchase = app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 10,
        'purchase_currency_id' => $usd->id,
        'purchase_unit_amount' => '20',
        'sale_price_afn' => 2000,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'investment_id' => $investment->id,
        'profit_per_unit' => 2,
        'profit_currency_id' => $usd->id,
        'usd_to_afn_rate' => '70',
        'aed_to_afn_rate' => '19',
        'purchase_date' => '2026-03-01',
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

    $saleItem = SaleItem::query()->where('sale_id', $sale->id)->first();
    $batch = ProductBatch::query()->where('variant_id', $variant->id)->first();
    $entry = InvestorProfitEntry::query()->where('sale_item_id', $saleItem->id)->where('status', 'pending')->first();

    expect((float) $entry->profit_amount_currency)->toBe(8.0) // 2 USD × 4
        ->and((float) $entry->profit_amount_afn)->toBe(560.0)
        ->and((float) $entry->exchange_rate)->toBe(70.0);

    $return = srCreateDraftReturn($sale, $admin, $saleItem, $batch, 1);
    srFinalize($return);

    $entry->refresh();
    $purchase->refresh();

    expect((int) $entry->quantity)->toBe(3)
        ->and((float) $entry->profit_amount_currency)->toBe(6.0)
        ->and((float) $entry->profit_amount_afn)->toBe(420.0)
        ->and((float) $entry->exchange_rate)->toBe(70.0)
        ->and((float) $batch->fresh()->exchange_rate)->toBe(70.0)
        ->and((int) $batch->fresh()->remaining_quantity)->toBe(7);
});

test('multi-batch FIFO return restores original batch costs without current FX', function () {
    ['afn' => $afn, 'usd' => $usd] = srCurrencies();
    $fx = app(CurrencyService::class);
    $fx->postRate($usd, $afn, '65', '2026-03-01 08:00:00', null, 'test');
    $fx->postRate($usd, $afn, '70', '2026-03-02 08:00:00', null, 'test');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $investor = Investor::factory()->create();
    $investment = Investment::factory()->create([
        'investor_id' => $investor->id,
        'source_type' => 'cash',
        'capital_amount_afn' => 100000,
        'available_amount_afn' => 100000,
    ]);
    $investor->getOrCreateWallet();

    $variant = ProductVariant::factory()->create();

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 3,
        'purchase_currency_id' => $usd->id,
        'purchase_unit_amount' => '1',
        'sale_price_afn' => 200,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'investment_id' => $investment->id,
        'profit_per_unit' => 10,
        'usd_to_afn_rate' => '65',
        'aed_to_afn_rate' => '19',
        'purchase_date' => '2026-03-01',
    ], $admin, $branch->id);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 3,
        'purchase_currency_id' => $usd->id,
        'purchase_unit_amount' => '1',
        'sale_price_afn' => 200,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'investment_id' => $investment->id,
        'profit_per_unit' => 10,
        'usd_to_afn_rate' => '70',
        'aed_to_afn_rate' => '19',
        'purchase_date' => '2026-03-02',
    ], $admin, $branch->id);

    $batches = ProductBatch::query()->where('variant_id', $variant->id)->orderBy('id')->get();
    expect($batches)->toHaveCount(2);
    $batchA = $batches[0];
    $batchB = $batches[1];
    $costA = (float) $batchA->unit_cost_afn;
    $costB = (float) $batchB->unit_cost_afn;
    expect($costA)->toBe(65.0)->and($costB)->toBe(70.0);

    // Current board drifts — must not affect restored costs.
    $fx->postRate($usd, $afn, '120', '2026-03-20 08:00:00', null, 'test');

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 5,
            'sale_price_afn' => 200,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 1000,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    $saleItem = SaleItem::query()->where('sale_id', $sale->id)->first();
    expect($saleItem->cost_breakdown)->toBeArray()->and(count($saleItem->cost_breakdown))->toBeGreaterThanOrEqual(2);

    // Omit batch_id so finalizeReturn unwinds the full FIFO cost_breakdown across batches.
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
        'batch_id' => null,
        'quantity' => 5,
        'unit_price_afn' => 200,
        'total_afn' => 1000,
        'condition' => SaleReturnItem::CONDITION_GOOD,
    ]);
    srFinalize($return);

    expect((int) $batchA->fresh()->remaining_quantity)->toBe(3)
        ->and((float) $batchA->fresh()->unit_cost_afn)->toBe(65.0)
        ->and((int) $batchB->fresh()->remaining_quantity)->toBe(3)
        ->and((float) $batchB->fresh()->unit_cost_afn)->toBe(70.0);
});

test('multi-investor sale return reverses each investor profit independently', function () {
    ['afn' => $afn] = srCurrencies();

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);

    $invA = Investor::factory()->create();
    $invB = Investor::factory()->create();
    $capA = Investment::factory()->create([
        'investor_id' => $invA->id,
        'source_type' => 'cash',
        'capital_amount_afn' => 20000,
        'available_amount_afn' => 20000,
    ]);
    $capB = Investment::factory()->create([
        'investor_id' => $invB->id,
        'source_type' => 'cash',
        'capital_amount_afn' => 20000,
        'available_amount_afn' => 20000,
    ]);
    $invA->getOrCreateWallet();
    $invB->getOrCreateWallet();

    $variant = ProductVariant::factory()->create();

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 4,
        'purchase_price_afn' => 1000,
        'sale_price_afn' => 1500,
        'funding_source' => 'investor',
        'investor_id' => $invA->id,
        'investment_id' => $capA->id,
        'profit_per_unit' => 100,
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 4,
        'purchase_price_afn' => 1000,
        'sale_price_afn' => 1500,
        'funding_source' => 'investor',
        'investor_id' => $invB->id,
        'investment_id' => $capB->id,
        'profit_per_unit' => 150,
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 6,
            'sale_price_afn' => 1500,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 9000,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    expect((float) InvestorProfitEntry::query()->where('investor_id', $invA->id)->where('sale_id', $sale->id)->sum('profit_amount_afn'))->toBe(400.0)
        ->and((float) InvestorProfitEntry::query()->where('investor_id', $invB->id)->where('sale_id', $sale->id)->sum('profit_amount_afn'))->toBe(300.0);

    $saleItem = SaleItem::query()->where('sale_id', $sale->id)->first();
    $batchA = ProductBatch::query()
        ->where('variant_id', $variant->id)
        ->whereHas('investment', fn ($q) => $q->where('investor_id', $invA->id))
        ->first();

    $return = srCreateDraftReturn($sale, $admin, $saleItem, $batchA, 6);
    srFinalize($return);

    expect((float) InvestorProfitEntry::query()->where('investor_id', $invA->id)->where('sale_id', $sale->id)->where('status', 'pending')->sum('profit_amount_afn'))->toBe(0.0)
        ->and((float) InvestorProfitEntry::query()->where('investor_id', $invB->id)->where('sale_id', $sale->id)->where('status', 'pending')->sum('profit_amount_afn'))->toBe(0.0);
});

test('paid investor profit return creates immutable adjustment without mutating original entry FX', function () {
    ['afn' => $afn, 'usd' => $usd] = srCurrencies();
    $fx = app(CurrencyService::class);
    $fx->postRate($usd, $afn, '70', '2026-03-01 08:00:00', null, 'test');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $investor = Investor::factory()->create();
    $investment = Investment::factory()->create([
        'investor_id' => $investor->id,
        'source_type' => 'cash',
        'capital_amount_afn' => 50000,
        'available_amount_afn' => 50000,
    ]);
    $investor->getOrCreateWallet();

    $variant = ProductVariant::factory()->create();

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 5,
        'purchase_currency_id' => $usd->id,
        'purchase_unit_amount' => '20',
        'sale_price_afn' => 2000,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'investment_id' => $investment->id,
        'profit_per_unit' => 2,
        'profit_currency_id' => $usd->id,
        'usd_to_afn_rate' => '70',
        'aed_to_afn_rate' => '19',
        'purchase_date' => '2026-03-01',
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 3,
            'sale_price_afn' => 2000,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 6000,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    $entry = InvestorProfitEntry::query()->where('sale_id', $sale->id)->first();
    $entry->markAsPaid('paid in test');
    $entry->refresh();

    $originalCurrency = (float) $entry->profit_amount_currency;
    $originalAfn = (float) $entry->profit_amount_afn;
    $originalRate = (float) $entry->exchange_rate;
    $originalCode = $entry->currency_code;

    $saleItem = SaleItem::query()->where('sale_id', $sale->id)->first();
    $batch = ProductBatch::query()->where('variant_id', $variant->id)->first();

    $fx->postRate($usd, $afn, '95', '2026-03-15 08:00:00', null, 'test');

    $return = srCreateDraftReturn($sale, $admin, $saleItem, $batch, 3);
    srFinalize($return);

    $entry->refresh();
    $adjustment = InvestorProfitEntry::query()
        ->where('sale_item_id', $saleItem->id)
        ->where('entry_type', 'adjustment')
        ->first();

    expect($entry->status)->toBe('paid')
        ->and((float) $entry->profit_amount_currency)->toBe($originalCurrency)
        ->and((float) $entry->profit_amount_afn)->toBe($originalAfn)
        ->and((float) $entry->exchange_rate)->toBe($originalRate)
        ->and($entry->currency_code)->toBe($originalCode)
        ->and($adjustment)->not->toBeNull()
        ->and((float) $adjustment->profit_amount_afn)->toBe(-1 * $originalAfn)
        ->and((float) $adjustment->profit_amount_currency)->toBe(-1 * $originalCurrency)
        ->and((float) $adjustment->exchange_rate)->toBe($originalRate);
});

test('duplicate finalize and over-return quantity are prevented', function () {
    srCurrencies();
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

    $variant = ProductVariant::factory()->create();

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 5,
        'purchase_price_afn' => 1000,
        'sale_price_afn' => 1500,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'investment_id' => $investment->id,
        'profit_per_unit' => 100,
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 3,
            'sale_price_afn' => 1500,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 4500,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    $saleItem = SaleItem::query()->where('sale_id', $sale->id)->first();
    $batch = ProductBatch::query()->where('variant_id', $variant->id)->first();

    $return = srCreateDraftReturn($sale, $admin, $saleItem, $batch, 3);
    srFinalize($return);
    expect($return->fresh()->status)->toBe(SaleReturn::STATUS_COMPLETED);

    // Second finalize is a no-op (idempotent) — no double reverse.
    $profitAfterFirst = (float) InvestorProfitEntry::query()
        ->where('sale_id', $sale->id)
        ->whereIn('status', ['pending', 'paid', 'cancelled'])
        ->sum('profit_amount_afn');

    srFinalize($return->fresh(['items']));

    $profitAfterSecond = (float) InvestorProfitEntry::query()
        ->where('sale_id', $sale->id)
        ->whereIn('status', ['pending', 'paid', 'cancelled'])
        ->sum('profit_amount_afn');

    expect($profitAfterSecond)->toBe($profitAfterFirst)
        ->and((int) $batch->fresh()->remaining_quantity)->toBe(5);

    // UI helper: already returned blocks further quantity.
    $controller = app(SaleReturnController::class);
    $method = new ReflectionMethod($controller, 'buildSaleItemsForReturn');
    $method->setAccessible(true);
    $items = $method->invoke($controller, $sale->fresh(['items']));
    $row = collect($items)->firstWhere('id', $saleItem->id);

    expect($row['already_returned'])->toBe(3)
        ->and($row['max_returnable'])->toBe(0);
});

test('unauthorized investor-profit-exceeds-gross sale remains hard-blocked with no silent reduction', function () {
    srCurrencies();
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'branch_id' => $branch->id]);
    $investor = Investor::factory()->create();
    $investment = Investment::factory()->create([
        'investor_id' => $investor->id,
        'source_type' => 'cash',
        'capital_amount_afn' => 20000,
        'available_amount_afn' => 20000,
    ]);

    $variant = ProductVariant::factory()->create();

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 5,
        'purchase_price_afn' => 1000,
        'sale_price_afn' => 1100,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'investment_id' => $investment->id,
        'profit_per_unit' => 200, // exceeds gross 100
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    $payload = [
        'branch_id' => $branch->id,
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_price_afn' => 1100,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 1100,
            'payment_method' => 'cash',
        ]],
    ];

    expect(fn () => app(SaleService::class)->createSale($payload, $cashier))
        ->toThrow(ValidationException::class);

    expect(fn () => app(SaleService::class)->createSale($payload, $admin))
        ->toThrow(ValidationException::class);

    // No override permission exists in ROLE_PERMISSIONS — exception flow intentionally not implemented.
    expect($admin->hasPermission('sales.force_insufficient_investor_profit'))->toBeFalse()
        ->and($cashier->hasPermission('sales.force_insufficient_investor_profit'))->toBeFalse()
        ->and(Sale::query()->count())->toBe(0);
});
