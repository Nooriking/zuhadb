<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Investment;
use App\Models\InvestmentProductAllocation;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductPriceHistory;
use App\Models\ProductVariant;
use App\Models\Currency;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\FxBoard;
use App\Support\Money;
use App\Support\PurchaseValuation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(private readonly PurchaseValuation $valuation) {}

    /**
     * Receive stock for an existing variant (batch + inventory + price history).
     *
     * @param  array<string, mixed>  $data  Validated receive payload
     */
    public function receiveStock(array $data, User $actor, int $branchId): Product
    {
        return DB::transaction(function () use ($data, $actor, $branchId) {
            $variant = ProductVariant::with(['product', 'purchaseCurrency'])->findOrFail($data['variant_id']);
            $product = $variant->product;
            $qty = (int) $data['quantity'];
            $salePrice = (float) $data['sale_price_afn'];
            $profitOriginal = (float) ($data['profit_per_unit'] ?? 0);

            $ledger = Currency::ledger();
            $purchaseCurrency = ! empty($data['purchase_currency_id'])
                ? Currency::query()->notTrashed()->findOrFail((int) $data['purchase_currency_id'])
                : ($variant->purchaseCurrency
                    ?? ($variant->purchase_currency_id ? Currency::query()->find($variant->purchase_currency_id) : $ledger));
            $profitCurrency = ! empty($data['profit_currency_id'])
                ? Currency::query()->notTrashed()->find((int) $data['profit_currency_id'])
                : $ledger;

            $exchangeRate = (float) ($data['exchange_rate'] ?? 0);
            if ($exchangeRate < 0) {
                throw ValidationException::withMessages([
                    'exchange_rate' => 'Exchange rate cannot be negative.',
                ]);
            }

            // Prefer original unit amount in the selected purchase currency.
            if (isset($data['purchase_unit_amount']) && $data['purchase_unit_amount'] !== '' && $data['purchase_unit_amount'] !== null) {
                $originalUnit = (string) $data['purchase_unit_amount'];
            } elseif (
                isset($data['purchase_price_afn'])
                && $data['purchase_price_afn'] !== ''
                && $data['purchase_price_afn'] !== null
                && $purchaseCurrency
                && $ledger
                && $purchaseCurrency->id !== $ledger->id
                && $exchangeRate > 0
            ) {
                // Legacy clients send AFN unit + exchange_rate for a non-AFN purchase currency.
                $originalUnit = number_format(((float) $data['purchase_price_afn']) / $exchangeRate, 2, '.', '');
            } elseif (isset($data['purchase_price_afn']) && $data['purchase_price_afn'] !== '' && $data['purchase_price_afn'] !== null) {
                $originalUnit = (string) $data['purchase_price_afn'];
                $purchaseCurrency = $ledger ?? $purchaseCurrency;
            } elseif ($variant->purchase_amount !== null && $variant->purchase_currency_id) {
                $originalUnit = (string) $variant->purchase_amount;
                $purchaseCurrency = $variant->purchaseCurrency ?? $purchaseCurrency;
            } else {
                $originalUnit = (string) ($variant->purchase_price_afn ?? '0');
                $purchaseCurrency = $ledger ?? $purchaseCurrency;
            }

            $rateOverrides = [];
            if (! empty($data['usd_to_afn_rate']) && (float) $data['usd_to_afn_rate'] > 0) {
                $rateOverrides['usd_to_afn'] = (string) $data['usd_to_afn_rate'];
            }
            if (! empty($data['aed_to_afn_rate']) && (float) $data['aed_to_afn_rate'] > 0) {
                $rateOverrides['aed_to_afn'] = (string) $data['aed_to_afn_rate'];
            }
            if ($rateOverrides !== []) {
                $rateOverrides['source'] = (string) ($data['rate_source'] ?? 'transaction');
            }

            $effectiveAt = $data['purchase_date'] ?? now();
            $money = $this->valuation->snapshot(
                $originalUnit,
                $qty,
                $purchaseCurrency,
                $exchangeRate > 0 ? (string) $exchangeRate : null,
                $effectiveAt,
                $rateOverrides,
            );
            $purchasePrice = (float) $money['unit_cost_afn'];

            $fundingSource = $data['funding_source'] ?? null;
            $investorId = $data['investor_id'] ?? null;
            $investmentId = ! empty($data['investment_id']) ? (int) $data['investment_id'] : null;

            if ($fundingSource === 'office' || empty($investorId) || $investorId === 'store' || $investorId === 'none') {
                $investorId = null;
                $investmentId = null;
                $profitOriginal = 0;
            }

            if ($investorId && $profitOriginal < 0) {
                throw ValidationException::withMessages([
                    'profit_per_unit' => 'Investor profit cannot be negative.',
                ]);
            }

            if ($purchasePrice < 0 || $salePrice < 0) {
                throw ValidationException::withMessages([
                    'sale_price_afn' => 'Prices cannot be negative.',
                ]);
            }

            // Compare receive-time sale AFN (request) vs this receive's PurchaseValuation AFN —
            // never revalue the purchase with a later catalog/product-reference rate.
            // Catalog variant.sale_price_afn must not silently substitute for either side.
            if ($salePrice < $purchasePrice) {
                throw ValidationException::withMessages([
                    'sale_price_afn' => 'Sale price (AFN at receive) cannot be less than this purchase\'s historical AFN cost. '
                        .'Catalog estimate rates are not used for this check.',
                ]);
            }

            $board = FxBoard::fromArray([
                'usd_to_afn' => (string) ($money['usd_to_afn_rate'] ?? 0),
                'aed_to_afn' => (string) ($money['aed_to_afn_rate'] ?? 0),
                'usd_to_aed' => $money['usd_to_aed_rate'] ?? null,
                'effective_at' => isset($money['rate_effective_at']) ? (string) $money['rate_effective_at'] : null,
                'source' => (string) ($money['rate_source'] ?? 'operational'),
                'kind' => (string) ($money['rate_kind'] ?? 'operational'),
            ]);

            $profitCode = strtoupper((string) ($profitCurrency?->code ?? $ledger?->code ?? 'AFN'));
            if ($investorId && $profitOriginal > 0) {
                $profitEq = $board->equivalents(Money::round((string) $profitOriginal, 4), $profitCode, 4);
                $profitAfn = (float) Money::round($profitEq['afn'], 2);
                $profitUsd = (float) $profitEq['usd'];
                $profitAed = (float) $profitEq['aed'];
            } else {
                $profitOriginal = 0;
                $profitAfn = 0.0;
                $profitUsd = 0.0;
                $profitAed = 0.0;
                $profitCurrency = $ledger;
                $profitCode = strtoupper((string) ($ledger?->code ?? 'AFN'));
            }

            // Preview-only company PPU at receive time (sale-time residual is authoritative).
            // Do not silently clamp — insufficient coverage is enforced at sale time.
            $storeProfitPerUnit = round($salePrice - $purchasePrice - $profitAfn, 2);

            $totalCostAfn = (float) $money['line_cost_afn'];

            $investment = null;
            if ($investorId && ! $investmentId) {
                // Receiving investor goods is itself the contribution event.
                // Do NOT increase the investor cash wallet — goods are in-kind capital.
                $investment = Investment::create([
                    'investor_id' => (int) $investorId,
                    'source_type' => 'goods',
                    'investment_number' => Investment::generateInvestmentNumber(),
                    'currency_id' => $money['purchase_currency_id'],
                    'currency_code' => $money['purchase_currency_code'],
                    'currency_symbol' => $money['purchase_currency_symbol'],
                    'exchange_rate' => $money['rate_to_ledger'],
                    'usd_to_afn_rate' => $money['usd_to_afn_rate'] ?? null,
                    'aed_to_afn_rate' => $money['aed_to_afn_rate'] ?? null,
                    'usd_to_aed_rate' => $money['usd_to_aed_rate'] ?? null,
                    'rate_effective_at' => $money['rate_effective_at'] ?? null,
                    'rate_source' => $money['rate_source'] ?? null,
                    'capital_amount_currency' => $money['purchase_line_amount'],
                    'capital_amount_afn' => $totalCostAfn,
                    'capital_amount_usd' => $money['line_amount_usd'] ?? null,
                    'capital_amount_aed' => $money['line_amount_aed'] ?? null,
                    'available_amount_currency' => $money['purchase_line_amount'],
                    'available_amount_afn' => $totalCostAfn,
                    'allocated_amount_afn' => 0,
                    'profit_amount_afn' => 0,
                    'withdrawn_amount_afn' => 0,
                    'investment_date' => $data['purchase_date'],
                    'status' => 'active',
                    'notes' => 'Goods contribution created by product receiving.',
                    'created_by' => $actor->id,
                ]);
                $investmentId = $investment->id;
            }

            if ($investmentId) {
                $investment = Investment::query()->lockForUpdate()->findOrFail($investmentId);

                if ((int) $investment->investor_id !== (int) $investorId) {
                    throw ValidationException::withMessages([
                        'investment_id' => 'That investment does not belong to the selected investor.',
                    ]);
                }

                if (! $investment->hasAvailableCapital()) {
                    throw ValidationException::withMessages([
                        'investment_id' => 'This investment has no available capital left.',
                    ]);
                }

                $available = $investment->isGoodsInvestment()
                    ? $investment->remainingGoodsCapitalAfn()
                    : (float) $investment->available_amount_afn;

                if ($totalCostAfn > $available + 0.01) {
                    throw ValidationException::withMessages([
                        'investment_id' => "Available capital is {$available} AFN, but this stock costs {$totalCostAfn} AFN.",
                    ]);
                }
            }

            $oldPurchase = (float) $variant->purchase_price_afn;
            $oldSale = (float) $variant->sale_price_afn;

            $variant->update([
                'purchase_price_afn' => $purchasePrice,
                'sale_price_afn' => $salePrice,
                'user_id' => $actor->id,
            ]);

            $purchaseRowPayload = [
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
                'quantity' => $qty,
                'remaining_quantity' => $qty,
                'purchase_price_afn' => $totalCostAfn,
                'profit_per_unit' => $profitAfn,
                'line_total' => $totalCostAfn,
                'line_profit' => $profitAfn * $qty,
                'store_profit_per_unit' => $storeProfitPerUnit,
                'line_store_profit' => round($storeProfitPerUnit * $qty, 2),
                'notes' => $data['notes'] ?? null,
                'payment_status' => ! empty($data['supplier_id']) ? 'unpaid' : 'paid',
            ];

            if (Schema::hasColumn('products', 'profit_currency_id')) {
                $purchaseRowPayload['profit_currency_id'] = $profitCurrency?->id ?? $ledger?->id;
            }
            if (Schema::hasColumn('products', 'profit_currency_code')) {
                $purchaseRowPayload['profit_currency_code'] = $profitCode;
            }

            $purchaseRow = Product::create(array_merge($purchaseRowPayload, [
                'exchange_rate' => $money['exchange_rate'],
                'purchase_currency_id' => $money['purchase_currency_id'],
                'purchase_currency_code' => $money['purchase_currency_code'],
                'purchase_currency_symbol' => $money['purchase_currency_symbol'],
                'purchase_unit_amount' => $money['purchase_unit_amount'],
                'purchase_line_amount' => $money['purchase_line_amount'],
                'rate_to_ledger' => $money['rate_to_ledger'],
                'rate_kind' => $money['rate_kind'],
                'usd_to_afn_rate' => $money['usd_to_afn_rate'] ?? null,
                'aed_to_afn_rate' => $money['aed_to_afn_rate'] ?? null,
                'usd_to_aed_rate' => $money['usd_to_aed_rate'] ?? null,
                'rate_effective_at' => $money['rate_effective_at'] ?? null,
                'rate_source' => $money['rate_source'] ?? null,
                'unit_amount_usd' => $money['unit_amount_usd'] ?? null,
                'unit_amount_aed' => $money['unit_amount_aed'] ?? null,
                'line_amount_usd' => $money['line_amount_usd'] ?? null,
                'line_amount_aed' => $money['line_amount_aed'] ?? null,
            ]));

            // Goods contributions are in-kind — never invent a supplier cash payment.
            if (! ($investment && $investment->isGoodsInvestment())) {
                $this->applyPaidNow($purchaseRow, (float) ($data['paid_now'] ?? 0), $actor->id);
            }

            $batch = $this->createProductBatch(
                branchId: $branchId,
                variantId: $variant->id,
                purchaseRowId: $purchaseRow->id,
                investorId: $investorId ? (int) $investorId : null,
                qty: $qty,
                unitCostAfn: $purchasePrice,
                unitCostCurrency: (float) $money['purchase_unit_amount'],
                exchangeRate: (float) $money['rate_to_ledger'],
                profitPerUnitAfn: $profitAfn,
                profitPerUnitOriginal: $profitOriginal,
                profitCurrencyId: $profitCurrency?->id,
                profitCurrencyCode: $profitCode,
                profitPerUnitUsd: $profitUsd,
                profitPerUnitAed: $profitAed,
                storeProfitPerUnit: $storeProfitPerUnit,
                salePriceAfn: $salePrice,
                receivedDate: $data['purchase_date'],
                userId: $actor->id,
            );

            if ($investment) {
                $purchaseAllocation = $investment->allocateToPurchase(
                    $purchaseRow->id,
                    $totalCostAfn,
                    "Add stock · batch {$batch->batch_code}"
                );

                InvestmentProductAllocation::create([
                    'investment_id' => $investment->id,
                    'investment_purchase_allocation_id' => $purchaseAllocation->id,
                    'batch_id' => $batch->id,
                    'variant_id' => $variant->id,
                    'funded_quantity' => $qty,
                    'sold_quantity' => 0,
                    'remaining_quantity' => $qty,
                    'cost_per_unit_afn' => $purchasePrice,
                    'profit_per_unit_afn' => $profitAfn,
                    'capital_recovered_afn' => 0,
                    'profit_earned_afn' => 0,
                    'status' => 'active',
                ]);
            }

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
                    'funding_source' => $investorId ? 'investor' : 'office',
                    'investment_id' => $investment?->id,
                    'total_cost_afn' => $totalCostAfn,
                    'profit_per_unit_afn' => $profitAfn,
                ],
                userId: $actor->id,
            );

            return $purchaseRow;
        });
    }

    private function createProductBatch(
        int $branchId,
        int $variantId,
        int $purchaseRowId,
        ?int $investorId,
        int $qty,
        float $unitCostAfn,
        float $unitCostCurrency,
        float $exchangeRate,
        float $profitPerUnitAfn,
        float $profitPerUnitOriginal,
        ?int $profitCurrencyId,
        string $profitCurrencyCode,
        float $profitPerUnitUsd,
        float $profitPerUnitAed,
        float $storeProfitPerUnit,
        float $salePriceAfn,
        string $receivedDate,
        int $userId,
    ): ProductBatch {
        $payload = [
            'branch_id' => $branchId,
            'variant_id' => $variantId,
            'purchase_item_id' => $purchaseRowId,
            'investor_id' => $investorId,
            'batch_code' => ProductBatch::generateBatchCode(),
            'quantity' => $qty,
            'remaining_quantity' => $qty,
            'unit_cost_afn' => $unitCostAfn,
            'total_cost_afn' => $unitCostAfn * $qty,
            'profit_per_unit_afn' => $profitPerUnitAfn,
            'received_date' => $receivedDate,
            'status' => 'active',
            'user_id' => $userId,
            'trash' => false,
        ];

        if (Schema::hasColumn('product_batches', 'unit_cost_currency')) {
            $payload['unit_cost_currency'] = $unitCostCurrency;
        }
        if (Schema::hasColumn('product_batches', 'exchange_rate')) {
            $payload['exchange_rate'] = $exchangeRate;
        }
        if (Schema::hasColumn('product_batches', 'profit_per_unit_currency')) {
            $payload['profit_per_unit_currency'] = $profitPerUnitOriginal;
        }
        if (Schema::hasColumn('product_batches', 'store_profit_per_unit_afn')) {
            $payload['store_profit_per_unit_afn'] = $storeProfitPerUnit;
        }
        if (Schema::hasColumn('product_batches', 'sale_price_afn')) {
            $payload['sale_price_afn'] = $salePriceAfn;
        }
        if (Schema::hasColumn('product_batches', 'profit_currency_id')) {
            $payload['profit_currency_id'] = $profitCurrencyId;
        }
        if (Schema::hasColumn('product_batches', 'profit_currency_code')) {
            $payload['profit_currency_code'] = $profitCurrencyCode;
        }
        if (Schema::hasColumn('product_batches', 'profit_per_unit_usd')) {
            $payload['profit_per_unit_usd'] = $profitPerUnitUsd;
        }
        if (Schema::hasColumn('product_batches', 'profit_per_unit_aed')) {
            $payload['profit_per_unit_aed'] = $profitPerUnitAed;
        }

        return ProductBatch::create($payload);
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
            $totalStock = ProductBatch::query()
                ->where('variant_id', $variant->id)
                ->where('trash', false)
                ->sum('remaining_quantity');

            $variant->update([
                'stock_quantity' => (int) $totalStock,
            ]);
        });
    }
}
