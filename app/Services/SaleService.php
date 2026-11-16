<?php

namespace App\Services;

use App\Models\Currency;
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
use App\Support\FxBoard;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

            $ledger = Currency::ledger();
            $saleCurrency = ! empty($data['sale_currency_id'])
                ? $this->currency->requireActive((int) $data['sale_currency_id'], 'sale_currency_id')
                : ($ledger ?? throw ValidationException::withMessages([
                    'sale_currency_id' => 'Ledger currency (AFN) is required.',
                ]));

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

            $board = $this->currency->boardAt($data['sale_date'], $rateOverrides, allowCatalogFallback: true);

            unset(
                $data['sale_currency_id'],
                $data['usd_to_afn_rate'],
                $data['aed_to_afn_rate'],
                $data['rate_source'],
                $data['usd_to_aed_rate'],
            );

            $variantIds = collect($items)->pluck('variant_id')->unique()->all();
            $variants = ProductVariant::with([
                'product:id,name',
                'barcodes' => fn ($q) => $q->orderByDesc('is_primary'),
            ])->whereIn('id', $variantIds)->get()->keyBy('id');

            $subtotalAfn = 0.0;
            $totalCostAfn = 0.0;
            $originalSubtotal = '0';
            $lineData = [];

            foreach ($items as $item) {
                $prepared = $this->prepareSaleLine($branchId, $item, $variants, $saleCurrency, $board);
                $subtotalAfn = round($subtotalAfn + $prepared['line_subtotal_afn'], 2);
                $totalCostAfn = round($totalCostAfn + $prepared['line_cost_afn'], 2);
                $originalSubtotal = Money::round(
                    function_exists('bcadd')
                        ? bcadd($originalSubtotal, (string) $prepared['line_original_subtotal'], 2)
                        : (string) ((float) $originalSubtotal + (float) $prepared['line_original_subtotal']),
                    2
                );
                $lineData[] = $prepared['line'];
            }

            $batchIds = collect($lineData)
                ->flatMap(fn (array $line) => collect($line['cost_breakdown'] ?? [])->pluck('batch_id'))
                ->unique()
                ->values();
            $batchesById = ProductBatch::query()
                ->whereIn('id', $batchIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $allocationsByBatch = InvestmentProductAllocation::query()
                ->whereIn('batch_id', $batchIds)
                ->where('status', 'active')
                ->where('remaining_quantity', '>', 0)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->groupBy('batch_id');

            $this->assertInvestorProfitFitsGross($lineData, $batchesById);

            // Document discount may be entered in sale currency (discount_amount) or legacy AFN.
            if (isset($data['discount_amount']) && $data['discount_amount'] !== '' && $data['discount_amount'] !== null) {
                $discountOriginal = Money::round((string) $data['discount_amount'], 2);
                $discountAfn = (float) $board->equivalents($discountOriginal, $saleCurrency->code, 2)['afn'];
            } else {
                $discountAfn = round((float) ($data['discount_afn'] ?? 0), 2);
                $discountOriginal = $board->equivalents((string) $discountAfn, 'AFN', 2)[
                    strtolower($saleCurrency->code) === 'usd' ? 'usd' : (strtolower($saleCurrency->code) === 'aed' ? 'aed' : 'afn')
                ];
            }
            unset($data['discount_amount']);

            $data['subtotal_afn'] = $subtotalAfn;
            $data['discount_afn'] = $discountAfn;
            $data['total_afn'] = $this->calculateTotal($subtotalAfn, $discountAfn);
            $data['total_cost_afn'] = $totalCostAfn;
            $data['total_profit_afn'] = round($data['total_afn'] - $totalCostAfn, 2);

            $originalTotal = Money::round(
                function_exists('bcsub')
                    ? bcsub($originalSubtotal, (string) $discountOriginal, 2)
                    : (string) max(0, (float) $originalSubtotal - (float) $discountOriginal),
                2
            );

            $newSaleAmountAfn = round((float) $data['total_afn'], 2);
            $grandTotalDueAfn = round($previousDueAfn + $newSaleAmountAfn, 2);

            $data['previous_due_afn'] = $previousDueAfn;
            $data['new_sale_amount_afn'] = $newSaleAmountAfn;
            $data['grand_total_due_afn'] = $grandTotalDueAfn;

            $fxSnapshot = $this->currency->saleBoardSnapshot(
                (string) $data['total_afn'],
                $board,
                $saleCurrency,
                $originalTotal,
            );
            unset($fxSnapshot['total_afn_check']);

            $sale = Sale::create(array_merge(
                $data,
                $this->currency->ledgerSnapshot(),
                $fxSnapshot,
            ));

            $totalInvestorProfit = 0.0;

            foreach ($lineData as $line) {
                $line['sale_id'] = $sale->id;
                $saleItem = SaleItem::create($line);

                $breakdown = $line['cost_breakdown'] ?? [];
                $investorProfitForLine = 0.0;
                $companyProfitForLine = 0.0;
                $grossProfitForLine = (float) ($line['gross_profit_afn'] ?? 0);
                $enrichedBreakdown = [];

                foreach ($breakdown as $part) {
                    $batch = $batchesById->get($part['batch_id']);
                    if (! $batch) {
                        continue;
                    }

                    $take = (int) $part['qty'];
                    $partGross = (float) ($part['gross_profit_afn'] ?? 0);
                    $partInvestor = 0.0;

                    if ($batch->investor_id && (float) $batch->profit_per_unit_afn > 0) {
                        $partInvestor = (float) Money::round(
                            Money::mul((string) $batch->profit_per_unit_afn, (string) $take, 8),
                            2
                        );
                        $left = $take;
                        $batchAllocations = $allocationsByBatch->get($batch->id) ?? collect();

                        foreach ($batchAllocations as $allocation) {
                            if ($left <= 0) {
                                break;
                            }
                            if ($allocation->status !== 'active' || (int) $allocation->remaining_quantity <= 0) {
                                continue;
                            }

                            $allocTake = min($left, (int) $allocation->remaining_quantity);
                            if ($allocTake <= 0) {
                                continue;
                            }

                            $profitOriginal = Schema::hasColumn('product_batches', 'profit_per_unit_currency')
                                ? (float) ($batch->profit_per_unit_currency ?? $batch->profit_per_unit_afn)
                                : (float) $batch->profit_per_unit_afn;
                            $profitFx = $profitOriginal > 0
                                ? (float) Money::round(
                                    Money::div((string) $batch->profit_per_unit_afn, (string) $profitOriginal, 12),
                                    8
                                )
                                : null;

                            InvestorProfitEntry::recordProfit(
                                investorId: (int) $batch->investor_id,
                                investmentId: $allocation->investment_id,
                                productAllocationId: $allocation->id,
                                saleId: $sale->id,
                                saleItemId: $saleItem->id,
                                quantity: $allocTake,
                                profitPerUnitAfn: (float) $batch->profit_per_unit_afn,
                                profitPerUnitCurrency: $profitOriginal,
                                currencyId: $batch->profit_currency_id ?? null,
                                exchangeRate: $profitFx,
                                notes: "Profit from batch {$batch->batch_code}"
                            );

                            $allocation->recordSale($allocTake);
                            $left -= $allocTake;
                        }

                        // Investor-owned batch with no allocation row: still record profit against investor.
                        if ($left > 0) {
                            $profitOriginal = Schema::hasColumn('product_batches', 'profit_per_unit_currency')
                                ? (float) ($batch->profit_per_unit_currency ?? $batch->profit_per_unit_afn)
                                : (float) $batch->profit_per_unit_afn;
                            $profitFx = $profitOriginal > 0
                                ? (float) Money::round(
                                    Money::div((string) $batch->profit_per_unit_afn, (string) $profitOriginal, 12),
                                    8
                                )
                                : null;

                            InvestorProfitEntry::recordProfit(
                                investorId: (int) $batch->investor_id,
                                investmentId: null,
                                productAllocationId: null,
                                saleId: $sale->id,
                                saleItemId: $saleItem->id,
                                quantity: $left,
                                profitPerUnitAfn: (float) $batch->profit_per_unit_afn,
                                profitPerUnitCurrency: $profitOriginal,
                                currencyId: $batch->profit_currency_id ?? null,
                                exchangeRate: $profitFx,
                                notes: "Profit from batch {$batch->batch_code}"
                            );
                        }

                        $investorProfitForLine = (float) Money::round(
                            Money::add((string) $investorProfitForLine, (string) $partInvestor, 8),
                            2
                        );
                    }

                    $partCompany = (float) Money::round(
                        Money::sub((string) $partGross, (string) $partInvestor, 8),
                        2
                    );
                    $companyProfitForLine = (float) Money::round(
                        Money::add((string) $companyProfitForLine, (string) $partCompany, 8),
                        2
                    );

                    $enrichedBreakdown[] = array_merge($part, [
                        'investor_profit_afn' => $partInvestor,
                        'company_profit_afn' => $partCompany,
                        'profit_per_unit_afn' => (float) $batch->profit_per_unit_afn,
                    ]);

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
                    'gross_profit_afn' => $grossProfitForLine,
                    'investor_profit_afn' => $investorProfitForLine,
                    'store_profit_afn' => round($grossProfitForLine - $investorProfitForLine, 2),
                    'cost_breakdown' => $enrichedBreakdown !== [] ? $enrichedBreakdown : $breakdown,
                ]);

                $totalInvestorProfit = round($totalInvestorProfit + $investorProfitForLine, 2);
            }

            $paidAfn = 0.0;
            $createdPayments = [];

            foreach ($payments as $payment) {
                $resolved = $this->resolvePaymentMoney($payment);
                $amountAfn = $resolved['amount_afn'];
                if ($amountAfn <= 0) {
                    continue;
                }

                $salePayment = SalePayment::create(array_merge($this->currency->ledgerSnapshot(), $resolved, [
                    'sale_id' => $sale->id,
                    'customer_id' => $sale->customer_id,
                    'remaining_due_afn' => 0,
                    'payment_method' => $payment['payment_method'],
                    'reference_number' => $payment['reference_number'] ?? null,
                    'notes' => $payment['notes'] ?? null,
                    'paid_at' => now(),
                    'received_by' => $actor->id,
                ]));

                $createdPayments[] = $salePayment;
                $paidAfn = round($paidAfn + $amountAfn, 2);
            }

            $remainingDueAfn = round(max(0, $grandTotalDueAfn - $paidAfn), 2);
            $paymentStatus = $this->paymentStatus($paidAfn, $grandTotalDueAfn);

            $runningDue = $grandTotalDueAfn;
            foreach ($createdPayments as $createdPayment) {
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

                foreach ($createdPayments as $payment) {
                    $this->ledger->recordPayment($payment);
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
            $resolved = $this->resolvePaymentMoney($data);
            $amountAfn = $resolved['amount_afn'];

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

            $salePayment = SalePayment::create(array_merge($this->currency->ledgerSnapshot(), $resolved, [
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
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
                'amount_afn' => $data['amount_afn'] ?? $data['amount'] ?? null,
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
     * Build one sale line with FIFO cost breakdown (also used by sale update).
     *
     * Line prices may be entered as sale_unit_amount in the document sale currency,
     * or as legacy sale_price_afn (AFN). Profit always uses AFN ledger amounts.
     *
     * @param  \Illuminate\Support\Collection<int, ProductVariant>  $variants
     * @param  array<string, mixed>  $item
     * @return array{line_subtotal_afn: float, line_cost_afn: float, line_original_subtotal: string, line: array<string, mixed>}
     */
    public function prepareSaleLine(
        int $branchId,
        array $item,
        $variants,
        ?Currency $saleCurrency = null,
        ?FxBoard $board = null,
    ): array {
        $variantId = (int) $item['variant_id'];
        $qty = (int) $item['quantity'];
        $saleCurrency = $saleCurrency ?? Currency::ledger();
        $board = $board ?? $this->currency->boardAt(now(), [], allowCatalogFallback: true);
        $code = strtoupper((string) ($saleCurrency?->code ?? 'AFN'));

        if (isset($item['sale_unit_amount']) && $item['sale_unit_amount'] !== '' && $item['sale_unit_amount'] !== null) {
            $unitOriginal = Money::round((string) $item['sale_unit_amount'], 4);
        } elseif (isset($item['sale_price_afn']) && $item['sale_price_afn'] !== '' && $item['sale_price_afn'] !== null) {
            // Legacy clients send AFN unit price; treat as AFN regardless of document currency.
            $unitOriginal = Money::round((string) $item['sale_price_afn'], 4);
            $code = 'AFN';
        } else {
            throw ValidationException::withMessages([
                'sale_unit_amount' => 'Sale unit price is required.',
            ]);
        }

        $unitEq = $board->equivalents($unitOriginal, $code, 4);
        $priceAfn = (float) Money::round($unitEq['afn'], 2);

        if (isset($item['discount_amount']) && $item['discount_amount'] !== '' && $item['discount_amount'] !== null) {
            $discOriginal = Money::round((string) $item['discount_amount'], 2);
            $discAfn = (float) $board->equivalents($discOriginal, $code, 2)['afn'];
        } else {
            $discAfn = round((float) ($item['discount_afn'] ?? 0), 2);
        }

        $lineSubtotalAfn = round($priceAfn * $qty, 2);
        $lineTotalAfn = round($lineSubtotalAfn - $discAfn, 2);
        $lineOriginal = Money::round(Money::mul($unitOriginal, (string) $qty, 8), 2);
        $lineEq = $board->equivalents($lineOriginal, $code, 2);

        $fifo = ProductBatch::calculateFifoCost($branchId, $variantId, $qty);
        $lineCostAfn = round((float) $fifo['cost_afn'], 2);
        $breakdown = $fifo['breakdown'];
        $grossProfitAfn = round($lineTotalAfn - $lineCostAfn, 2);

        // Allocate net revenue across FIFO parts so per-batch gross = revenue_share − cost.
        $revenuePerUnit = $qty > 0 ? round($lineTotalAfn / $qty, 6) : 0.0;
        $allocatedRevenue = 0.0;
        $enriched = [];
        $lastIndex = count($breakdown) - 1;
        foreach ($breakdown as $i => $part) {
            $take = (int) $part['qty'];
            $costPart = round((float) ($part['line_cost_afn'] ?? ((float) ($part['cost_per_unit_afn'] ?? 0) * $take)), 2);
            if ($i === $lastIndex) {
                $revenuePart = round($lineTotalAfn - $allocatedRevenue, 2);
            } else {
                $revenuePart = round($revenuePerUnit * $take, 2);
                $allocatedRevenue = round($allocatedRevenue + $revenuePart, 2);
            }
            $grossPart = round($revenuePart - $costPart, 2);
            $investorPpu = (float) ($part['profit_per_unit_afn'] ?? 0);
            $enriched[] = array_merge($part, [
                'revenue_afn' => $revenuePart,
                'gross_profit_afn' => $grossPart,
                'gross_profit_per_unit_afn' => $take > 0 ? round($grossPart / $take, 4) : 0,
                'investor_profit_per_unit_afn' => $investorPpu,
            ]);
        }
        $breakdown = $enriched;

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

        $line = [
            'variant_id' => $variantId,
            'batch_id' => $batchId,
            'product_name_snapshot' => $productName,
            'sku_snapshot' => $sku,
            'barcode_snapshot' => $barcode,
            'sale_currency_code' => $code,
            'sale_unit_amount' => $unitOriginal,
            'sale_line_amount' => $lineOriginal,
            'unit_amount_usd' => $unitEq['usd'],
            'unit_amount_aed' => $unitEq['aed'],
            'line_amount_usd' => $lineEq['usd'],
            'line_amount_aed' => $lineEq['aed'],
            'quantity' => $qty,
            'cost_price_afn' => round($lineCostAfn / max($qty, 1), 2),
            'sale_price_afn' => $priceAfn,
            'discount_afn' => $discAfn,
            'line_total_afn' => $lineTotalAfn,
            // store_profit_afn = company residual after investor (filled after investor calc).
            'store_profit_afn' => $grossProfitAfn,
            'investor_profit_afn' => 0,
            'cost_breakdown' => $breakdown,
        ];

        if (Schema::hasColumn('sale_items', 'gross_profit_afn')) {
            $line['gross_profit_afn'] = $grossProfitAfn;
        }

        return [
            'line_subtotal_afn' => $lineSubtotalAfn,
            'line_cost_afn' => $lineCostAfn,
            'line_original_subtotal' => $lineOriginal,
            'line' => $line,
        ];
    }

    /**
     * Block sales where fixed investor profit per unit exceeds gross profit on any FIFO part.
     *
     * @param  list<array<string, mixed>>  $lineData
     * @param  \Illuminate\Support\Collection<int, ProductBatch>  $batchesById
     */
    private function assertInvestorProfitFitsGross(array $lineData, $batchesById): void
    {
        foreach ($lineData as $lineIndex => $line) {
            foreach ($line['cost_breakdown'] ?? [] as $part) {
                $batch = $batchesById->get($part['batch_id'] ?? null);
                if (! $batch || ! $batch->investor_id) {
                    continue;
                }

                $investorPpu = (float) $batch->profit_per_unit_afn;
                if ($investorPpu <= 0) {
                    continue;
                }

                $take = max(1, (int) ($part['qty'] ?? 1));
                $grossPerUnit = isset($part['gross_profit_per_unit_afn'])
                    ? (float) $part['gross_profit_per_unit_afn']
                    : round(((float) ($part['gross_profit_afn'] ?? 0)) / $take, 4);

                if ($investorPpu > $grossPerUnit + 0.0001) {
                    throw ValidationException::withMessages([
                        "items.{$lineIndex}.sale_unit_amount" =>
                            "Investor fixed profit per unit ({$investorPpu} AFN) exceeds gross profit per unit "
                            ."({$grossPerUnit} AFN) for batch {$batch->batch_code}. "
                            .'Raise the sale price, lower the investor profit, or choose different stock. '
                            .'The sale was blocked so company profit cannot go negative.',
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array{
     *     amount_afn: float,
     *     amount_currency: float,
     *     currency_id: int|null,
     *     currency_code: string|null,
     *     rate_to_ledger: string,
     *     rate_kind: string
     * }
     */
    private function resolvePaymentMoney(array $payment): array
    {
        $ledger = Currency::ledger();
        $currencyId = $payment['currency_id'] ?? null;
        $entered = $payment['amount'] ?? $payment['amount_afn'] ?? null;
        $paidAt = $payment['paid_at'] ?? now();

        if ($entered === null || $entered === '') {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount is required.',
            ]);
        }

        if (empty($currencyId) || ($ledger && (int) $currencyId === (int) $ledger->id)) {
            $amount = round((float) $entered, 2);

            return [
                'amount_afn' => $amount,
                'amount_currency' => $amount,
                'currency_id' => $ledger?->id,
                'currency_code' => $ledger?->code ?? (string) config('business.default_currency', 'AFN'),
                'rate_to_ledger' => '1.00000000',
                'rate_kind' => 'ledger',
            ];
        }

        $currency = $this->currency->requireActive((int) $currencyId, 'currency_id');
        $resolved = $this->currency->resolveAt($currency, $ledger ?? $currency, $paidAt);
        $rate = (string) $resolved['rate'];
        $afn = (float) \App\Support\Money::round(
            \App\Support\Money::mul((string) $entered, $rate, 12),
            2
        );

        return [
            'amount_afn' => round($afn, 2),
            'amount_currency' => round((float) $entered, 2),
            'currency_id' => $currency->id,
            'currency_code' => $currency->code,
            'rate_to_ledger' => $rate,
            'rate_kind' => $resolved['kind'] === 'catalog' ? 'catalog' : 'operational',
        ];
    }
}
