<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * ✅ وقتی purchase جدید ساخته شد → inventory را افزایش بده
     */
    public function increaseFromPurchase(
        int $variantId,
        int $branchId,
        int $quantity,
        float $unitCostAfn
    ): void {
        $inventory = Inventory::firstOrCreate(
            [
                'variant_id' => $variantId,
                'branch_id'  => $branchId,
            ],
            [
                'quantity'            => 0,
                'reserved_quantity'   => 0,
                'available_quantity'  => 0,
                'average_cost_afn'    => 0,
                'user_id'             => Auth::id(),
                'trash'               => false,
            ]
        );

        $oldQty  = $inventory->quantity;
        $oldCost = (float) $inventory->average_cost_afn;

        // ✅ محاسبه weighted average cost
        $newQty    = $oldQty + $quantity;
        $newAvgCost = $newQty > 0
            ? (($oldQty * $oldCost) + ($quantity * $unitCostAfn)) / $newQty
            : $unitCostAfn;

        $inventory->update([
            'quantity'            => $newQty,
            'available_quantity'  => $newQty - $inventory->reserved_quantity,
            'average_cost_afn'    => round($newAvgCost, 2),
            'last_movement_at'    => now(),
            'user_id'             => Auth::id(),
        ]);
    }

    /**
     * ✅ وقتی فروش انجام شد → inventory را کاهش بده
     */
    public function decreaseFromSale(
        int $variantId,
        int $branchId,
        int $quantity
    ): void {
        $inventory = Inventory::where('variant_id', $variantId)
            ->where('branch_id', $branchId)
            ->first();

        if (! $inventory) {
            return;
        }

        $newQty = max(0, $inventory->quantity - $quantity);

        $inventory->update([
            'quantity'            => $newQty,
            'available_quantity'  => $newQty - $inventory->reserved_quantity,
            'last_movement_at'    => now(),
            'user_id'             => Auth::id(),
        ]);
    }

    /**
     * ✅ Recalculate inventory از purchase rows
     * برای یک variant در یک branch
     */
    public function recalculate(
        int $variantId,
        int $branchId,
        int $defaultBranchId = null
    ): void {
        $purchaseRows = Product::where('variant_id', $variantId)
            ->where('trash', false)
            ->get(['remaining_quantity', 'purchase_price_afn', 'quantity']);

        $totalQty  = 0;
        $totalCost = 0;

        foreach ($purchaseRows as $row) {
            $qty  = (int) $row->remaining_quantity;
            $cost = (float) $row->purchase_price_afn / max(1, (int) $row->quantity);

            $totalQty  += $qty;
            $totalCost += $qty * $cost;
        }

        $avgCost = $totalQty > 0 ? $totalCost / $totalQty : 0;

        $inventory = Inventory::firstOrCreate(
            [
                'variant_id' => $variantId,
                'branch_id'  => $branchId ?? $defaultBranchId,
            ],
            [
                'quantity'            => 0,
                'reserved_quantity'   => 0,
                'available_quantity'  => 0,
                'average_cost_afn'    => 0,
                'user_id'             => Auth::id(),
                'trash'               => false,
            ]
        );

        $inventory->update([
            'quantity'            => $totalQty,
            'available_quantity'  => $totalQty - $inventory->reserved_quantity,
            'average_cost_afn'    => round($avgCost, 2),
            'last_movement_at'    => now(),
            'user_id'             => Auth::id(),
        ]);
    }

    /**
     * ✅ Recalculate برای همه variants یک product
     */
    public function recalculateForProduct(Product $product, int $branchId): void
    {
        $product->variants()->get()->each(function (ProductVariant $variant) use ($branchId) {
            $this->recalculate($variant->id, $branchId);
        });
    }
}