<?php

namespace App\Services;

use App\Models\ProductBatch;
use Illuminate\Support\Facades\DB;

class StoreAssetsService
{
    /**
     * ✅ Calculate store assets (without investor)
     *
     * Logic:
     *  - Items with investment_id = NULL → belong to the store
     *    (investor_id column was removed from product_batches)
     *  - Profit from these items goes entirely to the store (not to any investor)
     */
    public static function summarize(?int $branchId = null): array
    {
        /* ═══════════════════════════════════════════════════
         |  1. Current store stock in inventory
         ═══════════════════════════════════════════════════ */

        $batches = ProductBatch::query()
            ->notTrashed()
            ->storeOwned()
            ->where('remaining_quantity', '>', 0)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with([
                'variant:id,product_id,volume_ml,concentration',
                'variant.prices' => fn ($q) => $q->where('is_active', true),
                'variant.product:id,name,brand_id',
                'variant.product.brand:id,name',
            ])
            ->orderByDesc('received_date')
            ->get();

        $items = $batches->map(function (ProductBatch $b) {
            $remaining    = (int) $b->remaining_quantity;
            $qty          = (int) $b->quantity;
            $unitCostAfn  = (float) $b->unit_cost_afn;
            $totalCostAfn = (float) $b->total_cost_afn;

            // Remaining value based on purchase cost
            $remainingCostAfn = $remaining * $unitCostAfn;

            // Sale price: prefer batch, otherwise variant
            $salePriceAfn = (float) ($b->sale_price_afn ?: ($b->variant?->sale_price_afn ?? 0));

            // Expected profit (if all remaining units sell)
            $expectedProfitAfn = max(0, ($salePriceAfn - $unitCostAfn) * $remaining);

            // Full display name
            $displayName = trim(implode(' · ', array_filter([
                $b->variant?->product?->brand?->name,
                $b->variant?->product?->name,
                $b->variant?->volume_ml ? "{$b->variant->volume_ml}ml" : null,
                $b->variant?->concentration ? strtoupper($b->variant->concentration) : null,
            ])));

            return [
                'batch_id'            => $b->id,
                'batch_code'          => $b->batch_code,
                'variant_id'          => $b->variant_id,
                'name'                => $displayName ?: 'Product',
                'brand'               => $b->variant?->product?->brand?->name,
                'quantity'            => $qty,
                'remaining'           => $remaining,
                'unit_cost_afn'       => $unitCostAfn,
                'sale_price_afn'      => $salePriceAfn,
                'total_cost_afn'      => $totalCostAfn,
                'remaining_cost_afn'  => $remainingCostAfn,
                'expected_profit_afn' => $expectedProfitAfn,
                'received_date'       => optional($b->received_date)->toDateString(),
                'branch_id'           => $b->branch_id,
            ];
        })->values()->all();

        $totalCostAfn           = (float) collect($items)->sum('total_cost_afn');
        $totalRemainingCostAfn  = (float) collect($items)->sum('remaining_cost_afn');
        $totalExpectedProfitAfn = (float) collect($items)->sum('expected_profit_afn');
        $totalQty               = (int) collect($items)->sum('quantity');
        $totalRemainingQty      = (int) collect($items)->sum('remaining');

        /* ═══════════════════════════════════════════════════
         |  2. Realized sales from store-owned items
         ═══════════════════════════════════════════════════ */

        // From product_batches with investment_id = NULL (store-owned), join sale_items
        $realizedRow = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_batches', 'sale_items.batch_id', '=', 'product_batches.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->where('product_batches.trash', false)
            ->whereNull('product_batches.investment_id')
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->selectRaw('
                COALESCE(SUM(sale_items.line_total_afn), 0) as total_sales_afn,
                COALESCE(SUM(sale_items.quantity), 0) as total_qty,
                COALESCE(SUM(sale_items.store_profit_afn), 0) as total_profit_afn
            ')
            ->first();

        $realized = [
            'total_sales_afn'  => (float) ($realizedRow->total_sales_afn ?? 0),
            'total_qty'        => (int) ($realizedRow->total_qty ?? 0),
            'total_profit_afn' => (float) ($realizedRow->total_profit_afn ?? 0),
        ];

        /* ═══════════════════════════════════════════════════
         |  3. Totals
         ═══════════════════════════════════════════════════ */

        return [
            'summary' => [
                // Current stock
                'total_items'         => count($items),
                'total_qty'           => $totalQty,
                'total_remaining_qty' => $totalRemainingQty,

                // Values (AFN)
                'total_cost_afn'      => $totalCostAfn,
                'remaining_cost_afn'  => $totalRemainingCostAfn,
                'expected_profit_afn' => $totalExpectedProfitAfn,

                // Realized sales
                'realized_sales_afn'  => $realized['total_sales_afn'],
                'realized_qty'        => $realized['total_qty'],
                'realized_profit_afn' => $realized['total_profit_afn'],

                // Total profit
                'total_profit_afn'    => $realized['total_profit_afn'] + $totalExpectedProfitAfn,
            ],
            'items' => $items,
        ];
    }
}
