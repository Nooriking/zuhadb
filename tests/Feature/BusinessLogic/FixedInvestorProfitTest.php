<?php

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorProfitEntry;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Validation\ValidationException;

function fipEnsureCurrencies(): array
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

    $fx = app(CurrencyService::class);
    $fx->postRate($usd, $afn, '70', '2026-01-01 08:00:00', null, 'test');
    $fx->postRate($aed, $afn, '19', '2026-01-01 08:00:00', null, 'test');

    return compact('afn', 'usd', 'aed');
}

/**
 * @return array{branch: Branch, admin: User, investor: Investor, variant: ProductVariant}
 */
function fipReceiveInvestorStock(
    float $unitCostAfn,
    float $salePriceAfn,
    float $profitPerUnitAfn,
    int $qty = 10,
    ?Investor $investor = null,
): array {
    fipEnsureCurrencies();

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $investor ??= Investor::factory()->create();
    $investor->getOrCreateWallet();

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => $unitCostAfn,
        'sale_price_afn' => $salePriceAfn,
    ]);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => $qty,
        'purchase_price_afn' => $unitCostAfn,
        'sale_price_afn' => $salePriceAfn,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'profit_per_unit' => $profitPerUnitAfn,
        'usd_to_afn_rate' => '70',
        'aed_to_afn_rate' => '19',
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    return compact('branch', 'admin', 'investor', 'variant');
}

test('example A: cost 700 sale 1200 investor 200 qty 1 → gross 500 inv 200 company 300', function () {
    ['branch' => $branch, 'admin' => $admin, 'variant' => $variant] = fipReceiveInvestorStock(700, 1200, 200);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_price_afn' => 1200,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 1200,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    $item = SaleItem::query()->where('sale_id', $sale->id)->first();

    expect((float) $sale->total_profit_afn)->toBe(500.0)
        ->and((float) $sale->total_investor_profit_afn)->toBe(200.0)
        ->and((float) $sale->store_profit_afn)->toBe(300.0)
        ->and((float) $item->gross_profit_afn)->toBe(500.0)
        ->and((float) $item->investor_profit_afn)->toBe(200.0)
        ->and((float) $item->store_profit_afn)->toBe(300.0)
        ->and((float) InvestorProfitEntry::query()->where('sale_id', $sale->id)->sum('profit_amount_afn'))->toBe(200.0);
});

test('example B: cost 700 sale 2000 investor 200 qty 1 → gross 1300 inv 200 company 1100', function () {
    ['branch' => $branch, 'admin' => $admin, 'variant' => $variant] = fipReceiveInvestorStock(700, 2000, 200);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_price_afn' => 2000,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 2000,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    expect((float) $sale->total_profit_afn)->toBe(1300.0)
        ->and((float) $sale->total_investor_profit_afn)->toBe(200.0)
        ->and((float) $sale->store_profit_afn)->toBe(1100.0);
});

test('example C: cost 700 sale 2000 investor 200 qty 5 → gross 6500 inv 1000 company 5500', function () {
    ['branch' => $branch, 'admin' => $admin, 'variant' => $variant] = fipReceiveInvestorStock(700, 2000, 200, 10);

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

    expect((float) $sale->total_profit_afn)->toBe(6500.0)
        ->and((float) $sale->total_investor_profit_afn)->toBe(1000.0)
        ->and((float) $sale->store_profit_afn)->toBe(5500.0)
        ->and((float) InvestorProfitEntry::query()->where('sale_id', $sale->id)->sum('profit_amount_afn'))->toBe(1000.0);
});

