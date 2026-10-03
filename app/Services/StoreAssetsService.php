<?php

namespace App\Services;

use App\Models\ProductBatch;
use Illuminate\Support\Facades\DB;

class StoreAssetsService
{
    /**
     * ✅ محاسبه دارایی‌های دوکان (بدون investor)
     *
     * منطق:
     *  - جنس‌هایی که investor_id = NULL دارند → مال دوکان هستند
     *  - سود این جنس‌ها کامل به دوکان می‌رسد (نه به هیچ سرمایه‌گذار)
     */
    public static function summarize(?int $branchId = null): array
    {
        /* ═══════════════════════════════════════════════════
         |  1. موجودی فعلی دوکان در انبار
         ═══════════════════════════════════════════════════ */

        $batches = ProductBatch::query()
            ->where('trash', false)
            ->whereNull('investor_id')
            ->where('remaining_quantity', '>', 0)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with([
                'variant:id,product_id,volume_ml,concentration,sale_price_afn',
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

            // ارزش باقی‌مانده بر اساس قیمت خرید
            $remainingCostAfn = $remaining * $unitCostAfn;

            // قیمت فروش برای محاسبه سود مورد انتظار
            $salePriceAfn = (float) ($b->variant?->sale_price_afn ?? 0);

            // سود مورد انتظار (اگر همه بفروشند)
            $expectedProfitAfn = max(0, ($salePriceAfn - $unitCostAfn) * $remaining);

            // نام کامل
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
         |  2. فروش‌های تحقق‌یافته از جنس‌های دوکان
         ═══════════════════════════════════════════════════ */

        // از product_batches که investor_id = NULL، sale_items را بگیر
        $realizedRow = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_batches', 'sale_items.batch_id', '=', 'product_batches.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->where('product_batches.trash', false)
            ->whereNull('product_batches.investor_id')
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
         |  3. جمع کل
         ═══════════════════════════════════════════════════ */

        return [
            'summary' => [
                // موجودی فعلی
                'total_items'         => count($items),
                'total_qty'           => $totalQty,
                'total_remaining_qty' => $totalRemainingQty,

                // ارزش‌ها (AFN)
                'total_cost_afn'      => $totalCostAfn,
                'remaining_cost_afn'  => $totalRemainingCostAfn,
                'expected_profit_afn' => $totalExpectedProfitAfn,

                // فروش‌های تحقق‌یافته
                'realized_sales_afn'  => $realized['total_sales_afn'],
                'realized_qty'        => $realized['total_qty'],
                'realized_profit_afn' => $realized['total_profit_afn'],

                // سود کل
                'total_profit_afn'    => $realized['total_profit_afn'] + $totalExpectedProfitAfn,
            ],
            'items' => $items,
        ];
    }
}