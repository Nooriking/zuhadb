<?php

/**
 * Priority 1 — Inventory valuation consistency across Admin / Stock / Accountant.
 * Authoritative method: remaining_quantity × historical unit_cost_afn on product_batches.
 * Runs only against zuha_testing.
 */

use App\Models\Branch;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CurrencyService;

test('ProductBatch valuationSummary splits store and investor ownership via investment_id', function () {
    $branch = Branch::factory()->create();
    $variant = ProductVariant::factory()->create();

    // Store-owned batch
    ProductBatch::factory()->create([
        'branch_id' => $branch->id,
        'variant_id' => $variant->id,
        'investment_id' => null,
        'quantity' => 10,
        'remaining_quantity' => 10,
        'unit_cost_afn' => 70,
        'total_cost_afn' => 700,
        'trash' => false,
        'status' => 'active',
    ]);

    $investor = Investor::factory()->create();
    $investment = Investment::factory()->create([
        'investor_id' => $investor->id,
        'status' => 'active',
    ]);

    // Investor-funded batch (partially depleted)
    ProductBatch::factory()->create([
        'branch_id' => $branch->id,
        'variant_id' => $variant->id,
        'investment_id' => $investment->id,
        'quantity' => 8,
        'remaining_quantity' => 5,
        'unit_cost_afn' => 100,
        'total_cost_afn' => 800,
        'trash' => false,
        'status' => 'active',
    ]);

    // Fully depleted — must be excluded
    ProductBatch::factory()->create([
        'branch_id' => $branch->id,
        'variant_id' => $variant->id,
        'investment_id' => null,
        'quantity' => 3,
        'remaining_quantity' => 0,
        'unit_cost_afn' => 999,
        'total_cost_afn' => 2997,
        'trash' => false,
        'status' => 'depleted',
    ]);

    $summary = ProductBatch::valuationSummary($branch->id);

    expect($summary['store_afn'])->toBe(700.0)
        ->and($summary['investor_afn'])->toBe(500.0)
        ->and($summary['total_afn'])->toBe(1200.0);
});

test('admin stock dashboard and accountant inventory value use the same batch valuation', function () {
    $branch = Branch::factory()->create();
    $variant = ProductVariant::factory()->create();

    ProductBatch::factory()->create([
        'branch_id' => $branch->id,
        'variant_id' => $variant->id,
        'investment_id' => null,
        'quantity' => 4,
        'remaining_quantity' => 4,
        'unit_cost_afn' => 250,
        'total_cost_afn' => 1000,
        'trash' => false,
        'status' => 'active',
        'received_date' => now()->toDateString(),
    ]);

    $investor = Investor::factory()->create();
    $investment = Investment::factory()->create([
        'investor_id' => $investor->id,
        'status' => 'active',
    ]);

    ProductBatch::factory()->create([
        'branch_id' => $branch->id,
        'variant_id' => $variant->id,
        'investment_id' => $investment->id,
        'quantity' => 2,
        'remaining_quantity' => 2,
        'unit_cost_afn' => 400,
        'total_cost_afn' => 800,
        'trash' => false,
        'status' => 'active',
        'received_date' => now()->toDateString(),
    ]);

    $expectedTotal = 1800.0; // 4*250 + 2*400
    $expected = app(CurrencyService::class)->threeCurrencyFromLedger($expectedTotal);

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('kpis.stock_value_afn', fn ($v) => (float) $v === $expectedTotal)
        );

    $accountant = User::factory()->create(['role' => User::ROLE_ACCOUNTANT]);
    $this->actingAs($accountant)
        ->get('/dashboard/accountant')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('kpis3c.inventory_value.afn', fn ($v) => (float) $v === (float) $expected['afn'])
        );

    $keeper = User::factory()->create([
        'role' => User::ROLE_STOCK_KEEPER,
        'branch_id' => $branch->id,
    ]);
    $this->actingAs($keeper)
        ->get('/dashboard/stock')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('kpis3c.inventory_value.afn', fn ($v) => (float) $v === (float) $expected['afn'])
            ->where('kpis3c.store_owned_value.afn', fn ($v) => (float) $v === 1000.0)
            ->where('kpis3c.investor_owned_value.afn', fn ($v) => (float) $v === 800.0)
        );
});

test('multi-currency purchase snapshots keep historical unit_cost_afn for valuation', function () {
    $branch = Branch::factory()->create();

    // Historical USD purchase snapshotted at 70 AFN — never recalculated with today's board.
    ProductBatch::factory()->create([
        'branch_id' => $branch->id,
        'investment_id' => null,
        'quantity' => 3,
        'remaining_quantity' => 3,
        'unit_cost_currency' => 10,
        'exchange_rate' => 70,
        'unit_cost_afn' => 700,
        'total_cost_afn' => 2100,
        'trash' => false,
        'status' => 'active',
    ]);

    $summary = ProductBatch::valuationSummary($branch->id);
    expect($summary['total_afn'])->toBe(2100.0);
});

test('branch separation excludes other branch batches from valuation', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    ProductBatch::factory()->create([
        'branch_id' => $branchA->id,
        'investment_id' => null,
        'remaining_quantity' => 5,
        'unit_cost_afn' => 100,
        'trash' => false,
        'status' => 'active',
    ]);
    ProductBatch::factory()->create([
        'branch_id' => $branchB->id,
        'investment_id' => null,
        'remaining_quantity' => 5,
        'unit_cost_afn' => 999,
        'trash' => false,
        'status' => 'active',
    ]);

    expect(ProductBatch::valuationSummary($branchA->id)['total_afn'])->toBe(500.0)
        ->and(ProductBatch::valuationSummary($branchB->id)['total_afn'])->toBe(4995.0);
});
