<?php

/**
 * Seeds and reconciles the full QA workflow against zuha_testing only.
 * Uses RefreshDatabase (Pest Feature default) then QaCompleteWorkflowSeeder.
 */

use App\Models\Inventory;
use App\Models\Investor;
use App\Models\InvestorProfitEntry;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\Setting;
use Database\Seeders\QaCompleteWorkflowSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    expect(config('database.connections.mysql.database'))->toBe('zuha_testing');
});

it('seeds the complete QA workflow idempotently with relational integrity', function () {
    $this->seed(QaCompleteWorkflowSeeder::class);

    // Reference investors
    $king = Investor::query()->where('username', 'new')->first();
    $mustafa = Investor::query()->where('username', 'AnsariUAE')->first();

    expect($king)->not->toBeNull()
        ->and($king->status)->toBe('blocked')
        ->and($king->wallet)->not->toBeNull()
        ->and($mustafa)->not->toBeNull()
        ->and($mustafa->status)->toBe('active')
        ->and($mustafa->wallet)->not->toBeNull();

    // Purchases → batches
    $purchaseIds = Product::query()
        ->whereNotNull('variant_id')
        ->where('notes', 'like', 'QA-PO-%')
        ->pluck('id');
    expect($purchaseIds)->not->toBeEmpty();

    $batchCount = ProductBatch::query()->whereIn('purchase_item_id', $purchaseIds)->count();
    expect($batchCount)->toBe($purchaseIds->count());

    // Inventory reconciles with batch remaining
    $mismatches = 0;
    $rows = ProductBatch::query()
        ->select('branch_id', 'variant_id', DB::raw('SUM(remaining_quantity) as batch_qty'))
        ->groupBy('branch_id', 'variant_id')
        ->get();
    foreach ($rows as $row) {
        $inv = (int) Inventory::query()
            ->where('branch_id', $row->branch_id)
            ->where('variant_id', $row->variant_id)
            ->value('quantity');
        if ($inv !== (int) $row->batch_qty) {
            $mismatches++;
        }
    }
    expect($mismatches)->toBe(0);

    // Sales exist via SaleService markers
    $sales = Sale::query()->where('notes', 'like', 'QA-SALE-%')->where('status', 'completed')->get();
    expect($sales->count())->toBeGreaterThanOrEqual(10);

    // Profit identity: gross ≈ investor + store
    foreach ($sales as $sale) {
        $gross = round((float) $sale->total_profit_afn, 2);
        $inv = round((float) $sale->total_investor_profit_afn, 2);
        $store = round((float) $sale->store_profit_afn, 2);
        expect(abs(round($inv + $store, 2) - $gross))->toBeLessThanOrEqual(0.05);
    }

    // Payments match sale.paid_afn
    foreach ($sales as $sale) {
        $paid = (float) SalePayment::query()->where('sale_id', $sale->id)->sum('amount_afn');
        expect(abs($paid - (float) $sale->paid_afn))->toBeLessThanOrEqual(0.05);
    }

    // Investor profits reference sales (no orphan fabricated profits for QA sales path)
    expect(
        InvestorProfitEntry::query()->where('entry_type', 'profit')->whereNull('sale_id')->count()
    )->toBe(0);

    $qaSaleIds = $sales->pluck('id');
    expect(
        InvestorProfitEntry::query()->whereIn('sale_id', $qaSaleIds)->count()
    )->toBeGreaterThan(0);

    // Returns exist and reference sales
    expect(SaleReturn::query()->where('reason', 'like', 'QA-RETURN-%')->count())->toBeGreaterThanOrEqual(1);

    // No negative batches
    expect(ProductBatch::query()->where('remaining_quantity', '<', 0)->count())->toBe(0);

    // Marker setting written
    expect(Setting::query()->where('key', 'qa_complete_workflow_seeded_at')->exists())->toBeTrue();

    // Idempotency: re-run does not duplicate QA sales
    $saleCountBefore = Sale::query()->where('notes', 'like', 'QA-SALE-%')->count();
    $purchaseCountBefore = Product::query()->where('notes', 'like', 'QA-PO-%')->whereNotNull('variant_id')->count();

    $this->seed(QaCompleteWorkflowSeeder::class);

    expect(Sale::query()->where('notes', 'like', 'QA-SALE-%')->count())->toBe($saleCountBefore)
        ->and(
            Product::query()->where('notes', 'like', 'QA-PO-%')->whereNotNull('variant_id')->count()
        )->toBe($purchaseCountBefore);
});

it('blocks store-owned stock from inventing investor profit on QA cash sale', function () {
    $this->seed(QaCompleteWorkflowSeeder::class);

    $sale = Sale::query()->where('notes', 'QA-SALE-CASH-SWY')->first();
    expect($sale)->not->toBeNull()
        ->and((float) $sale->total_investor_profit_afn)->toBe(0.0)
        ->and((float) $sale->store_profit_afn)->toBeGreaterThan(0);
});

it('allocates investor profit from historical batch ownership on Ansari Al Fareed sale', function () {
    $this->seed(QaCompleteWorkflowSeeder::class);

    $mustafa = Investor::query()->where('username', 'AnsariUAE')->first();
    $sale = Sale::query()->where('notes', 'QA-SALE-INV-ANSARI-AF')->first();

    expect($sale)->not->toBeNull()
        ->and((float) $sale->total_investor_profit_afn)->toBeGreaterThan(0);

    $entry = InvestorProfitEntry::query()
        ->where('sale_id', $sale->id)
        ->where('investor_id', $mustafa->id)
        ->where('entry_type', 'profit')
        ->first();

    expect($entry)->not->toBeNull()
        ->and((float) $entry->profit_amount_afn)->toBeGreaterThan(0);
});
