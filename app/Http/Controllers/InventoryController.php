<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\ProductBatch;
use App\Services\ExchangeRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request, ExchangeRateService $rates): Response
    {
        $user = $request->user();
        $branchId = $request->input('branch_id');
        $search = $request->input('search');
        $stockFilter = $request->input('stock_filter');
        $brandId = $request->input('brand_id');

        if ($user?->isBranchScoped()) {
            $branchId = $user->branch_id;
        }

        $query = Inventory::query()
            ->with([
                'variant.product.brand:id,name',
                'variant.prices' => fn ($q) => $q->where('is_active', 1),
                'variant.prices.currency:id,code,symbol',
                'branch:id,name,code',
            ])
            ->notTrashed()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($search, function ($q) use ($search) {
                $q->whereHas('variant', function ($v) use ($search) {
                    $v->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('barcodes', fn ($b) => $b->where('barcode', 'like', "%{$search}%"));
                });
            })
            ->when($brandId, fn ($q) => $q->whereHas('variant.product', fn ($p) => $p->where('brand_id', $brandId)))
            ->when($stockFilter === 'in_stock', fn ($q) => $q->where('available_quantity', '>', 0))
            ->when($stockFilter === 'low_stock', fn ($q) => $q
                ->where('available_quantity', '>', 0)
                ->where('available_quantity', '<=', Inventory::LOW_STOCK_THRESHOLD))
            ->when($stockFilter === 'out_of_stock', fn ($q) => $q->where('available_quantity', '<=', 0))
            ->orderBy('available_quantity');

        $inventories = $query->paginate(30)->withQueryString();

        $inventories->through(function (Inventory $inv) {
            $avgCost = (float) $inv->average_cost_afn;
            $qty = (int) $inv->available_quantity;
            $totalCostAfn = round($avgCost * $qty, 2);

            $salePrice = $inv->variant?->prices
                ?->first(fn ($p) => $p->price_type === 'sale' && strtoupper((string) $p->currency_code) === 'AFN');
            $salePriceAfn = $salePrice ? (float) $salePrice->amount : 0.0;
            $totalSaleAfn = round($salePriceAfn * $qty, 2);

            return [
                'id' => $inv->id,
                'branch' => $inv->branch ? [
                    'id' => $inv->branch->id,
                    'name' => $inv->branch->name,
                    'code' => $inv->branch->code,
                ] : null,
                'variant' => $inv->variant ? [
                    'id' => $inv->variant->id,
                    'name' => $inv->variant->name,
                    'volume_ml' => $inv->variant->volume_ml,
                    'concentration' => $inv->variant->concentration,
                    'sku' => $inv->variant->sku,
                    'product' => $inv->variant->product ? [
                        'id' => $inv->variant->product->id,
                        'name' => $inv->variant->product->name,
                        'brand' => $inv->variant->product->brand,
                    ] : null,
                ] : null,
                'quantity' => (int) $inv->quantity,
                'reserved_quantity' => (int) $inv->reserved_quantity,
                'available_quantity' => $qty,
                'average_cost_afn' => $avgCost,
                'total_cost_afn' => $totalCostAfn,
                'sale_price_afn' => $salePriceAfn,
                'total_sale_afn' => $totalSaleAfn,
                'last_movement_at' => optional($inv->last_movement_at)?->toIso8601String(),
            ];
        });

        $summaryBase = Inventory::query()
            ->notTrashed()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $summary = [
            'total_quantity' => (int) (clone $summaryBase)->sum('available_quantity'),
            'total_cost_afn' => (float) (clone $summaryBase)->sum(DB::raw('available_quantity * average_cost_afn')),
            'low_stock_count' => (clone $summaryBase)
                ->where('available_quantity', '>', 0)
                ->where('available_quantity', '<=', Inventory::LOW_STOCK_THRESHOLD)
                ->count(),
            'out_of_stock_count' => (clone $summaryBase)->where('available_quantity', '<=', 0)->count(),
            'variant_count' => (clone $summaryBase)->count(),
        ];

        return Inertia::render('inventory/index', [
            'inventories' => $inventories,
            'summary' => $summary,
            'branches' => Branch::query()
                ->notTrashed()
                ->active()
                ->visibleTo($user)
                ->get(['id', 'name', 'code']),
            'brands' => Brand::query()->notTrashed()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'branch_id' => $branchId,
                'search' => $search,
                'stock_filter' => $stockFilter,
                'brand_id' => $brandId,
            ],
            'lowStockThreshold' => Inventory::LOW_STOCK_THRESHOLD,
            'exchangeBoard' => $rates->flatBoard(),
        ]);
    }

    public function show(Inventory $inventory, ExchangeRateService $rates): Response
    {
        abort_if($inventory->trash, 404);

        $inventory->load([
            'variant.product.brand',
            'variant.prices.currency',
            'branch:id,name,code',
        ]);

        $movements = InventoryTransaction::query()
            ->where('branch_id', $inventory->branch_id)
            ->where('variant_id', $inventory->variant_id)
            ->with([
                'batch:id,batch_code,purchase_date,invoice_number,investment_id',
                'creator:id,name',
            ])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (InventoryTransaction $t) => [
                'id' => $t->id,
                'type' => $t->type,
                'quantity' => $t->quantity,
                'before_quantity' => $t->before_quantity,
                'after_quantity' => $t->after_quantity,
                'reference_type' => $t->reference_type,
                'reference_id' => $t->reference_id,
                'notes' => $t->notes,
                'batch' => $t->batch ? [
                    'id' => $t->batch->id,
                    'batch_code' => $t->batch->batch_code,
                    'purchase_date' => optional($t->batch->purchase_date)?->toDateString(),
                    'invoice_number' => $t->batch->invoice_number,
                ] : null,
                'created_by' => $t->creator?->name,
                'created_at' => optional($t->created_at)?->toIso8601String(),
            ]);

        $batches = ProductBatch::query()
            ->where('branch_id', $inventory->branch_id)
            ->where('variant_id', $inventory->variant_id)
            ->where('status', 'active')
            ->where('trash', false)
            ->with(['investment.investor:id,name', 'purchaseCurrency:id,code,symbol'])
            ->orderBy('received_date')
            ->orderBy('id')
            ->get()
            ->map(fn (ProductBatch $b) => [
                'id' => $b->id,
                'batch_code' => $b->batch_code,
                'quantity' => $b->quantity,
                'remaining_quantity' => $b->remaining_quantity,
                'unit_cost_afn' => (float) $b->unit_cost_afn,
                'unit_cost_currency' => (float) $b->unit_cost_currency,
                'purchase_currency' => $b->purchaseCurrency?->code,
                'exchange_rate' => (float) $b->exchange_rate,
                'purchase_date' => optional($b->purchase_date)?->toDateString(),
                'invoice_number' => $b->invoice_number,
                'investment_id' => $b->investment_id,
                'investor' => $b->investment?->investor?->name,
                'sale_price_afn' => (float) $b->sale_price_afn,
                'store_profit_per_unit_afn' => (float) $b->store_profit_per_unit_afn,
            ]);

        $stats = [
            'total_purchased' => (int) InventoryTransaction::query()
                ->where('branch_id', $inventory->branch_id)
                ->where('variant_id', $inventory->variant_id)
                ->where('type', 'purchase')
                ->sum('quantity'),
            'total_sold' => (int) abs(InventoryTransaction::query()
                ->where('branch_id', $inventory->branch_id)
                ->where('variant_id', $inventory->variant_id)
                ->where('type', 'sale')
                ->sum('quantity')),
            'total_returned' => (int) InventoryTransaction::query()
                ->where('branch_id', $inventory->branch_id)
                ->where('variant_id', $inventory->variant_id)
                ->where('type', 'return')
                ->sum('quantity'),
        ];

        $qty = (int) $inventory->available_quantity;
        $avgCost = (float) $inventory->average_cost_afn;
        $salePrice = $inventory->variant?->prices
            ?->first(fn ($p) => $p->price_type === 'sale' && strtoupper((string) $p->currency_code) === 'AFN');
        $salePriceAfn = $salePrice ? (float) $salePrice->amount : 0.0;

        return Inertia::render('inventory/show', [
            'inventory' => [
                'id' => $inventory->id,
                'quantity' => (int) $inventory->quantity,
                'reserved_quantity' => (int) $inventory->reserved_quantity,
                'available_quantity' => $qty,
                'average_cost_afn' => $avgCost,
                'total_cost_afn' => round($avgCost * $qty, 2),
                'sale_price_afn' => $salePriceAfn,
                'total_sale_afn' => round($salePriceAfn * $qty, 2),
                'last_movement_at' => optional($inventory->last_movement_at)?->toIso8601String(),
                'branch' => $inventory->branch,
                'variant' => $inventory->variant ? [
                    'id' => $inventory->variant->id,
                    'name' => $inventory->variant->name,
                    'sku' => $inventory->variant->sku,
                    'volume_ml' => $inventory->variant->volume_ml,
                    'concentration' => $inventory->variant->concentration,
                    'product' => $inventory->variant->product ? [
                        'id' => $inventory->variant->product->id,
                        'name' => $inventory->variant->product->name,
                        'brand' => $inventory->variant->product->brand,
                    ] : null,
                    'prices' => $inventory->variant->prices->map(fn ($p) => [
                        'id' => $p->id,
                        'price_type' => $p->price_type,
                        'currency_code' => $p->currency_code,
                        'amount' => (float) $p->amount,
                        'currency' => $p->currency,
                    ]),
                ] : null,
            ],
            'movements' => $movements,
            'batches' => $batches,
            'stats' => $stats,
            'exchangeBoard' => $rates->flatBoard(),
        ]);
    }

    public function adjust(Request $request, Inventory $inventory): RedirectResponse
    {
        abort_if($inventory->trash, 404);

        $validated = $request->validate([
            'new_quantity' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        Inventory::adjust(
            branchId: $inventory->branch_id,
            variantId: $inventory->variant_id,
            newQty: $validated['new_quantity'],
            notes: $validated['notes'] ?? 'Manual adjustment'
        );

        return back()->with('success', 'Stock adjusted successfully.');
    }

    public function lowStock(Request $request, ExchangeRateService $rates): Response
    {
        $user = $request->user();
        $branchId = $request->input('branch_id');

        if ($user?->isBranchScoped()) {
            $branchId = $user->branch_id;
        }

        $inventories = Inventory::query()
            ->with([
                'branch:id,name,code',
                'variant:id,product_id,name,volume_ml,concentration,sku',
                'variant.product:id,name,brand_id',
                'variant.product.brand:id,name',
            ])
            ->notTrashed()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('available_quantity', '>', 0)
            ->where('available_quantity', '<=', Inventory::LOW_STOCK_THRESHOLD)
            ->orderBy('available_quantity')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('inventory/low-stock', [
            'inventories' => $inventories,
            'lowStockThreshold' => Inventory::LOW_STOCK_THRESHOLD,
            'exchangeBoard' => $rates->flatBoard(),
        ]);
    }

    public function transactions(Request $request, ExchangeRateService $rates): Response
    {
        $user = $request->user();
        $branchId = $request->query('branch_id');
        $type = $request->query('type');
        $variantId = $request->query('variant_id');

        if ($user?->isBranchScoped()) {
            $branchId = $user->branch_id;
        }

        $transactions = InventoryTransaction::query()
            ->with([
                'branch:id,name,code',
                'variant.product:id,name,brand_id',
                'variant.product.brand:id,name',
                'batch:id,batch_code,purchase_date,invoice_number',
                'creator:id,name',
            ])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($variantId, fn ($q) => $q->where('variant_id', $variantId))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('inventory/transactions', [
            'transactions' => $transactions,
            'branches' => Branch::query()
                ->notTrashed()
                ->active()
                ->visibleTo($user)
                ->get(['id', 'name', 'code']),
            'types' => InventoryTransaction::TYPES,
            'filters' => [
                'branch_id' => $branchId,
                'type' => $type,
                'variant_id' => $variantId,
            ],
            'exchangeBoard' => $rates->flatBoard(),
        ]);
    }
}
