<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\ProductBatch;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockDashboardController extends Controller
{
    public function __construct(private readonly CurrencyService $currencies) {}

    public function index(Request $request): Response
    {
        $branchId = $request->user()?->branch_id;
        $reporting = $this->currencies->reportingContext();
        $fxBoard = $this->currencies->dashboardFxBoard();
        $money = fn (float|string|null $amount): float => $this->currencies->reportAmount((float) ($amount ?? 0));

        $stock = Inventory::notTrashed()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $low = (clone $stock)->whereHas('variant', function ($q) {
            $q->where('minimum_stock', '>', 0)
                ->whereColumn('inventories.quantity', '<=', 'product_variants.minimum_stock');
        });

        $lowItems = (clone $low)
            ->with(['variant:id,product_id,volume_ml,minimum_stock', 'variant.product:id,name'])
            ->orderBy('quantity')
            ->limit(8)
            ->get()
            ->map(fn (Inventory $row) => [
                'id' => $row->id,
                'product' => $row->variant?->product?->name ?? '—',
                'volume_ml' => $row->variant?->volume_ml,
                'quantity' => (int) $row->quantity,
                'min_stock' => (int) ($row->variant?->minimum_stock ?? 0),
            ]);

        $movements = InventoryTransaction::query()
            ->with(['variant:id,product_id', 'variant.product:id,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (InventoryTransaction $row) => [
                'id' => $row->id,
                'type' => $row->type,
                'product' => $row->variant?->product?->name ?? '—',
                'quantity' => (int) $row->quantity,
                'before_quantity' => (int) $row->before_quantity,
                'after_quantity' => (int) $row->after_quantity,
                'created_at' => optional($row->created_at)->toDateTimeString(),
            ]);

        // Batch-level remaining cost (historical AFN unit costs) — never catalog sale price.
        $valuationRow = ProductBatch::query()
            ->where('trash', false)
            ->where('remaining_quantity', '>', 0)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(remaining_quantity * unit_cost_afn), 0) as inventory_value_afn')
            ->selectRaw('COALESCE(SUM(CASE WHEN investor_id IS NULL THEN remaining_quantity * unit_cost_afn ELSE 0 END), 0) as store_value_afn')
            ->selectRaw('COALESCE(SUM(CASE WHEN investor_id IS NOT NULL THEN remaining_quantity * unit_cost_afn ELSE 0 END), 0) as investor_value_afn')
            ->first();

        $inventoryValueLedger = (float) ($valuationRow->inventory_value_afn ?? 0);
        $storeValueLedger = (float) ($valuationRow->store_value_afn ?? 0);
        $investorValueLedger = (float) ($valuationRow->investor_value_afn ?? 0);

        $purchaseCostLedger = (float) ProductBatch::query()
            ->where('trash', false)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('received_date', '>=', now()->startOfMonth()->toDateString())
            ->sum('total_cost_afn');

        $kpis3c = $this->currencies->mapThreeCurrency([
            'inventory_value' => $inventoryValueLedger,
            'store_owned_value' => $storeValueLedger,
            'investor_owned_value' => $investorValueLedger,
            'month_purchase_cost' => $purchaseCostLedger,
        ]);

        $topBatches = ProductBatch::query()
            ->where('trash', false)
            ->where('remaining_quantity', '>', 0)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['variant:id,product_id,volume_ml', 'variant.product:id,name'])
            ->orderByDesc(DB::raw('remaining_quantity * unit_cost_afn'))
            ->limit(8)
            ->get();

        $batch3c = $this->currencies->mapThreeCurrency(
            $topBatches->mapWithKeys(function (ProductBatch $b) {
                return ["b{$b->id}" => (float) $b->remaining_quantity * (float) $b->unit_cost_afn];
            })->all()
        );

        $valuedBatches = $topBatches->map(function (ProductBatch $b) use ($money, $batch3c) {
            $valueAfn = round((float) $b->remaining_quantity * (float) $b->unit_cost_afn, 2);

            return [
                'id' => $b->id,
                'batch_code' => $b->batch_code,
                'product' => $b->variant?->product?->name ?? '—',
                'volume_ml' => $b->variant?->volume_ml,
                'remaining' => (int) $b->remaining_quantity,
                'unit_cost_afn' => round((float) $b->unit_cost_afn, 2),
                'value' => $money($valueAfn),
                'value_ledger' => $valueAfn,
                'value_3c' => $batch3c["b{$b->id}"] ?? null,
                'investor_owned' => $b->investor_id !== null,
            ];
        });

        return Inertia::render('dashboards/stock', [
            'kpis' => [
                'total_stock' => (int) (clone $stock)->sum('quantity'),
                'low_stock' => (clone $low)->count(),
                'out_of_stock' => (clone $stock)->where('quantity', '<=', 0)->count(),
                'inventory_value' => $money($inventoryValueLedger),
                'store_owned_value' => $money($storeValueLedger),
                'investor_owned_value' => $money($investorValueLedger),
                'month_purchase_cost' => $money($purchaseCostLedger),
            ],
            'kpis3c' => $kpis3c,
            'lowItems' => $lowItems,
            'movements' => $movements,
            'valuedBatches' => $valuedBatches,
            'reporting' => $reporting,
            'fxBoard' => $fxBoard,
        ]);
    }
}
