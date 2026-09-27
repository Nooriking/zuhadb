<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductPriceHistory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    /**
     * Receive stock for an existing variant (batch + inventory + price history).
     *
     * @param  array<string, mixed>  $data  Validated receive payload
     */
    public function receiveStock(array $data, User $actor, int $branchId): Product
    {
        return DB::transaction(function () use ($data, $actor, $branchId) {
            $variant = ProductVariant::with('product')->findOrFail($data['variant_id']);
            $product = $variant->product;
            $qty = (int) $data['quantity'];
            $purchasePrice = (float) $data['purchase_price_afn'];
            $salePrice = (float) $data['sale_price_afn'];
            $exchangeRate = (float) ($data['exchange_rate'] ?? 0);
            $profitAfn = (float) ($data['profit_per_unit'] ?? 0);

            $investorId = $data['investor_id'] ?? null;
            if (empty($investorId) || $investorId === 'store' || $investorId === 'none') {
                $investorId = null;
            }

            if ($investorId && $profitAfn > 0 && $exchangeRate <= 0) {
                throw ValidationException::withMessages([
                    'exchange_rate' => 'Enter the USD to AFN rate so investor profit is saved in dollars.',
                ]);
            }

            if ($purchasePrice < 0 || $salePrice < 0 || $profitAfn < 0) {
                throw ValidationException::withMessages([
                    'sale_price_afn' => 'Prices cannot be negative.',
                ]);
            }

            if ($salePrice < $purchasePrice) {
                throw ValidationException::withMessages([
                    'sale_price_afn' => 'Sale price cannot be less than the purchase price.',
                ]);
            }

            $oldPurchase = (float) $variant->purchase_price_afn;
            $oldSale = (float) $variant->sale_price_afn;

            $variant->update([
                'purchase_price_afn' => $purchasePrice,
                'sale_price_afn' => $salePrice,
                'user_id' => $actor->id,
            ]);

            $profitUsd = $this->profitAfnToUsd($profitAfn, $exchangeRate, $investorId);

            $purchaseRow = Product::create([
                'name' => $product->name,
                'brand_id' => $product->brand_id,
                'gender' => $product->gender,
                'status' => $product->status,
                'description' => $product->description,
                'user_id' => $actor->id,
                'trash' => false,
                'variant_id' => $variant->id,
                'investor_id' => $investorId,
                'supplier_id' => $data['supplier_id'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'exchange_rate' => $exchangeRate > 0 ? $exchangeRate : null,
                'quantity' => $qty,
                'remaining_quantity' => $qty,
                'purchase_price_afn' => $purchasePrice * $qty,
                'profit_per_unit' => $profitAfn,
                'line_total' => $purchasePrice * $qty,
                'line_profit' => $profitAfn * $qty,
                'notes' => $data['notes'] ?? null,
                'payment_status' => ! empty($data['supplier_id']) ? 'unpaid' : 'paid',
            ]);

            $this->applyPaidNow($purchaseRow, (float) ($data['paid_now'] ?? 0), $actor->id);

            $this->createProductBatch(
                branchId: $branchId,
                variantId: $variant->id,
                purchaseRowId: $purchaseRow->id,
                investorId: $investorId ? (int) $investorId : null,
                qty: $qty,
                unitCostAfn: $purchasePrice,
                profitPerUnit: $profitUsd,
                receivedDate: $data['purchase_date'],
                userId: $actor->id,
            );

            $this->recordPriceChange(
                $variant,
                $oldPurchase,
                $purchasePrice,
                $oldSale,
                $salePrice,
                'Add stock'
            );
            $this->recordPriceDropLoss($variant, $oldPurchase, $purchasePrice, $branchId, $actor->id);

            Inventory::increase(
                branchId: $branchId,
                variantId: $variant->id,
                qty: $qty,
                type: 'purchase',
                notes: 'Stock received',
                unitCostAfn: $purchasePrice,
            );

            $this->refreshVariantStock($product);

            ActivityLogger::log(
                action: 'receive',
                section: 'purchases',
                description: "Stock received for variant #{$variant->id} qty {$qty}",
                subject: $purchaseRow,
                properties: [
                    'variant_id' => $variant->id,
                    'quantity' => $qty,
                    'purchase_price_afn' => $purchasePrice,
                    'sale_price_afn' => $salePrice,
                    'branch_id' => $branchId,
                ],
                userId: $actor->id,
            );

            return $purchaseRow;
        });
    }

    private function profitAfnToUsd(float $profitAfn, float $exchangeRate, mixed $investorId): float
    {
        if (empty($investorId) || $exchangeRate <= 0) {
            return 0;
        }

        return round($profitAfn / $exchangeRate, 2);
    }

    private function createProductBatch(
        int $branchId,
        int $variantId,
        int $purchaseRowId,
        ?int $investorId,
        int $qty,
        float $unitCostAfn,
        float $profitPerUnit,
        string $receivedDate,
        int $userId,
    ): ProductBatch {
        return ProductBatch::create([
            'branch_id' => $branchId,
            'variant_id' => $variantId,
            'purchase_item_id' => $purchaseRowId,
            'investor_id' => $investorId,
            'batch_code' => ProductBatch::generateBatchCode(),
            'quantity' => $qty,
            'remaining_quantity' => $qty,
            'unit_cost_afn' => $unitCostAfn,
            'total_cost_afn' => $unitCostAfn * $qty,
            'profit_per_unit_afn' => $profitPerUnit,
            'received_date' => $receivedDate,
            'status' => 'active',
            'user_id' => $userId,
            'trash' => false,
        ]);
    }

    private function recordPriceDropLoss(
        ProductVariant $variant,
        float $oldPurchase,
        float $newPurchase,
        int $branchId,
        int $userId,
    ): void {
        if ($newPurchase >= $oldPurchase || $oldPurchase <= 0) {
            return;
        }

        $qty = (int) Inventory::query()
            ->where('variant_id', $variant->id)
            ->where('trash', false)
            ->sum('quantity');

        if ($qty <= 0) {
            return;
        }

        $loss = round(($oldPurchase - $newPurchase) * $qty, 2);
        if ($loss <= 0) {
            return;
        }

        $variant->loadMissing('product:id,name');
        $name = $variant->product?->name ?? 'Product';

        Expense::create([
            'branch_id' => $branchId,
            'expense_number' => Expense::generateExpenseNumber(),
            'title' => 'Price drop loss',
            'category' => 'other',
            'amount_afn' => $loss,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'other',
            'description' => "{$name}: purchase price fell from {$oldPurchase} to {$newPurchase} AFN on {$qty} pieces still in stock.",
            'created_by' => $userId,
            'user_id' => $userId,
            'trash' => false,
        ]);
    }

    private function recordPriceChange(
        ProductVariant $variant,
        float $oldPurchase,
        float $newPurchase,
        float $oldSale,
        float $newSale,
        ?string $reason = null
    ): void {
        if ($oldPurchase == $newPurchase && $oldSale == $newSale) {
            return;
        }

        ProductPriceHistory::record(
            variantId: $variant->id,
            oldPurchaseAfn: $oldPurchase,
            newPurchaseAfn: $newPurchase,
            oldSaleAfn: $oldSale,
            newSaleAfn: $newSale,
            reason: $reason
        );
    }

    private function applyPaidNow(Product $purchase, float $paidNow, int $userId): void
    {
        if ($paidNow <= 0 || ! $purchase->supplier_id) {
            return;
        }

        $invoiceNo = mb_substr($purchase->invoice_number ?: ('P-'.$purchase->id), 0, 50);
        $invoice = \App\Models\SupplierInvoice::query()
            ->where('supplier_id', $purchase->supplier_id)
            ->where('invoice_no', $invoiceNo)
            ->first();

        $invoice?->takePayment(
            $paidNow,
            optional($purchase->purchase_date)->toDateString() ?: now()->toDateString(),
            (float) ($purchase->exchange_rate ?? 0),
            'Paid on purchase',
            $userId,
        );
    }

    private function refreshVariantStock(Product $product): void
    {
        $product->variants()->get()->each(function (ProductVariant $variant) {
            $totalStock = Product::where('variant_id', $variant->id)
                ->where('trash', false)
                ->sum('remaining_quantity');

            $variant->update([
                'stock_quantity' => (int) $totalStock,
            ]);
        });
    }
}
