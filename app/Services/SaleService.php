<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InvestmentProductAllocation;
use App\Models\InvestorProfitEntry;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\Receipt;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private readonly CustomerLedgerService $ledger,
        private readonly SaleNotifier $notifier,
        private readonly CurrencyService $currency,
    ) {}

    /**
     * Create a completed sale with stock check, FIFO cost, payments, and ledger.
     *
     * @param  array<string, mixed>  $data  Validated StoreSaleRequest payload
     */
    public function createSale(array $data, User $actor): Sale
    {
        $items = $data['items'];
        $branchId = $actor->isBranchScoped()
            ? (int) $actor->branch_id
            : (int) $data['branch_id'];

        $this->assertStockAvailable($branchId, $items);

        $sale = DB::transaction(function () use ($items, $data, $branchId, $actor) {
            $payments = $data['payments'] ?? [];
            unset($data['items'], $data['payments']);

            $data['branch_id'] = $branchId;
            $data['created_by'] = $actor->id;
            $data['invoice_number'] = Sale::generateInvoiceNumber();
            $data['receipt_no'] = Sale::generateReceiptNumber();
            $data['status'] = 'completed';
            $data['payment_method'] = $data['payment_method'] ?? 'cash';
            $data['sale_date'] = $data['sale_date'] ?? now()->toDateString();

            [$customer, $previousDueAfn, $data] = $this->resolveCustomerSnapshot($data);

            if (($data['payment_method'] ?? '') === 'credit' && ! $customer) {
                throw ValidationException::withMessages([
                    'customer_id' => 'A customer is required when the sale is on credit.',
                ]);
            }

            $variantIds = collect($items)->pluck('variant_id')->unique()->all();
            $variants = ProductVariant::with([
                'product:id,name',
                'barcodes' => fn ($q) => $q->orderByDesc('is_primary'),
            ])->whereIn('id', $variantIds)->get()->keyBy('id');

            $subtotalAfn = 0.0;
            $totalCostAfn = 0.0;
            $lineData = [];

            foreach ($items as $item) {
                $prepared = $this->prepareSaleLine($branchId, $item, $variants);
                $subtotalAfn = round($subtotalAfn + $prepared['line_subtotal_afn'], 2);
                $totalCostAfn = round($totalCostAfn + $prepared['line_cost_afn'], 2);
                $lineData[] = $prepared['line'];
            }

            $discountAfn = round((float) ($data['discount_afn'] ?? 0), 2);

            $data['subtotal_afn'] = $subtotalAfn;
            $data['discount_afn'] = $discountAfn;
            $data['total_afn'] = $this->calculateTotal($subtotalAfn, $discountAfn);
            $data['total_cost_afn'] = $totalCostAfn;
            $data['total_profit_afn'] = round($data['total_afn'] - $totalCostAfn, 2);

            $newSaleAmountAfn = round((float) $data['total_afn'], 2);
            $grandTotalDueAfn = round($previousDueAfn + $newSaleAmountAfn, 2);

            $data['previous_due_afn'] = $previousDueAfn;
            $data['new_sale_amount_afn'] = $newSaleAmountAfn;
            $data['grand_total_due_afn'] = $grandTotalDueAfn;

            $sale = Sale::create(array_merge($data, $this->currency->ledgerSnapshot()));

            $totalInvestorProfit = 0.0;

            foreach ($lineData as $line) {
                $line['sale_id'] = $sale->id;
                $saleItem = SaleItem::create($line);

                $breakdown = $line['cost_breakdown'] ?? [];
                $investorProfitForLine = 0.0;

                foreach ($breakdown as $part) {
                    $batch = ProductBatch::find($part['batch_id']);
                    if (! $batch) {
                        continue;
                    }

                    $take = (int) $part['qty'];

                    if ($batch->investor_id && (float) $batch->profit_per_unit_afn > 0) {
                        $allocation = InvestmentProductAllocation::query()
                            ->where('batch_id', $batch->id)
                            ->where('status', 'active')
                            ->where('remaining_quantity', '>', 0)
                            ->orderBy('id')
                            ->first();

                        InvestorProfitEntry::recordProfit(
                            investorId: $batch->investor_id,
                            investmentId: $allocation?->investment_id,
                            productAllocationId: $allocation?->id,
                            saleId: $sale->id,
                            saleItemId: $saleItem->id,
                            quantity: $take,
                            profitPerUnitAfn: (float) $batch->profit_per_unit_afn,
                            notes: "Profit from batch {$batch->batch_code}"
                        );

                        $allocation?->recordSale($take);

                        $investorProfitForLine = round(
                            $investorProfitForLine + ((float) $batch->profit_per_unit_afn * $take),
                            2
                        );
                    }

                    $batch->reduceStock($take);

                    Inventory::decrease(
                        branchId: $sale->branch_id,
                        variantId: $saleItem->variant_id,
                        qty: $take,
                        type: 'sale',
                        batchId: $batch->id,
                        referenceType: Sale::class,
                        referenceId: $sale->id,
                        notes: "Sold via {$sale->invoice_number}"
                    );
                }

                $saleItem->update([
                    'investor_profit_afn' => $investorProfitForLine,
                ]);

                $totalInvestorProfit = round($totalInvestorProfit + $investorProfitForLine, 2);
            }

            $paidAfn = 0.0;
            $paymentIds = [];

            foreach ($payments as $payment) {
                $amountAfn = round((float) $payment['amount_afn'], 2);
                if ($amountAfn <= 0) {
                    continue;
                }

                $salePayment = SalePayment::create(array_merge($this->currency->ledgerSnapshot(), [
                    'sale_id' => $sale->id,
                    'customer_id' => $sale->customer_id,
                    'amount_afn' => $amountAfn,
                    'remaining_due_afn' => 0,
                    'payment_method' => $payment['payment_method'],
                    'reference_number' => $payment['reference_number'] ?? null,
                    'notes' => $payment['notes'] ?? null,
                    'paid_at' => now(),
                    'received_by' => $actor->id,
                ]));

                $paymentIds[] = $salePayment->id;
                $paidAfn = round($paidAfn + $amountAfn, 2);
            }

            $remainingDueAfn = round(max(0, $grandTotalDueAfn - $paidAfn), 2);
            $paymentStatus = $this->paymentStatus($paidAfn, $grandTotalDueAfn);

            $runningDue = $grandTotalDueAfn;
            foreach ($paymentIds as $paymentId) {
                $createdPayment = SalePayment::find($paymentId);
                if (! $createdPayment) {
                    continue;
                }
                $runningDue = round(max(0, $runningDue - (float) $createdPayment->amount_afn), 2);
                $createdPayment->update(['remaining_due_afn' => $runningDue]);
            }

            $sale->update([
                'received_now_afn' => $paidAfn,
                'remaining_due_afn' => $remainingDueAfn,
                'due_date' => $remainingDueAfn > 0 ? ($data['due_date'] ?? null) : null,
                'paid_afn' => $paidAfn,
                'due_afn' => $remainingDueAfn,
                'payment_status' => $paymentStatus,
                'total_investor_profit_afn' => $totalInvestorProfit,
                'store_profit_afn' => round($sale->total_profit_afn - $totalInvestorProfit, 2),
            ]);

            if ($customer) {
                $this->ledger->recordSale($sale);

                foreach ($paymentIds as $paymentId) {
                    $payment = SalePayment::find($paymentId);
                    if ($payment) {
                        $this->ledger->recordPayment($payment);
                    }
                }

                $customer->update([
                    'current_due_afn' => $remainingDueAfn,
                    'last_sale_at' => now(),
                ]);
            }

            return $sale->fresh();
        });

        try {
            $this->notifier->notify($sale);
        } catch (\Throwable $e) {
            report($e);
        }

        ActivityLogger::log(
            action: 'create',
            section: 'sales',
            description: "Sale {$sale->invoice_number} created",
            subject: $sale,
            properties: [
                'invoice_number' => $sale->invoice_number,
                'grand_total_afn' => $sale->grand_total_afn ?? null,
                'branch_id' => $sale->branch_id,
            ],
            userId: $actor->id,
        );

        return $sale;
    }

    /**
     * Record a follow-up payment against a sale (and customer ledger when applicable).
     *
     * @param  array<string, mixed>  $data  Validated payment payload
     */
    public function addPayment(Sale $sale, array $data, User $actor): Receipt
    {
        $receipt = DB::transaction(function () use ($data, $sale, $actor) {
            $amountAfn = round((float) $data['amount_afn'], 2);

            $customer = $sale->customer_id
                ? Customer::whereKey($sale->customer_id)->lockForUpdate()->first()
                : null;

            $accountDue = $customer
                ? round((float) $customer->current_due_afn, 2)
                : round((float) $sale->remaining_due_afn, 2);

            if ($amountAfn > $accountDue + 0.01) {
                throw ValidationException::withMessages([
                    'amount_afn' => 'The payment is larger than the current due.',
                ]);
            }

            $newCustomerDue = round(max(0, $accountDue - $amountAfn), 2);
            $newSaleRemaining = round(max(0, (float) $sale->remaining_due_afn - $amountAfn), 2);
            $balanceAfter = $customer ? $newCustomerDue : $newSaleRemaining;

            $salePayment = SalePayment::create(array_merge($this->currency->ledgerSnapshot(), [
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'amount_afn' => $amountAfn,
                'remaining_due_afn' => $balanceAfter,
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => now(),
                'received_by' => $actor->id,
            ]));

            $newPaidAfn = round((float) $sale->paid_afn + $amountAfn, 2);
            $paymentStatus = $newSaleRemaining <= 0.01 ? 'paid' : 'partial';

            $sale->update([
                'paid_afn' => $newPaidAfn,
                'due_afn' => $newSaleRemaining,
                'remaining_due_afn' => $newSaleRemaining,
                'payment_status' => $paymentStatus,
            ]);

            if ($customer) {
                $this->ledger->recordPayment($salePayment);

                $customer->update([
                    'current_due_afn' => $newCustomerDue,
                ]);

                $latest = $customer->sales()->notTrashed()->latest('id')->first();
                if ($latest && $latest->id !== $sale->id) {
                    $latest->update([
                        'remaining_due_afn' => $newCustomerDue,
                        'due_afn' => $newCustomerDue,
                        'payment_status' => $newCustomerDue <= 0.01 ? 'paid' : 'partial',
                    ]);
                }
            }

            return Receipt::create([
                'customer_id' => $sale->customer_id,
                'sale_id' => $sale->id,
                'sale_payment_id' => $salePayment->id,
                'receipt_number' => Receipt::generateReceiptNumber(),
                'amount_afn' => $amountAfn,
                'remaining_due_afn' => $balanceAfter,
                'receipt_type' => 'payment_receipt',
                'created_by' => $actor->id,
            ]);
        });

        ActivityLogger::log(
            action: 'payment',
            section: 'sales',
            description: "Payment on sale {$sale->invoice_number}",
            subject: $sale,
            properties: [
                'amount_afn' => $data['amount_afn'],
                'receipt_id' => $receipt->id,
            ],
            userId: $actor->id,
        );

        return $receipt;
    }

    public function calculateTotal(float $subtotalAfn, float $discountAfn): float
    {
        return round(max(0, $subtotalAfn - $discountAfn), 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function assertStockAvailable(int $branchId, array $items): void
    {
        $stockErrors = [];

        foreach ($items as $index => $item) {
            $variantId = (int) $item['variant_id'];
            $qty = (int) $item['quantity'];
            $available = ProductBatch::getAvailableQuantity($branchId, $variantId);

            if ($available < $qty) {
                $variant = ProductVariant::with('product:id,name')->find($variantId);
                $variantName = $variant
                    ? trim(($variant->product?->name ?? '').' - '.($variant->name ?? ''))
                    : "Variant #{$variantId}";

                $stockErrors[] = [
                    'index' => $index,
                    'variant_id' => $variantId,
                    'variant_name' => $variantName,
                    'requested' => $qty,
                    'available' => $available,
                    'missing' => $qty - $available,
                ];
            }
        }

        if ($stockErrors !== []) {
            throw ValidationException::withMessages([
                'stock' => 'Some products do not have enough stock.',
                'stock_errors' => json_encode($stockErrors),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?Customer, 1: float, 2: array<string, mixed>}
     */
    private function resolveCustomerSnapshot(array $data): array
    {
        $customer = null;
        $previousDueAfn = 0.0;
        $customerIdInput = $data['customer_id'] ?? null;

        if (! empty($customerIdInput) && is_numeric($customerIdInput)) {
            $customer = Customer::find((int) $customerIdInput);

            if ($customer) {
                $data['customer_id'] = $customer->id;
                $data['customer_name_snapshot'] = $customer->name;
                $data['customer_phone_snapshot'] = $customer->phone;
                $previousDueAfn = (float) $customer->current_due_afn;
            } else {
                $data['customer_id'] = null;
                $data['customer_name_snapshot'] = null;
                $data['customer_phone_snapshot'] = null;
            }
        } else {
            $data['customer_id'] = null;
            $data['customer_name_snapshot'] = null;
            $data['customer_phone_snapshot'] = null;
        }

        return [$customer, $previousDueAfn, $data];
    }

    private function paymentStatus(float $paidAfn, float $grandTotalDueAfn): string
    {
        if ($paidAfn >= $grandTotalDueAfn - 0.01) {
            return 'paid';
        }

        if ($paidAfn > 0) {
            return 'partial';
        }

        return 'unpaid';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProductVariant>  $variants
     * @param  array<string, mixed>  $item
     * @return array{line_subtotal_afn: float, line_cost_afn: float, line: array<string, mixed>}
     */
    /**
     * Build one sale line with FIFO cost breakdown (also used by sale update).
     *
     * @param  \Illuminate\Support\Collection<int, ProductVariant>  $variants
     * @param  array<string, mixed>  $item
     * @return array{line_subtotal_afn: float, line_cost_afn: float, line: array<string, mixed>}
     */
    public function prepareSaleLine(int $branchId, array $item, $variants): array
    {
        $variantId = (int) $item['variant_id'];
        $qty = (int) $item['quantity'];
        $priceAfn = round((float) $item['sale_price_afn'], 2);
        $discAfn = round((float) ($item['discount_afn'] ?? 0), 2);

        $lineSubtotalAfn = round($priceAfn * $qty, 2);
        $lineTotalAfn = round($lineSubtotalAfn - $discAfn, 2);

        $fifo = ProductBatch::calculateFifoCost($branchId, $variantId, $qty);
        $lineCostAfn = round((float) $fifo['cost_afn'], 2);
        $breakdown = $fifo['breakdown'];
        $lineProfitAfn = round($lineTotalAfn - $lineCostAfn, 2);

        $variant = $variants->get($variantId);
        $productName = null;
        $barcode = null;
        $sku = null;

        if ($variant) {
            $nameParts = array_values(array_filter([
                trim((string) ($variant->product?->name ?? '')),
                trim((string) ($variant->name ?? '')),
            ], static fn (string $part) => $part !== ''));

            $productName = $nameParts !== [] ? implode(' - ', $nameParts) : null;
            $sku = $variant->sku ?: null;

            $primaryBarcode = $variant->barcodes->firstWhere('is_primary', true)
                ?? $variant->barcodes->first();
            $barcode = $primaryBarcode?->barcode;
        }

        $batchIds = collect($breakdown)->pluck('batch_id')->filter()->unique()->values();
        $batchId = $batchIds->count() === 1 ? (int) $batchIds->first() : null;

        return [
            'line_subtotal_afn' => $lineSubtotalAfn,
            'line_cost_afn' => $lineCostAfn,
            'line' => [
                'variant_id' => $variantId,
                'batch_id' => $batchId,
                'product_name_snapshot' => $productName,
                'sku_snapshot' => $sku,
                'barcode_snapshot' => $barcode,
                'quantity' => $qty,
                'cost_price_afn' => round($lineCostAfn / max($qty, 1), 2),
                'sale_price_afn' => $priceAfn,
                'discount_afn' => $discAfn,
                'line_total_afn' => $lineTotalAfn,
                'store_profit_afn' => $lineProfitAfn,
                'investor_profit_afn' => 0,
                'cost_breakdown' => $breakdown,
            ],
        ];
    }
}
