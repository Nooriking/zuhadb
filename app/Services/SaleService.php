<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Investor;
use App\Models\InvestorTransaction;
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
        private readonly ExchangeService $exchange,
        private readonly PriceResolver $priceResolver,
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

            [$customer, $previousDueAfn, $data, $previousDueByCurrency] = $this->resolveCustomerSnapshot($data);

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

            $saleCurrencyCode = strtoupper((string) $saleCurrency->code);
            if (! in_array($saleCurrencyCode, ['AFN', 'USD', 'AED'], true)) {
                throw ValidationException::withMessages([
                    'sale_currency_id' => "Unsupported sale currency: {$saleCurrencyCode}",
                ]);
            }

            // Central Exchange Board (exchange_rates) only — never invent a silent 1:1.
            $rateOverrides = $this->resolveSaleBoardOverrides($data);

            // Persist cashier board edits into exchange_rates so future sales
            // pick them up. Historical sales keep their own FX snapshots.
            if (
                ! empty($data['usd_to_afn_rate'])
                && (float) $data['usd_to_afn_rate'] > 0
                && ! empty($data['aed_to_afn_rate'])
                && (float) $data['aed_to_afn_rate'] > 0
            ) {
                $this->currency->postBoardRates(
                    (string) $data['usd_to_afn_rate'],
                    (string) $data['aed_to_afn_rate'],
                    $data['sale_date'] ?? now(),
                    $actor->id,
                    (string) ($data['rate_source'] ?? 'sale_board'),
                );
            }

            $board = $this->currency->boardAt($data['sale_date'], $rateOverrides, allowCatalogFallback: false);

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
            $lineDiscountAfn = 0.0;
            $originalSubtotal = '0';
            $lineDiscountOriginal = '0';
            $lineData = [];

            foreach ($items as $item) {
                $prepared = $this->prepareSaleLine($branchId, $item, $variants, $saleCurrency, $board);
                $subtotalAfn = round($subtotalAfn + $prepared['line_subtotal_afn'], 2);
                $totalCostAfn = round($totalCostAfn + $prepared['line_cost_afn'], 2);
                $lineDiscountAfn = round($lineDiscountAfn + (float) $prepared['line_discount_afn'], 2);
                $originalSubtotal = Money::round(
                    function_exists('bcadd')
                        ? bcadd($originalSubtotal, (string) $prepared['line_original_subtotal'], 2)
                        : (string) ((float) $originalSubtotal + (float) $prepared['line_original_subtotal']),
                    2
                );
                $lineDiscountOriginal = Money::round(
                    function_exists('bcadd')
                        ? bcadd($lineDiscountOriginal, (string) $prepared['line_original_discount'], 2)
                        : (string) ((float) $lineDiscountOriginal + (float) $prepared['line_original_discount']),
                    2
                );
                $lineData[] = $prepared['line'];
            }

            $batchIds = collect($lineData)
                ->flatMap(fn (array $line) => collect($line['cost_breakdown'] ?? [])->pluck('batch_id'))
                ->unique()
                ->values();
            $batchesById = ProductBatch::query()
                ->with('investment:id,investor_id,currency_id,exchange_rate')
                ->whereIn('id', $batchIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $this->assertInvestorProfitFitsGross($lineData, $batchesById);

            // Header discount may be entered in sale currency (discount_amount) or legacy AFN.
            // Sale discount_afn = sum(line discounts) + header discount.
            if (isset($data['discount_amount']) && $data['discount_amount'] !== '' && $data['discount_amount'] !== null) {
                $headerDiscountOriginal = Money::round((string) $data['discount_amount'], 2);
                $headerDiscountAfn = (float) $board->equivalents($headerDiscountOriginal, $saleCurrency->code, 2)['afn'];
            } else {
                $headerDiscountAfn = round((float) ($data['discount_afn'] ?? 0), 2);
                $headerDiscountOriginal = $board->equivalents((string) $headerDiscountAfn, 'AFN', 2)[
                    strtolower($saleCurrency->code) === 'usd' ? 'usd' : (strtolower($saleCurrency->code) === 'aed' ? 'aed' : 'afn')
                ];
            }
            unset($data['discount_amount']);

            $discountAfn = round($lineDiscountAfn + $headerDiscountAfn, 2);
            $discountOriginal = Money::round(
                function_exists('bcadd')
                    ? bcadd($lineDiscountOriginal, (string) $headerDiscountOriginal, 2)
                    : (string) ((float) $lineDiscountOriginal + (float) $headerDiscountOriginal),
                2
            );

            // Block discounts that wipe out gross profit (and reserved investor profit).
            $this->assertDiscountWithinProfit($subtotalAfn, $totalCostAfn, $discountAfn, $lineData, $batchesById);

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
            $data['previous_due_by_currency'] = $previousDueByCurrency;
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

                    $investorId = $batch->investment_id
                        ? (int) ($batch->investment?->investor_id ?? 0)
                        : 0;

                    if ($investorId && (float) $batch->profit_per_unit_afn > 0) {
                        $partInvestor = (float) Money::round(
                            Money::mul((string) $batch->profit_per_unit_afn, (string) $take, 8),
                            2
                        );

                        $this->recordInvestorProfit(
                            investorId: $investorId,
                            investmentId: $batch->investment_id ? (int) $batch->investment_id : null,
                            sale: $sale,
                            saleItem: $saleItem,
                            quantity: $take,
                            profitPerUnitAfn: (float) $batch->profit_per_unit_afn,
                            profitAmountAfn: $partInvestor,
                            batchCode: (string) $batch->batch_code,
                        );

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
                $salePayment = SalePayment::createWithSnapshot([
                    'sale_id' => $sale->id,
                    'customer_id' => $sale->customer_id,
                    'amount_currency' => $payment['amount'] ?? $payment['amount_afn'] ?? $payment['amount_currency'] ?? null,
                    'currency_id' => $payment['currency_id'] ?? null,
                    'remaining_due_afn' => 0,
                    'payment_method' => $payment['payment_method'],
                    'reference_number' => $payment['reference_number'] ?? null,
                    'notes' => $payment['notes'] ?? null,
                    'paid_at' => now(),
                    'received_by' => $actor->id,
                ]);

                $amountAfn = (float) $salePayment->amount_afn;
                if ($amountAfn <= 0) {
                    $salePayment->delete();
                    continue;
                }

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
                $lockedCustomer = Customer::query()->whereKey($customer->id)->lockForUpdate()->first();
                if ($lockedCustomer) {
                    // Previous due already lives in customer_balances.
                    // 1) Add this sale in the document currency.
                    // 2) Subtract each payment in the payment's own currency (from exchange_rates snapshot).
                    $code = strtoupper((string) ($sale->sale_currency_code ?: 'AFN'));
                    $rate = $sale->rateToAfn();
                    $saleAmountCurrency = $sale->sale_amount_currency !== null
                        ? round((float) $sale->sale_amount_currency, 2)
                        : ($rate > 0 ? round($newSaleAmountAfn / $rate, 2) : $newSaleAmountAfn);

                    if (abs($newSaleAmountAfn) > 0.009) {
                        $lockedCustomer->adjustBalance($code, $saleAmountCurrency, $newSaleAmountAfn, $rate);
                    }

                    foreach ($createdPayments as $createdPayment) {
                        $payCode = strtoupper((string) ($createdPayment->currency_code ?: $code));
                        $payCurrency = round((float) ($createdPayment->amount_currency ?? $createdPayment->amount_afn ?? 0), 2);
                        $payAfn = round((float) ($createdPayment->amount_afn ?? 0), 2);
                        $payRate = $createdPayment->rate_to_ledger !== null
                            ? (float) $createdPayment->rate_to_ledger
                            : $rate;
                        if (abs($payAfn) > 0.009 || abs($payCurrency) > 0.009) {
                            $lockedCustomer->adjustBalance($payCode, -1 * $payCurrency, -1 * $payAfn, $payRate);
                        }
                    }

                    $lockedCustomer->update([
                        'last_sale_at' => now(),
                    ]);

                    $sale->update([
                        'remaining_due_by_currency' => $lockedCustomer->fresh()->dueByCurrencySnapshot(),
                    ]);
                }
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
            $customer = $sale->customer_id
                ? Customer::whereKey($sale->customer_id)->lockForUpdate()->first()
                : null;

            $salePayment = SalePayment::createWithSnapshot([
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'amount_currency' => $data['amount'] ?? $data['amount_afn'] ?? $data['amount_currency'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
                'remaining_due_afn' => 0,
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => now(),
                'received_by' => $actor->id,
            ]);

            $amountAfn = (float) $salePayment->amount_afn;

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

            $salePayment->update(['remaining_due_afn' => $balanceAfter]);

            $newPaidAfn = round((float) $sale->paid_afn + $amountAfn, 2);
            $paymentStatus = $newSaleRemaining <= 0.01 ? 'paid' : 'partial';

            $sale->update([
                'paid_afn' => $newPaidAfn,
                'due_afn' => $newSaleRemaining,
                'remaining_due_afn' => $newSaleRemaining,
                'payment_status' => $paymentStatus,
            ]);

            if ($customer) {
                $code = strtoupper((string) ($salePayment->currency_code ?: $sale->sale_currency_code ?: 'AFN'));
                $rate = $salePayment->rate_to_ledger !== null
                    ? (float) $salePayment->rate_to_ledger
                    : $sale->rateToAfn();
                $amountCurrency = $salePayment->amount_currency !== null
                    ? round((float) $salePayment->amount_currency, 2)
                    : ($rate > 0 ? round($amountAfn / $rate, 2) : $amountAfn);
                $customer->adjustBalance($code, -1 * $amountCurrency, -1 * $amountAfn, $rate);
                $sale->update([
                    'remaining_due_by_currency' => $customer->fresh()->dueByCurrencySnapshot(),
                ]);
            }

            return Receipt::create([
                'customer_id' => $sale->customer_id,
                'sale_id' => $sale->id,
                'sale_payment_id' => $salePayment->id,
                'receipt_number' => Receipt::generateReceiptNumber(),
                'currency_id' => $salePayment->currency_id,
                'currency_code' => $salePayment->currency_code,
                'amount_afn' => $amountAfn,
                'amount_currency' => $salePayment->amount_currency ?? $amountAfn,
                'exchange_rate_to_afn' => $salePayment->rate_to_ledger ?? 1,
                'rate_effective_at' => $salePayment->rate_effective_at,
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
     * Reject discounts that would push the sale below cost, or into investor-reserved profit.
     *
     * Max discount = (subtotal − FIFO cost) − reserved investor profit.
     * Example: profit 2000 AFN → discount above 2000 is blocked and the sale is not saved.
     *
     * @param  array<int, array<string, mixed>>  $lineData
     * @param  \Illuminate\Support\Collection<int, ProductBatch>|null  $batchesById
     */
    public function assertDiscountWithinProfit(
        float $subtotalAfn,
        float $totalCostAfn,
        float $discountAfn,
        array $lineData = [],
        $batchesById = null,
    ): void {
        $discountAfn = round(max(0, $discountAfn), 2);
        if ($discountAfn <= 0) {
            return;
        }

        $grossBeforeDiscount = round($subtotalAfn - $totalCostAfn, 2);

        $investorReserve = 0.0;
        foreach ($lineData as $line) {
            foreach ($line['cost_breakdown'] ?? [] as $part) {
                $batch = $batchesById?->get($part['batch_id'] ?? null);
                $investmentId = $batch?->investment_id ?? ($part['investment_id'] ?? null);
                $ppu = (float) ($batch?->profit_per_unit_afn ?? $part['profit_per_unit_afn'] ?? 0);
                if (! $investmentId || $ppu <= 0) {
                    continue;
                }
                $investorReserve = round($investorReserve + ($ppu * (int) ($part['qty'] ?? 0)), 2);
            }
        }

        $maxDiscount = max(0.0, round($grossBeforeDiscount - $investorReserve, 2));

        if ($discountAfn > $maxDiscount + 0.01) {
            $detail = $investorReserve > 0
                ? 'gross profit before discount minus reserved investor profit'
                : 'gross profit before discount';

            throw ValidationException::withMessages([
                'discount_amount' => "Discount ({$discountAfn} AFN) exceeds the maximum allowed ({$maxDiscount} AFN) based on {$detail}. The sale was not saved.",
                'discount_afn' => "Maximum discount is {$maxDiscount} AFN.",
            ]);
        }
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
                'stock' => 'Some products do not have enough stock. The sale was not saved.',
                'stock_errors' => json_encode($stockErrors),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?Customer, 1: float, 2: array<string, mixed>, 3: list<array{currency_code: string, balance_currency: float, balance_afn: float, last_rate: float|null}>}
     */
    private function resolveCustomerSnapshot(array $data): array
    {
        $customer = null;
        $previousDueAfn = 0.0;
        $previousDueByCurrency = [];
        $customerIdInput = $data['customer_id'] ?? null;

        if (! empty($customerIdInput) && is_numeric($customerIdInput)) {
            $customer = Customer::query()->lockForUpdate()->find((int) $customerIdInput);

            if ($customer) {
                $data['customer_id'] = $customer->id;
                $data['customer_name_snapshot'] = $customer->name;
                $data['customer_phone_snapshot'] = $customer->phone;
                // Snapshot outstanding balance onto the sale for receipt / grand total.
                $breakdown = $customer->dueBreakdown();
                $previousDueAfn = round(max(0, (float) $breakdown['total_afn']), 2);
                $previousDueByCurrency = $breakdown['by_currency'];
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

        return [$customer, $previousDueAfn, $data, $previousDueByCurrency];
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
     * @return array{
     *     line_subtotal_afn: float,
     *     line_discount_afn: float,
     *     line_cost_afn: float,
     *     line_original_subtotal: string,
     *     line_original_discount: string,
     *     line: array<string, mixed>
     * }
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
        // Sales always use the Central Exchange Board — never catalog 1:1 fallback.
        $board = $board ?? $this->currency->boardAt(
            now(),
            $this->resolveSaleBoardOverrides([]),
            allowCatalogFallback: false,
        );
        $code = strtoupper((string) ($saleCurrency?->code ?? 'AFN'));
        if (! in_array($code, ['AFN', 'USD', 'AED'], true)) {
            throw ValidationException::withMessages([
                'sale_currency_id' => "Unsupported sale currency: {$code}",
            ]);
        }

        $hasUnitAmount = isset($item['sale_unit_amount']) && $item['sale_unit_amount'] !== '' && $item['sale_unit_amount'] !== null;
        $hasLegacyAfn = isset($item['sale_price_afn']) && $item['sale_price_afn'] !== '' && $item['sale_price_afn'] !== null;
        $useCatalog = ! empty($item['use_catalog_price']) || (! $hasUnitAmount && ! $hasLegacyAfn);

        if ($useCatalog) {
            // Catalog unit amount from PriceResolver; AFN ledger always via transaction board.
            $resolved = $this->priceResolver->resolveSaleLine([
                'variant_id' => $variantId,
                'quantity' => $qty,
                'order_currency_code' => $code,
            ], $board);
            $unitOriginal = Money::round((string) $resolved['sale_unit_amount'], 4);
            $priceAfn = (float) Money::round($board->equivalents($unitOriginal, $code, 4)['afn'], 2);
            $lineOriginal = Money::round((string) $resolved['sale_line_amount'], 2);
        } elseif ($hasUnitAmount) {
            $unitOriginal = Money::round((string) $item['sale_unit_amount'], 4);
            $priceAfn = (float) Money::round($board->equivalents($unitOriginal, $code, 4)['afn'], 2);
            $lineOriginal = Money::round(Money::mul($unitOriginal, (string) $qty, 8), 2);
        } else {
            // Legacy clients send AFN unit price; treat as AFN regardless of document currency.
            $unitOriginal = Money::round((string) $item['sale_price_afn'], 4);
            $code = 'AFN';
            $priceAfn = (float) Money::round($unitOriginal, 2);
            $lineOriginal = Money::round(Money::mul($unitOriginal, (string) $qty, 8), 2);
        }

        if (isset($item['discount_amount']) && $item['discount_amount'] !== '' && $item['discount_amount'] !== null) {
            $discOriginal = Money::round((string) $item['discount_amount'], 2);
            $discAfn = (float) $board->equivalents($discOriginal, $code, 2)['afn'];
        } else {
            $discAfn = round((float) ($item['discount_afn'] ?? 0), 2);
            $discOriginal = $board->equivalents((string) $discAfn, 'AFN', 2)[
                strtolower($code) === 'usd' ? 'usd' : (strtolower($code) === 'aed' ? 'aed' : 'afn')
            ];
        }

        $lineSubtotalAfn = round($priceAfn * $qty, 2);
        $lineTotalAfn = round($lineSubtotalAfn - $discAfn, 2);

        try {
            $fifo = ProductBatch::calculateFifoCost($branchId, $variantId, $qty);
        } catch (\Throwable $e) {
            // Convert stock shortfall into a validation error so the sale transaction rolls back cleanly.
            if (! str_contains($e->getMessage(), 'Insufficient stock')) {
                throw $e;
            }

            $available = ProductBatch::getAvailableQuantity($branchId, $variantId);
            $variant = $variants->get($variantId);
            $variantName = $variant
                ? trim(($variant->product?->name ?? '').' - '.($variant->name ?? ''))
                : "Variant #{$variantId}";

            throw ValidationException::withMessages([
                'stock' => 'Some products do not have enough stock. The sale was not saved.',
                'stock_errors' => json_encode([[
                    'variant_id' => $variantId,
                    'variant_name' => $variantName !== '' ? $variantName : "Variant #{$variantId}",
                    'requested' => $qty,
                    'available' => $available,
                    'missing' => max(0, $qty - $available),
                ]]),
            ]);
        }

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
            'line_discount_afn' => $discAfn,
            'line_cost_afn' => $lineCostAfn,
            'line_original_subtotal' => $lineOriginal,
            'line_original_discount' => Money::round((string) $discOriginal, 2),
            'line' => $line,
        ];
    }

    /**
     * Resolve USD/AED→AFN board overrides for a sale.
     * Prefer authorized request overrides; otherwise require exchange_rates rows.
     *
     * @param  array<string, mixed>  $data
     * @return array{usd_to_afn: string, aed_to_afn: string, usd_to_aed?: string, source: string}
     */
    private function resolveSaleBoardOverrides(array $data): array
    {
        $rateOverrides = [];
        if (! empty($data['usd_to_afn_rate']) && (float) $data['usd_to_afn_rate'] > 0) {
            $rateOverrides['usd_to_afn'] = (string) $data['usd_to_afn_rate'];
        }
        if (! empty($data['aed_to_afn_rate']) && (float) $data['aed_to_afn_rate'] > 0) {
            $rateOverrides['aed_to_afn'] = (string) $data['aed_to_afn_rate'];
        }
        if (! empty($data['usd_to_aed_rate']) && (float) $data['usd_to_aed_rate'] > 0) {
            $rateOverrides['usd_to_aed'] = (string) $data['usd_to_aed_rate'];
        }

        if ($rateOverrides !== []) {
            if (empty($rateOverrides['usd_to_afn']) || empty($rateOverrides['aed_to_afn'])) {
                throw ValidationException::withMessages([
                    'exchange_rate' => 'Both USD→AFN and AED→AFN rates are required when overriding the board.',
                ]);
            }

            $rateOverrides['source'] = (string) ($data['rate_source'] ?? 'transaction');

            return $rateOverrides;
        }

        try {
            $current = $this->exchange->getCurrentRates();
        } catch (ValidationException) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'Central exchange rate board is unavailable. Please add USD/AED rates in Exchange Rates first.',
            ]);
        }

        $usdToAfn = (float) ($current['usd_to_afn'] ?? 0);
        $aedToAfn = (float) ($current['aed_to_afn'] ?? 0);

        if ($usdToAfn <= 0 || $aedToAfn <= 0) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'Central exchange rate board is unavailable. Please add USD/AED rates in Exchange Rates first.',
            ]);
        }

        return [
            'usd_to_afn' => (string) $current['usd_to_afn'],
            'aed_to_afn' => (string) $current['aed_to_afn'],
            'usd_to_aed' => (string) ($current['usd_to_aed'] ?? ''),
            'source' => (string) ($current['source'] ?? 'exchange_rates'),
        ];
    }

    private function recordInvestorProfit(
        int $investorId,
        ?int $investmentId,
        Sale $sale,
        SaleItem $saleItem,
        int $quantity,
        float $profitPerUnitAfn,
        float $profitAmountAfn,
        string $batchCode,
    ): void {
        if ($profitAmountAfn <= 0) {
            return;
        }

        $investor = Investor::query()->find($investorId);
        if (! $investor) {
            return;
        }

        $wallet = $investor->getOrCreateWallet();
        $wallet->adjustProfit('AFN', $profitAmountAfn, $profitAmountAfn, 1.0);

        InvestorTransaction::record(
            investorId: $investorId,
            type: 'profit_earned',
            amountAfn: $profitAmountAfn,
            balanceType: 'profit',
            options: [
                'investment_id' => $investmentId,
                'currency_code' => 'AFN',
                'amount_currency' => $profitAmountAfn,
                'exchange_rate' => 1,
                'reference_type' => SaleItem::class,
                'reference_id' => $saleItem->id,
                'description' => "Profit from sale {$sale->invoice_number} · batch {$batchCode} · qty {$quantity} × {$profitPerUnitAfn} AFN",
                'transaction_date' => optional($sale->sale_date)->toDateString() ?? now()->toDateString(),
            ]
        );
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
                if (! $batch || ! $batch->investment_id) {
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

}
