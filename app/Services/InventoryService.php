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
     * ✅ When a new purchase is created → increase inventory
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
        $oldCost = (string) $inventory->average_cost_afn;
        $newQty    = $oldQty + $quantity;

        // Reporting average cost in AFN (ledger). Sales costing remains FIFO via batches.
        $newAvgCost = $newQty > 0
            ? \App\Support\Money::round(
                \App\Support\Money::div(
                    \App\Support\Money::add(
                        \App\Support\Money::mul((string) $oldQty, $oldCost, 8),
                        \App\Support\Money::mul((string) $quantity, (string) $unitCostAfn, 8),
                        8
                    ),
                    (string) $newQty,
                    12
                ),
                2
            )
            : \App\Support\Money::round((string) $unitCostAfn, 2);

        $inventory->update([
            'quantity'            => $newQty,
            'available_quantity'  => $newQty - $inventory->reserved_quantity,
            'average_cost_afn'    => $newAvgCost,
            'last_movement_at'    => now(),
            'user_id'             => Auth::id(),
        ]);
    }

    /**
     * ✅ When a sale is completed → decrease inventory
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
     * ✅ Recalculate inventory from purchase rows
     * for one variant in one branch
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
     * ✅ Recalculate for all variants of a product
     */
    public function recalculateForProduct(Product $product, int $branchId): void
    {
        $product->variants()->get()->each(function (ProductVariant $variant) use ($branchId) {
            $this->recalculate($variant->id, $branchId);
        });
    }
}