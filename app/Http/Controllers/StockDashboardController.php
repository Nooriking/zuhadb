<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $branchId = $request->user()?->branch_id;

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

        return Inertia::render('dashboards/stock', [
            'kpis' => [
                'total_stock' => (int) (clone $stock)->sum('quantity'),
                'low_stock' => (clone $low)->count(),
                'out_of_stock' => (clone $stock)->where('quantity', '<=', 0)->count(),
            ],
            'lowItems' => $lowItems,
            'movements' => $movements,
        ]);
    }
}
