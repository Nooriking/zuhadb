<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Investment;
use App\Models\InvestorTransaction;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductPrice;
use App\Models\ProductPriceHistory;
use App\Models\ProductVariant;
use App\Models\Currency;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Support\FxBoard;
use App\Support\Money;
use App\Support\PurchaseValuation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        private readonly PurchaseValuation $valuation,
        private readonly ExchangeService $exchange,
    ) {}

    /**
     * Receive stock for an existing variant (batch + inventory + optional auto-investment).
     *
     * @param  array<string, mixed>  $data  Validated receive payload
     */
    public function receiveStock(array $data, User $actor, int $branchId): ProductBatch
    {
        return DB::transaction(function () use ($data, $actor, $branchId) {
            $variant = ProductVariant::with(['product', 'prices'])->findOrFail($data['variant_id']);
            $product = $variant->product;
            $qty = (int) $data['quantity'];
            $salePrice = (float) $data['sale_price_afn'];
            $profitOriginal = (float) ($data['profit_per_unit'] ?? 0);
            $supplierId = ! empty($data['supplier_id']) ? (int) $data['supplier_id'] : null;

            $ledger = Currency::ledger();
            $purchaseCurrency = ! empty($data['purchase_currency_id'])
                ? Currency::query()->notTrashed()->findOrFail((int) $data['purchase_currency_id'])
                : $this->resolveCatalogPurchaseCurrency($variant, $ledger);

            $exchangeRate = (float) ($data['exchange_rate'] ?? 0);
            if ($exchangeRate < 0) {
                throw ValidationException::withMessages([
                    'exchange_rate' => 'Exchange rate cannot be negative.',
                ]);
            }

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
                $originalUnit = number_format(((float) $data['purchase_price_afn']) / $exchangeRate, 2, '.', '');
            } elseif (isset($data['purchase_price_afn']) && $data['purchase_price_afn'] !== '' && $data['purchase_price_afn'] !== null) {
                $originalUnit = (string) $data['purchase_price_afn'];
                $purchaseCurrency = $ledger ?? $purchaseCurrency;
            } else {
                $catalogPurchase = $this->catalogPriceRow($variant, 'purchase');
                $originalUnit = (string) ($catalogPurchase?->amount ?? '0');
                if ($catalogPurchase?->currency_id) {
                    $purchaseCurrency = Currency::query()->find($catalogPurchase->currency_id) ?? $purchaseCurrency;
                } else {
                    $purchaseCurrency = $ledger ?? $purchaseCurrency;
                }
            }

            // Single source: central exchange_rates via ExchangeService.
            $usdSnap = $this->exchange->snapshot('USD', 'AFN');
            $aedSnap = $this->exchange->snapshot('AED', 'AFN');
            $current = $this->exchange->getCurrentRates();
            $rateOverrides = [
                'usd_to_afn' => (string) $usdSnap['rate'],
                'aed_to_afn' => (string) $aedSnap['rate'],
                'usd_to_aed' => (string) $current['usd_to_aed'],
                'source' => (string) ($usdSnap['source'] ?? 'exchange_rates'),
            ];

            if (! empty($data['usd_to_afn_rate']) && (float) $data['usd_to_afn_rate'] > 0) {
                $rateOverrides['usd_to_afn'] = (string) $data['usd_to_afn_rate'];
            }
            if (! empty($data['aed_to_afn_rate']) && (float) $data['aed_to_afn_rate'] > 0) {
                $rateOverrides['aed_to_afn'] = (string) $data['aed_to_afn_rate'];
            }
            if (! empty($data['usd_to_aed_rate']) && (float) $data['usd_to_aed_rate'] > 0) {
                $rateOverrides['usd_to_aed'] = (string) $data['usd_to_aed_rate'];
            }

            if ($exchangeRate <= 0 && $purchaseCurrency) {
                $purchaseCode = strtoupper((string) $purchaseCurrency->code);
                if ($purchaseCode === 'AFN') {
                    $exchangeRate = 1.0;
                } elseif ($purchaseCode === 'USD') {
                    $exchangeRate = (float) $rateOverrides['usd_to_afn'];
                } elseif ($purchaseCode === 'AED') {
                    $exchangeRate = (float) $rateOverrides['aed_to_afn'];
                } else {
                    $exchangeRate = (float) $this->exchange->snapshot($purchaseCode, 'AFN')['rate'];
                }
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
            $totalCostAfn = (float) $money['line_cost_afn'];
            $purchaseLineAmount = (float) $money['purchase_line_amount'];

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

            if ($salePrice < $purchasePrice) {
                throw ValidationException::withMessages([
                    'sale_price_afn' => 'Sale price (AFN at receive) cannot be less than this purchase\'s historical AFN cost.',
                ]);
            }

            $board = FxBoard::fromArray([
                'usd_to_afn' => (string) ($money['usd_to_afn_rate'] ?? 0),
                'aed_to_afn' => (string) ($money['aed_to_afn_rate'] ?? 0),
                'usd_to_aed' => $money['usd_to_aed_rate'] ?? null,
                'effective_at' => isset($money['rate_effective_at']) ? (string) $money['rate_effective_at'] : null,
                'source' => (string) ($money['rate_source'] ?? 'exchange_rates'),
                'kind' => (string) ($money['rate_kind'] ?? 'operational'),
            ]);

            if ($investorId && $profitOriginal > 0) {
                $profitCode = strtoupper((string) ($purchaseCurrency?->code ?? $ledger?->code ?? 'AFN'));
                $profitEq = $board->equivalents(Money::round((string) $profitOriginal, 4), $profitCode, 4);
                $profitAfn = (float) Money::round($profitEq['afn'], 2);
            } else {
                $profitOriginal = 0;
                $profitAfn = 0.0;
            }

            $storeProfitPerUnit = round($salePrice - $purchasePrice - $profitAfn, 2);

            $investment = null;
            if ($investorId && ! $investmentId) {
                $investment = Investment::createFromPurchase([
                    'investor_id' => (int) $investorId,
                    'currency_id' => $money['purchase_currency_id'] ?? $purchaseCurrency?->id,
                    'amount_currency' => $purchaseLineAmount,
                    'amount_afn' => $totalCostAfn,
                    'exchange_rate' => $money['rate_to_ledger'],
                    'investment_date' => $data['purchase_date'] ?? now()->toDateString(),
                    'notes' => 'Auto-created on stock receive',
                    'created_by' => $actor->id,
                ]);
                $investmentId = $investment->id;

                InvestorTransaction::record(
                    investorId: (int) $investorId,
                    type: 'goods_deposit',
                    amountAfn: $totalCostAfn,
                    balanceType: 'capital',
                    options: [
                        'investment_id' => $investmentId,
                        'currency_id' => $money['purchase_currency_id'] ?? $purchaseCurrency?->id,
                        'currency_code' => $money['purchase_currency_code'] ?? $purchaseCurrency?->code,
                        'amount_currency' => $purchaseLineAmount,
                        'exchange_rate' => $money['rate_to_ledger'],
                        'reference_type' => ProductBatch::class,
                        'description' => 'Goods contribution on stock receive',
                        'transaction_date' => $data['purchase_date'] ?? now()->toDateString(),
                    ]
                );

                // In-kind goods: capital is tied up in inventory, not cash wallet.
            } elseif ($investmentId) {
                $investment = Investment::query()->lockForUpdate()->findOrFail($investmentId);

                if ((int) $investment->investor_id !== (int) $investorId) {
                    throw ValidationException::withMessages([
                        'investment_id' => 'That investment does not belong to the selected investor.',
                    ]);
                }
            }

            $oldPurchase = $variant->activePriceAmount('purchase', 'AFN');
            $oldSale = $variant->activePriceAmount('sale', 'AFN');

            $this->syncReceiveCatalogPrices($product, $variant, $purchasePrice, $salePrice);

            $paymentStatus = $supplierId ? 'unpaid' : 'paid';
            $purchaseDate = $data['purchase_date'] ?? now()->toDateString();

            $batch = ProductBatch::create([
                'branch_id' => $branchId,
                'variant_id' => $variant->id,
                'investment_id' => $investmentId,
                'batch_code' => ProductBatch::generateBatchCode(),
                'invoice_number' => $data['invoice_number'] ?? null,
                'purchase_date' => $purchaseDate,
                'payment_status' => $paymentStatus,
                'bill_image' => $data['bill_image'] ?? null,
                'notes' => $data['notes'] ?? null,
                'purchase_currency_id' => $money['purchase_currency_id'] ?? $purchaseCurrency?->id,
                'purchase_amount_currency' => $purchaseLineAmount,
                'quantity' => $qty,
                'remaining_quantity' => $qty,
                'unit_cost_currency' => (float) $money['purchase_unit_amount'],
                'exchange_rate' => (float) $money['rate_to_ledger'],
                'unit_cost_afn' => $purchasePrice,
                'profit_per_unit_afn' => $profitAfn,
                'store_profit_per_unit_afn' => $storeProfitPerUnit,
                'sale_price_afn' => $salePrice,
                'received_date' => $purchaseDate,
                'status' => 'active',
                'user_id' => $actor->id,
                'trash' => false,
            ]);

            if ($supplierId) {
                $this->syncSupplierInvoice(
                    supplierId: $supplierId,
                    batch: $batch,
                    totalAfn: $totalCostAfn,
                    exchangeRate: (float) ($money['rate_to_ledger'] ?? 0),
                    actorId: $actor->id,
                );

                $this->applyPaidNow(
                    $batch,
                    $supplierId,
                    (float) ($data['paid_now'] ?? 0),
                    (float) ($money['rate_to_ledger'] ?? 0),
                    $actor->id,
                );
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
                batchId: $batch->id,
                notes: 'Stock received',
                unitCostAfn: $purchasePrice,
            );

            ActivityLogger::log(
                action: 'receive',
                section: 'purchases',
                description: "Stock received for variant #{$variant->id} qty {$qty}",
                subject: $batch,
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

            return $batch;
        });
    }

    private function catalogPriceRow(ProductVariant $variant, string $priceType): ?ProductPrice
    {
        return $variant->prices
            ->where('price_type', $priceType)
            ->where('is_active', true)
            ->sortByDesc('min_qty')
            ->first(fn (ProductPrice $p) => (int) $p->min_qty === 0)
            ?? $variant->prices
                ->where('price_type', $priceType)
                ->where('is_active', true)
                ->sortByDesc('min_qty')
                ->first();
    }

    private function resolveCatalogPurchaseCurrency(ProductVariant $variant, ?Currency $ledger): ?Currency
    {
        $row = $this->catalogPriceRow($variant, 'purchase');
        if ($row?->currency_id) {
            return Currency::query()->find($row->currency_id) ?? $ledger;
        }

        return $ledger;
    }

    private function syncReceiveCatalogPrices(
        Product $product,
        ProductVariant $variant,
        float $purchasePriceAfn,
        float $salePriceAfn,
    ): void {
        $afn = Currency::query()->notTrashed()->where('code', 'AFN')->first();
        if (! $afn) {
            return;
        }

        foreach (
            [
                'purchase' => $purchasePriceAfn,
                'sale' => $salePriceAfn,
            ] as $type => $amount
        ) {
            ProductPrice::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'price_type' => $type,
                    'currency_id' => $afn->id,
                    'min_qty' => 0,
                ],
                [
                    'currency_code' => 'AFN',
                    'amount' => Money::round((string) $amount, 4),
                    'is_active' => true,
                ]
            );
        }
    }

    private function syncSupplierInvoice(
        int $supplierId,
        ProductBatch $batch,
        float $totalAfn,
        float $exchangeRate,
        int $actorId,
    ): void {
        $invoiceNo = mb_substr($batch->invoice_number ?: ('B-'.$batch->id), 0, 50);
        $date = optional($batch->purchase_date)->toDateString() ?: now()->toDateString();
        $existing = SupplierInvoice::query()
            ->where('supplier_id', $supplierId)
            ->where('invoice_no', $invoiceNo)
            ->whereDate('purchase_date', $date)
            ->first();
        $paid = min($totalAfn, round((float) ($existing->paid_afn ?? 0), 2));

        SupplierInvoice::query()->updateOrCreate(
            [
                'supplier_id' => $supplierId,
                'invoice_no' => $invoiceNo,
                'purchase_date' => $date,
            ],
            [
                'exchange_rate' => round($exchangeRate, 2),
                'total_afn' => round($totalAfn, 2),
                'paid_afn' => $paid,
                'due_afn' => round(max(0, $totalAfn - $paid), 2),
                'notes' => $batch->notes,
                'bill_image' => $existing->bill_image ?? $batch->bill_image,
                'created_by' => $existing->created_by ?? $actorId,
            ]
        );
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

    private function applyPaidNow(
        ProductBatch $batch,
        ?int $supplierId,
        float $paidNow,
        float $exchangeRate,
        int $userId,
    ): void {
        if ($paidNow <= 0 || ! $supplierId) {
            return;
        }

        $invoiceNo = mb_substr($batch->invoice_number ?: ('B-'.$batch->id), 0, 50);
        $invoice = SupplierInvoice::query()
            ->where('supplier_id', $supplierId)
            ->where('invoice_no', $invoiceNo)
            ->first();

        $invoice?->takePayment(
            $paidNow,
            optional($batch->purchase_date)->toDateString() ?: now()->toDateString(),
            $exchangeRate,
            'Paid on purchase',
            $userId,
        );
    }
}