test('example D: two batches two investors different fixed PPUs calculated independently', function () {
    fipEnsureCurrencies();

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $investorA = Investor::factory()->create();
    $investorB = Investor::factory()->create();
    $investorA->getOrCreateWallet();
    $investorB->getOrCreateWallet();

    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 700,
        'sale_price_afn' => 2000,
    ]);

    // Batch A: investor A, PPU 200, received earlier
    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 2,
        'purchase_price_afn' => 700,
        'sale_price_afn' => 2000,
        'funding_source' => 'investor',
        'investor_id' => $investorA->id,
        'profit_per_unit' => 200,
        'usd_to_afn_rate' => '70',
        'aed_to_afn_rate' => '19',
        'purchase_date' => '2026-01-01',
    ], $admin, $branch->id);

    // Batch B: investor B, PPU 100
    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 2,
        'purchase_price_afn' => 700,
        'sale_price_afn' => 2000,
        'funding_source' => 'investor',
        'investor_id' => $investorB->id,
        'profit_per_unit' => 100,
        'usd_to_afn_rate' => '70',
        'aed_to_afn_rate' => '19',
        'purchase_date' => '2026-01-02',
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 3, // 2 from A + 1 from B
            'sale_price_afn' => 2000,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 6000,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    $profitA = (float) InvestorProfitEntry::query()
        ->where('sale_id', $sale->id)
        ->where('investor_id', $investorA->id)
        ->sum('profit_amount_afn');
    $profitB = (float) InvestorProfitEntry::query()
        ->where('sale_id', $sale->id)
        ->where('investor_id', $investorB->id)
        ->sum('profit_amount_afn');

    // A: 2 × 200 = 400; B: 1 × 100 = 100; total investor 500
    // Gross: (2000-700)×3 = 3900; company = 3900 - 500 = 3400
    expect($profitA)->toBe(400.0)
        ->and($profitB)->toBe(100.0)
        ->and((float) $sale->total_investor_profit_afn)->toBe(500.0)
        ->and((float) $sale->total_profit_afn)->toBe(3900.0)
        ->and((float) $sale->store_profit_afn)->toBe(3400.0);
});

test('example E: blocks sale when investor PPU exceeds gross profit per unit', function () {
    // Cost 700, sale 800 → gross 100; investor PPU 200 > 100 → block
    ['branch' => $branch, 'admin' => $admin, 'variant' => $variant] = fipReceiveInvestorStock(700, 2000, 200);

    // Lower catalog sale is fine on receive; selling below gross coverage must fail.
    expect(fn () => app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_price_afn' => 800,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 800,
            'payment_method' => 'cash',
        ]],
    ], $admin))->toThrow(ValidationException::class);

    expect(InvestorProfitEntry::query()->count())->toBe(0)
        ->and((int) ProductBatch::query()->where('variant_id', $variant->id)->sum('remaining_quantity'))->toBe(10);
});

test('USD-configured investor PPU converts via FxBoard at receive', function () {
    ['afn' => $afn, 'usd' => $usd] = fipEnsureCurrencies();

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $investor = Investor::factory()->create();
    $investor->getOrCreateWallet();
    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 700,
        'sale_price_afn' => 2000,
    ]);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 5,
        'purchase_price_afn' => 700,
        'sale_price_afn' => 2000,
        'funding_source' => 'investor',
        'investor_id' => $investor->id,
        'profit_per_unit' => 2, // 2 USD
        'profit_currency_id' => $usd->id,
        'usd_to_afn_rate' => '70',
        'aed_to_afn_rate' => '19',
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    $batch = ProductBatch::query()->where('variant_id', $variant->id)->first();

    expect((float) $batch->profit_per_unit_afn)->toBe(140.0) // 2 × 70
        ->and((float) ($batch->profit_per_unit_currency ?? 0))->toBe(2.0)
        ->and((string) $batch->profit_currency_code)->toBe('USD')
        ->and((float) ($batch->profit_per_unit_usd ?? 0))->toBe(2.0);
});

test('HTTP receive with investment_id allocates cash capital', function () {
    fipEnsureCurrencies();

    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'branch_id' => $branch->id,
    ]);
    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 1000,
        'sale_price_afn' => 1400,
    ]);
    $investor = Investor::factory()->create();
    $investment = Investment::factory()->create([
        'investor_id' => $investor->id,
        'source_type' => 'cash',
        'capital_amount_afn' => 5000,
        'available_amount_afn' => 5000,
        'allocated_amount_afn' => 0,
        'status' => 'active',
    ]);

    $this->actingAs($user)->post('/products/receive', [
        'variant_id' => $variant->id,
        'quantity' => 2,
        'purchase_price_afn' => 700,
        'sale_price_afn' => 1200,
        'funding_source' => 'investor',
        'investor_id' => (string) $investor->id,
        'investment_id' => $investment->id,
        'profit_per_unit' => 70,
        'usd_to_afn_rate' => '70',
        'aed_to_afn_rate' => '19',
        'exchange_rate' => 70,
        'purchase_date' => now()->toDateString(),
    ])->assertRedirect();

    $investment->refresh();
    $batch = ProductBatch::query()->where('variant_id', $variant->id)->first();

    expect((float) $investment->available_amount_afn)->toBe(3600.0)
        ->and((float) $investment->allocated_amount_afn)->toBe(1400.0)
        ->and($batch->investor_id)->toBe($investor->id)
        ->and((float) $batch->profit_per_unit_afn)->toBe(70.0);
});
