<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    /* =========================================================
     |  INDEX — List inventory
     ========================================================= */

    public function index(Request $request): Response
    {
        $search      = $request->query('search');
        $branchId    = $request->query('branch_id');
        $stockStatus = $request->query('stock_status');
        $user        = $request->user();

        if ($user?->isBranchScoped()) {
            $branchId = $user->branch_id;
        }

        $query = Inventory::query()
            ->with([
                'branch:id,name,code',
                'variant:id,product_id,name,volume_ml,concentration,minimum_stock,sale_price_afn',
                'variant.product:id,name,brand_id',
                'variant.product.brand:id,name',
            ])
            ->notTrashed()
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($search, function ($q, $search) {
                $q->whereHas('variant.product', function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%");
                });
            });

        // Stock status filter
        if ($stockStatus === 'low') {
            $query->whereHas('variant', function ($q) {
                $q->whereColumn('inventories.quantity', '<=', 'product_variants.minimum_stock')
                  ->where('inventories.quantity', '>', 0);
            });
        } elseif ($stockStatus === 'out') {
            $query->where('quantity', '<=', 0);
        } elseif ($stockStatus === 'in') {
            $query->where('quantity', '>', 0);
        }

        $inventories = $query
            ->orderByDesc('quantity')
            ->paginate(20)
            ->withQueryString();

        $stockTotals = Inventory::notTrashed()
            ->when($user?->isBranchScoped(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->selectRaw('COUNT(*) as total_items, COALESCE(SUM(quantity), 0) as total_quantity')
            ->first();

        return Inertia::render('inventory/index', [
            'inventories' => $inventories,
            'branches'    => Branch::notTrashed()->visibleTo($user)->select('id', 'name', 'code')->get(),
            'filters'     => [
                'search'       => $search,
                'branch_id'    => $branchId,
                'stock_status' => $stockStatus,
            ],
            'stats' => [
                'total_items'    => (int) $stockTotals->total_items,
                'total_quantity' => (int) $stockTotals->total_quantity,
                'total_amount'   => (float) Inventory::query()
                    ->where('inventories.trash', false)
                    ->join('product_variants', 'product_variants.id', '=', 'inventories.variant_id')
                    ->where('product_variants.trash', false)
                    ->sum(DB::raw('inventories.quantity * product_variants.sale_price_afn')),
            ],
        ]);
    }

    /* =========================================================
     |  SHOW — Details + History
     ========================================================= */

    public function show(Inventory $inventory): Response
    {
        $inventory->load([
            'branch:id,name,code',
            'variant.product:id,name,brand_id',
            'variant.product.brand:id,name',
        ]);

        $transactions = InventoryTransaction::query()
            ->with(['batch:id,batch_code', 'creator:id,name'])
            ->where('branch_id', $inventory->branch_id)
            ->where('variant_id', $inventory->variant_id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $stats = [
            'total_purchased' => InventoryTransaction::where('branch_id', $inventory->branch_id)
                ->where('variant_id', $inventory->variant_id)
                ->where('type', 'purchase')
                ->sum('quantity'),

            'total_sold' => abs(InventoryTransaction::where('branch_id', $inventory->branch_id)
                ->where('variant_id', $inventory->variant_id)
                ->where('type', 'sale')
                ->sum('quantity')),

            'total_returned' => InventoryTransaction::where('branch_id', $inventory->branch_id)
                ->where('variant_id', $inventory->variant_id)
                ->where('type', 'return')
                ->sum('quantity'),
        ];

        return Inertia::render('inventory/show', [
            'inventory'    => $inventory,
            'transactions' => $transactions,
            'stats'        => $stats,
        ]);
    }

    /* =========================================================
     |  ADJUST — Manual stock adjustment
     ========================================================= */

    public function adjust(Request $request, Inventory $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'new_quantity' => ['required', 'integer', 'min:0'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        Inventory::adjust(
            branchId:  $inventory->branch_id,
            variantId: $inventory->variant_id,
            newQty:    $validated['new_quantity'],
            notes:     $validated['notes'] ?? 'Manual adjustment'
        );

        return back()->with('success', 'Stock adjusted successfully.');
    }

    /* =========================================================
     |  LOW STOCK — List low-stock items
     ========================================================= */

    public function lowStock(): Response
    {
        $inventories = Inventory::query()
            ->with([
                'branch:id,name,code',
                'variant.product:id,name,brand_id',
                'variant.product.brand:id,name',
            ])
            ->notTrashed()
            ->whereHas('variant', function ($q) {
                $q->whereColumn('inventories.quantity', '<=', 'product_variants.minimum_stock');
            })
            ->orderBy('quantity')
            ->paginate(20);

        return Inertia::render('inventory/low-stock', [
            'inventories' => $inventories,
        ]);
    }

    /* =========================================================
     |  TRANSACTIONS — All movements
     ========================================================= */

    public function transactions(Request $request): Response
    {
        $branchId  = $request->query('branch_id');
        $type      = $request->query('type');
        $variantId = $request->query('variant_id');

        $transactions = InventoryTransaction::query()
            ->with([
                'branch:id,name,code',
                'variant.product:id,name,brand_id',
                'variant.product.brand:id,name',
                'batch:id,batch_code',
                'creator:id,name',
            ])
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($type, fn ($q, $v) => $q->where('type', $v))
            ->when($variantId, fn ($q, $v) => $q->where('variant_id', $v))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('inventory/transactions', [
            'transactions' => $transactions,
            'branches'     => Branch::notTrashed()->select('id', 'name', 'code')->get(),
            'types'        => InventoryTransaction::TYPES,
            'filters'      => [
                'branch_id'  => $branchId,
                'type'       => $type,
                'variant_id' => $variantId,
            ],
        ]);
    }
}