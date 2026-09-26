<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UpdateSaleRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InvestorProfitEntry;
use App\Models\ProductBatch;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Models\Receipt;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Services\CustomerLedgerService;
use App\Services\SaleNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    /* =========================================================
     |  INDEX
     ========================================================= */

    public function index(Request $request): Response
    {
        $search        = $request->query('search');
        $branchId      = $request->query('branch_id');
        $customerId    = $request->query('customer_id');
        $paymentStatus = $request->query('payment_status');
        $paymentMethod = $request->query('payment_method');
        $status        = $request->query('status');
        $onlyDue       = $request->boolean('only_due');
        $user          = $request->user();

        if ($user?->isBranchScoped()) {
            $branchId = $user->branch_id;
        }

        $sales = Sale::query()
            ->with([
                'branch:id,name,code',
                'customer:id,name,phone',
                'creator:id,name',
            ])
            ->withCount('items')
            ->notTrashed()
            ->when($search, function ($q, $s) {
                $digits = preg_replace('/\D+/', '', (string) $s);
                $q->where(function ($q) use ($s, $digits) {
                    $q->where('invoice_number', 'like', "%{$s}%")
                      ->orWhere('receipt_no', 'like', "%{$s}%")
                      ->orWhere('customer_phone_snapshot', 'like', "%{$s}%")
                      ->orWhereHas('customer', function ($customer) use ($s, $digits) {
                          $customer->where('name', 'like', "%{$s}%")
                              ->orWhere('phone', 'like', "%{$s}%");
                          if ($digits !== '') {
                              $customer->orWhere('phone', 'like', "%{$digits}%");
                          }
                      });
                });
            })
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($customerId, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($paymentStatus, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($paymentMethod, fn ($q, $v) => $q->where('payment_method', $v))
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->when($onlyDue, fn ($q) => $q->where('remaining_due_afn', '>', 0))
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $statsQuery = Sale::notTrashed()
            ->when($user?->isBranchScoped(), fn ($q) => $q->where('branch_id', $user->branch_id));
        $today = now()->toDateString();
        $statsRow = (clone $statsQuery)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN sale_date = ? THEN total_afn ELSE 0 END), 0) as today_sales,
                 COALESCE(SUM(CASE WHEN sale_date = ? THEN 1 ELSE 0 END), 0) as today_count,
                 COALESCE(SUM(CASE WHEN sale_date = ? THEN total_profit_afn ELSE 0 END), 0) as today_profit,
                 COALESCE(SUM(remaining_due_afn), 0) as total_due',
                [$today, $today, $today]
            )
            ->first();

        $stats = [
            'today_sales'  => (float) $statsRow->today_sales,
            'today_count'  => (int) $statsRow->today_count,
            'today_profit' => (float) $statsRow->today_profit,
            'total_due'    => (float) $statsRow->total_due,
        ];

        return Inertia::render('sales/index', [
            'sales'     => $sales,
            'branches'  => Branch::notTrashed()->visibleTo($user)->select('id', 'name', 'code')->get(),
            'customers' => Customer::notTrashed()->select('id', 'name', 'phone')->orderBy('name')->get(),
            'filters'   => [
                'search'         => $search,
                'branch_id'      => $branchId,
                'customer_id'    => $customerId,
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod,
                'status'         => $status,
                'only_due'       => $onlyDue,
            ],
            'stats' => $stats,
        ]);
    }

    /* =========================================================
     |  CREATE
     ========================================================= */

    public function create(): Response
    {
        $user = Auth::user();

        return Inertia::render('sales/create', [
            'branches'   => Branch::notTrashed()->active()->visibleTo($user)->select('id', 'name', 'code')->get(),
            'customers'  => Customer::notTrashed()->active()
                ->select('id', 'name', 'phone', 'address', 'notes', 'status', 'current_due_afn', 'credit_limit_afn')
                ->orderBy('name')
                ->get(),
            'variants'   => ProductVariant::query()
                ->notTrashed()
                ->with([
                    'product:id,name,brand_id',
                    'product.brand:id,name',
                ])
                ->select(
                    'id', 'product_id', 'name', 'volume_ml', 'concentration',
                    'sale_price_afn', 'wholesale_price_afn', 'wholesale_min_qty', 'purchase_price_afn'
                )
                ->get(),
            'nextNumber' => Sale::generateInvoiceNumber(),
            'supplierCredits' => \App\Models\Product::supplierCredits(),
        ]);
    }

    /* =========================================================
     |  STORE — چک موجودی + فروش + کاهش انبار + سود سرمایه‌گذار + قرض مشتری
     ========================================================= */

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $data     = $request->validated();
        $items    = $data['items'];
        $actor    = Auth::user();
        $branchId = $actor?->isBranchScoped() ? (int) $actor->branch_id : (int) $data['branch_id'];

        /* ═══════════════════════════════════════════════════════
         |  ⭐ ۱. چک موجودی قبل از هر کاری
         ═══════════════════════════════════════════════════════ */

        $stockErrors = [];

        foreach ($items as $index => $item) {
            $variantId = (int) $item['variant_id'];
            $qty       = (int) $item['quantity'];

            $available = ProductBatch::getAvailableQuantity($branchId, $variantId);

            if ($available < $qty) {
                $variant = ProductVariant::with('product:id,name')->find($variantId);

                $variantName = $variant
                    ? trim(($variant->product?->name ?? '') . ' - ' . ($variant->name ?? ''))
                    : "Variant #{$variantId}";

                $stockErrors[] = [
                    'index'        => $index,
                    'variant_id'   => $variantId,
                    'variant_name' => $variantName,
                    'requested'    => $qty,
                    'available'    => $available,
                    'missing'      => $qty - $available,
                ];
            }
        }

        if (! empty($stockErrors)) {
            return back()
                ->withErrors([
                    'stock'        => 'Some products do not have enough stock.',
                    'stock_errors' => json_encode($stockErrors),
                ])
                ->withInput();
        }

        /* ═══════════════════════════════════════════════════════
         |  ⭐ ۲. موجودی کافی است → ثبت فروش
         ═══════════════════════════════════════════════════════ */

        $sale = DB::transaction(function () use ($items, $data, $branchId) {
            $payments = $data['payments'] ?? [];
            unset($data['items'], $data['payments']);

            /* ═══ Meta ═══ */
            $data['branch_id']      = $branchId;
            $data['created_by']     = Auth::id();
            $data['invoice_number'] = Sale::generateInvoiceNumber();
            $data['receipt_no']     = Sale::generateReceiptNumber();
            $data['status']         = 'completed';
            $data['payment_method'] = $data['payment_method'] ?? 'cash';
            $data['sale_date']      = $data['sale_date'] ?? now()->toDateString();

            /* ═══════════════════════════════════════════════════════
             |  ⭐ Snapshot مشتری + قرض قبلی (با اعتبارسنجی سخت)
             ═══════════════════════════════════════════════════════ */

            $customer       = null;
            $previousDueAfn = 0;

            $customerIdInput = $data['customer_id'] ?? null;

            if (! empty($customerIdInput) && is_numeric($customerIdInput)) {
                $customer = Customer::find((int) $customerIdInput);

                if ($customer) {
                    $data['customer_id']             = $customer->id;
                    $data['customer_name_snapshot']  = $customer->name;
                    $data['customer_phone_snapshot'] = $customer->phone;

                    // قرض قبلی مشتری
                    $previousDueAfn = (float) $customer->current_due_afn;
                } else {
                    // مشتری پیدا نشد → پاک کن
                    $data['customer_id']             = null;
                    $data['customer_name_snapshot']  = null;
                    $data['customer_phone_snapshot'] = null;
                }
            } else {
                // Walk-in یا خالی
                $data['customer_id']             = null;
                $data['customer_name_snapshot']  = null;
                $data['customer_phone_snapshot'] = null;
            }

            if (($data['payment_method'] ?? '') === 'credit' && ! $customer) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'customer_id' => 'A customer is required when the sale is on credit.',
                ]);
            }

            /* ═══ پیش‌واکشی variant ها ═══ */
            $variantIds = collect($items)->pluck('variant_id')->unique()->all();
            $variants   = ProductVariant::with([
                'product:id,name',
                'barcodes' => fn ($q) => $q->orderByDesc('is_primary'),
            ])->whereIn('id', $variantIds)->get()->keyBy('id');

            /* ═══════════════════════════════════════════════════════
             |  ⭐ محاسبه اقلام — با round(2) دقیق
             ═══════════════════════════════════════════════════════ */

            $subtotalAfn  = 0.0;
            $totalCostAfn = 0.0;
            $lineData     = [];

            foreach ($items as $item) {
                $prepared = $this->prepareSaleLine($branchId, $item, $variants);

                $subtotalAfn  = round($subtotalAfn + $prepared['line_subtotal_afn'], 2);
                $totalCostAfn = round($totalCostAfn + $prepared['line_cost_afn'], 2);

                $lineData[] = $prepared['line'];
            }

            /* ═══════════════════════════════════════════════════════
             |  ⭐ Totals — با round(2)
             ═══════════════════════════════════════════════════════ */

            $discountAfn = round((float) ($data['discount_afn'] ?? 0), 2);

            $data['subtotal_afn']     = $subtotalAfn;
            $data['discount_afn']     = $discountAfn;
            $data['total_afn']        = round($subtotalAfn - $discountAfn, 2);
            $data['total_cost_afn']   = $totalCostAfn;
            $data['total_profit_afn'] = round($data['total_afn'] - $totalCostAfn, 2);

            /* ═══════════════════════════════════════════════════════
             |  ⭐ محاسبه Due Flow — با round(2)
             ═══════════════════════════════════════════════════════ */

            $newSaleAmountAfn = round((float) $data['total_afn'], 2);
            $grandTotalDueAfn = round($previousDueAfn + $newSaleAmountAfn, 2);

            $data['previous_due_afn']    = $previousDueAfn;
            $data['new_sale_amount_afn'] = $newSaleAmountAfn;
            $data['grand_total_due_afn'] = $grandTotalDueAfn;

            /* ═══ ساخت Sale ═══ */
            $sale = Sale::create($data);

            /* ═══════════════════════════════════════════════════════
             |  ⭐ SaleItem + کاهش انبار + سود سرمایه‌گذار
             ═══════════════════════════════════════════════════════ */

            $totalInvestorProfit = 0.0;

            foreach ($lineData as $line) {
                $line['sale_id'] = $sale->id;
                $saleItem = SaleItem::create($line);

                $breakdown = $line['cost_breakdown'] ?? [];
                $investorProfitForLine = 0.0;

                foreach ($breakdown as $part) {
                    $batch = ProductBatch::find($part['batch_id']);
                    if (! $batch) continue;

                    $take = (int) $part['qty'];

                    /* ═══ سود سرمایه‌گذار ═══ */
                    if ($batch->investor_id && (float) $batch->profit_per_unit_afn > 0) {
                        InvestorProfitEntry::recordProfit(
                            investorId:            $batch->investor_id,
                            investmentId:          null,
                            productAllocationId:   null,
                            saleId:                $sale->id,
                            saleItemId:            $saleItem->id,
                            quantity:              $take,
                            profitPerUnitAfn:      (float) $batch->profit_per_unit_afn,
                            notes:                 "Profit from batch {$batch->batch_code}"
                        );

                        $investorProfitForLine = round(
                            $investorProfitForLine + ((float) $batch->profit_per_unit_afn * $take),
                            2
                        );
                    }

                    /* ═══ کاهش انبار ═══ */
                    $batch->reduceStock($take);

                    Inventory::decrease(
                        branchId:      $sale->branch_id,
                        variantId:     $saleItem->variant_id,
                        qty:           $take,
                        type:          'sale',
                        batchId:       $batch->id,
                        referenceType: Sale::class,
                        referenceId:   $sale->id,
                        notes:         "Sold via {$sale->invoice_number}"
                    );
                }

                $saleItem->update([
                    'investor_profit_afn' => $investorProfitForLine,
                ]);

                $totalInvestorProfit = round($totalInvestorProfit + $investorProfitForLine, 2);
            }

            /* ═══════════════════════════════════════════════════════
             |  ⭐ پرداخت‌ها — با round(2)
             ═══════════════════════════════════════════════════════ */

            $paidAfn    = 0.0;
            $paymentIds = [];

            foreach ($payments as $payment) {
                $amountAfn = round((float) $payment['amount_afn'], 2);

                if ($amountAfn <= 0) continue;

                $salePayment = SalePayment::create([
                    'sale_id'           => $sale->id,
                    'customer_id'       => $sale->customer_id,
                    'amount_afn'        => $amountAfn,
                    'remaining_due_afn' => 0, // موقت — بعداً آپدیت می‌شود
                    'payment_method'    => $payment['payment_method'],
                    'reference_number'  => $payment['reference_number'] ?? null,
                    'notes'             => $payment['notes'] ?? null,
                    'paid_at'           => now(),
                    'received_by'       => Auth::id(),
                ]);

                $paymentIds[] = $salePayment->id;
                $paidAfn      = round($paidAfn + $amountAfn, 2);
            }

            /* ═══════════════════════════════════════════════════════
             |  ⭐ محاسبه نهایی Due — با round(2)
             ═══════════════════════════════════════════════════════ */

            $remainingDueAfn = round(max(0, $grandTotalDueAfn - $paidAfn), 2);

            $paymentStatus = 'unpaid';
            if ($paidAfn >= $grandTotalDueAfn - 0.01) {
                $paymentStatus = 'paid';
            } elseif ($paidAfn > 0) {
                $paymentStatus = 'partial';
            }

            /* ═══ مانده بعد از هر پرداخت، به ترتیب ═══ */
            $runningDue = $grandTotalDueAfn;
            foreach ($paymentIds as $paymentId) {
                $createdPayment = SalePayment::find($paymentId);
                if (! $createdPayment) {
                    continue;
                }
                $runningDue = round(max(0, $runningDue - (float) $createdPayment->amount_afn), 2);
                $createdPayment->update(['remaining_due_afn' => $runningDue]);
            }

            /* ═══════════════════════════════════════════════════════
             |  ⭐ آپدیت نهایی Sale
             ═══════════════════════════════════════════════════════ */

            $sale->update([
                'received_now_afn'          => $paidAfn,
                'remaining_due_afn'         => $remainingDueAfn,
                'due_date'                  => $remainingDueAfn > 0 ? $request->input('due_date') : null,
                'paid_afn'                  => $paidAfn,
                'due_afn'                   => $remainingDueAfn,
                'payment_status'            => $paymentStatus,
                'total_investor_profit_afn' => $totalInvestorProfit,
                'store_profit_afn'          => round($sale->total_profit_afn - $totalInvestorProfit, 2),
            ]);

            /* ═══════════════════════════════════════════════════════
             |  ⭐ آپدیت قرض مشتری + ledger
             ═══════════════════════════════════════════════════════ */

            if ($customer) {
                $ledger = app(CustomerLedgerService::class);

                // ثبت فروش در ledger
                $ledger->recordSale($sale);

                // ثبت هر پرداخت در ledger
                foreach ($paymentIds as $paymentId) {
                    $payment = SalePayment::find($paymentId);
                    if ($payment) {
                        $ledger->recordPayment($payment);
                    }
                }

                // آپدیت customers.current_due_afn + last_sale_at
                $customer->update([
                    'current_due_afn' => $remainingDueAfn,
                    'last_sale_at'    => now(),
                ]);
            }

            return $sale;
        });

        try {
            app(SaleNotifier::class)->notify($sale);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()
            ->route('sales.show', $sale)
            ->with('success', "Sale \"{$sale->invoice_number}\" created successfully.");
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(Sale $sale): Response
    {
        if ($sale->trash) abort(404);

        $sale->load([
            'branch:id,name,code',
            'customer:id,name,phone,address,current_due_afn,credit_limit_afn',
            'creator:id,name,email',
            'items.variant.product:id,name',
            'items.variant.product.brand:id,name',
            'items.batch:id,batch_code,investor_id',
            'items.batch.investor:id,full_name',
            'payments.receiver:id,name',
            'payments.customer:id,name,phone',
            'receipts',
        ]);

        return Inertia::render('sales/show', ['sale' => $sale]);
    }

    /* =========================================================
     |  RECEIPT (نمایش رسید)
     ========================================================= */

    public function receipt(Sale $sale): Response
    {
        if ($sale->trash) abort(404);

        $sale->load([
            'branch:id,name,code,address,phone',
            'customer:id,name,phone,current_due_afn',
            'creator:id,name',
            'items.variant.product:id,name',
            'items.variant.product.brand:id,name',
            'payments.receiver:id,name',
            'payments.customer:id,name,phone',
        ]);

        return Inertia::render('sales/receipt', [
            'sale'    => $sale,
            'company' => \App\Models\AppSetting::bill(),
            'printedAt' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    /* =========================================================
     |  EDIT
     ========================================================= */

    public function edit(Sale $sale): Response
    {
        if ($sale->trash) abort(404);

        if ($sale->status === 'completed') {
            return redirect()
                ->route('sales.show', $sale->id)
                ->with('error', 'Completed sales cannot be edited.');
        }

        $sale->load(['items', 'payments']);

        return Inertia::render('sales/edit', [
            'sale'      => $sale,
            'branches'  => Branch::notTrashed()->active()->visibleTo(Auth::user())->select('id', 'name', 'code')->get(),
            'customers' => Customer::notTrashed()->active()
                ->select('id', 'name', 'phone', 'current_due_afn', 'credit_limit_afn')
                ->orderBy('name')
                ->get(),
            'variants'  => ProductVariant::query()
                ->notTrashed()
                ->with(['product:id,name,brand_id', 'product.brand:id,name'])
                ->select(
                    'id', 'product_id', 'name', 'volume_ml', 'concentration',
                    'sale_price_afn', 'wholesale_price_afn', 'wholesale_min_qty', 'purchase_price_afn'
                )
                ->get(),
        ]);
    }

    /* =========================================================
     |  UPDATE
     ========================================================= */

    public function update(UpdateSaleRequest $request, Sale $sale): RedirectResponse
    {
        if ($sale->status === 'completed') {
            return back()->with('error', 'Completed sales cannot be edited.');
        }

        DB::transaction(function () use ($request, $sale) {
            $data  = $request->validated();
            $items = $data['items'];
            unset($data['items'], $data['payments']);

            if (! empty($data['customer_id']) && is_numeric($data['customer_id'])) {
                $customer = Customer::find((int) $data['customer_id']);
                if ($customer) {
                    $data['customer_name_snapshot']  = $customer->name;
                    $data['customer_phone_snapshot'] = $customer->phone;
                }
            }

            $branchId = (int) ($data['branch_id'] ?? $sale->branch_id);

            $variantIds = collect($items)->pluck('variant_id')->unique()->all();
            $variants   = ProductVariant::with([
                'product:id,name',
                'barcodes' => fn ($q) => $q->orderByDesc('is_primary'),
            ])->whereIn('id', $variantIds)->get()->keyBy('id');

            $subtotalAfn  = 0.0;
            $totalCostAfn = 0.0;
            $lineData     = [];

            foreach ($items as $item) {
                $prepared = $this->prepareSaleLine($branchId, $item, $variants);

                $subtotalAfn  = round($subtotalAfn + $prepared['line_subtotal_afn'], 2);
                $totalCostAfn = round($totalCostAfn + $prepared['line_cost_afn'], 2);

                $lineData[] = $prepared['line'];
            }

            $discountAfn = round((float) ($data['discount_afn'] ?? 0), 2);

            $data['subtotal_afn']     = $subtotalAfn;
            $data['discount_afn']     = $discountAfn;
            $data['total_afn']        = round($subtotalAfn - $discountAfn, 2);
            $data['total_cost_afn']   = $totalCostAfn;
            $data['total_profit_afn'] = round($data['total_afn'] - $totalCostAfn, 2);

            $sale->update($data);

            SaleItem::where('sale_id', $sale->id)->delete();

            foreach ($lineData as $line) {
                $line['sale_id'] = $sale->id;
                SaleItem::create($line);
            }
        });

        return redirect()
            ->route('sales.show', $sale->id)
            ->with('success', "Sale \"{$sale->invoice_number}\" updated successfully.");
    }

    /* =========================================================
     |  DESTROY / RESTORE / FORCE DELETE / TRASH
     ========================================================= */

    public function destroy(Sale $sale): RedirectResponse
    {
        if ($sale->status === 'completed') {
            return back()->with('error', 'Completed sales cannot be deleted.');
        }

        $invoice = $sale->invoice_number;
        $sale->update(['trash' => true]);

        return redirect()->route('sales.index')
            ->with('success', "Sale \"{$invoice}\" was moved to trash.");
    }

    public function restore(int $id): RedirectResponse
    {
        $sale = Sale::onlyTrashed()->findOrFail($id);
        $sale->update(['trash' => false]);

        return redirect()->route('sales.index')
            ->with('success', "Sale \"{$sale->invoice_number}\" was restored.");
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $sale    = Sale::onlyTrashed()->findOrFail($id);
        $invoice = $sale->invoice_number;
        $sale->forceDelete();

        return redirect()->route('sales.index')
            ->with('success', "Sale \"{$invoice}\" was permanently deleted.");
    }

    public function trash(): Response
    {
        $sales = Sale::onlyTrashed()
            ->with(['branch:id,name', 'customer:id,name', 'creator:id,name'])
            ->orderByDesc('updated_at')
            ->paginate(15);

        return Inertia::render('sales/trash', ['sales' => $sales]);
    }

    /**
     * یک ردیف فروش را با snapshot، بچ FIFO و مبالغ ارزی (اگر بچ ارز داشته باشد) می‌سازد.
     *
     * @param  \Illuminate\Support\Collection<int, ProductVariant>  $variants
     * @return array{line_subtotal_afn: float, line_cost_afn: float, line: array<string, mixed>}
     */
    private function prepareSaleLine(int $branchId, array $item, $variants): array
    {
        $variantId = (int) $item['variant_id'];
        $qty       = (int) $item['quantity'];
        $priceAfn  = round((float) $item['sale_price_afn'], 2);
        $discAfn   = round((float) ($item['discount_afn'] ?? 0), 2);

        $lineSubtotalAfn = round($priceAfn * $qty, 2);
        $lineTotalAfn    = round($lineSubtotalAfn - $discAfn, 2);

        $fifo        = ProductBatch::calculateFifoCost($branchId, $variantId, $qty);
        $lineCostAfn = round((float) $fifo['cost_afn'], 2);
        $breakdown   = $fifo['breakdown'];
        $lineProfitAfn = round($lineTotalAfn - $lineCostAfn, 2);

        $variant     = $variants->get($variantId);
        $productName = null;
        $barcode     = null;
        $sku         = null;

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
        $batchId  = $batchIds->count() === 1 ? (int) $batchIds->first() : null;

        return [
            'line_subtotal_afn' => $lineSubtotalAfn,
            'line_cost_afn'     => $lineCostAfn,
            'line'              => [
                'variant_id'            => $variantId,
                'batch_id'              => $batchId,
                'product_name_snapshot' => $productName,
                'sku_snapshot'          => $sku,
                'barcode_snapshot'      => $barcode,
                'quantity'              => $qty,
                'cost_price_afn'        => round($lineCostAfn / max($qty, 1), 2),
                'sale_price_afn'        => $priceAfn,
                'discount_afn'          => $discAfn,
                'line_total_afn'        => $lineTotalAfn,
                'store_profit_afn'      => $lineProfitAfn,
                'investor_profit_afn'   => 0,
                'cost_breakdown'        => $breakdown,
            ],
        ];
    }

    /* =========================================================
     |  LOOKUP BARCODE
     ========================================================= */

    public function lookupBarcode(Request $request): JsonResponse
    {
        $barcode = trim($request->query('barcode', ''));

        if (empty($barcode)) {
            return response()->json(['found' => false, 'message' => 'Barcode is empty']);
        }

        $productBarcode = ProductBarcode::query()
            ->where('barcode', $barcode)
            ->where('status', 'active')
            ->with([
                'variant:id,product_id,name,volume_ml,concentration,sale_price_afn,wholesale_price_afn,wholesale_min_qty,purchase_price_afn',
                'variant.product:id,name,brand_id',
                'variant.product.brand:id,name',
            ])
            ->first();

        if (! $productBarcode || ! $productBarcode->variant) {
            return response()->json([
                'found'   => false,
                'message' => "Barcode \"{$barcode}\" not found",
            ]);
        }

        $variant = $productBarcode->variant;

        return response()->json([
            'found'   => true,
            'variant' => [
                'id'                  => $variant->id,
                'name'                => $variant->name,
                'volume_ml'           => $variant->volume_ml,
                'concentration'       => $variant->concentration,
                'sale_price_afn'      => (float) $variant->sale_price_afn,
                'wholesale_price_afn' => (float) $variant->wholesale_price_afn,
                'wholesale_min_qty'   => (int) $variant->wholesale_min_qty,
                'purchase_price_afn'  => (float) $variant->purchase_price_afn,
                'product'             => [
                    'id'    => $variant->product?->id,
                    'name'  => $variant->product?->name,
                    'brand' => $variant->product?->brand
                        ? ['id' => $variant->product->brand->id, 'name' => $variant->product->brand->name]
                        : null,
                ],
                'barcode' => $productBarcode->barcode,
            ],
        ]);
    }

    /* =========================================================
     |  PREVIEW COST
     ========================================================= */

    public function previewCost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id'          => ['required', 'exists:branches,id'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
        ]);

        $result = [];

        foreach ($validated['items'] as $item) {
            try {
                $fifo = ProductBatch::calculateFifoCost(
                    branchId:  (int) $validated['branch_id'],
                    variantId: (int) $item['variant_id'],
                    qty:       (int) $item['quantity'],
                );

                $result[] = [
                    'variant_id'    => $item['variant_id'],
                    'available'     => true,
                    'cost_afn'      => $fifo['cost_afn'],
                    'cost_per_unit' => $fifo['cost_per_unit_afn'],
                    'breakdown'     => $fifo['breakdown'],
                ];
            } catch (\Exception $e) {
                $result[] = [
                    'variant_id' => $item['variant_id'],
                    'available'  => false,
                    'message'    => $e->getMessage(),
                ];
            }
        }

        return response()->json(['items' => $result]);
    }

    /* =========================================================
     |  ADD PAYMENT — پرداخت مرحله‌ای قرض
     ========================================================= */

    public function addPayment(Request $request, Sale $sale): RedirectResponse
    {
        $validated = $request->validate([
            'amount_afn'       => ['required', 'numeric', 'min:0.01'],
            'payment_method'   => ['required', 'in:cash,card,bank,mobile,credit,other'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes'            => ['nullable', 'string'],
        ]);

        $receipt = DB::transaction(function () use ($validated, $sale) {
            $amountAfn = round((float) $validated['amount_afn'], 2);

            $customer = $sale->customer_id
                ? Customer::whereKey($sale->customer_id)->lockForUpdate()->first()
                : null;

            $accountDue = $customer
                ? round((float) $customer->current_due_afn, 2)
                : round((float) $sale->remaining_due_afn, 2);

            if ($amountAfn > $accountDue + 0.01) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount_afn' => 'The payment is larger than the current due.',
                ]);
            }

            $newCustomerDue = round(max(0, $accountDue - $amountAfn), 2);
            $newSaleRemaining = round(max(0, (float) $sale->remaining_due_afn - $amountAfn), 2);
            $balanceAfter = $customer ? $newCustomerDue : $newSaleRemaining;

            $salePayment = SalePayment::create([
                'sale_id'           => $sale->id,
                'customer_id'       => $sale->customer_id,
                'amount_afn'        => $amountAfn,
                'remaining_due_afn' => $balanceAfter,
                'payment_method'    => $validated['payment_method'],
                'reference_number'  => $validated['reference_number'] ?? null,
                'notes'             => $validated['notes'] ?? null,
                'paid_at'           => now(),
                'received_by'       => Auth::id(),
            ]);

            $newPaidAfn = round((float) $sale->paid_afn + $amountAfn, 2);
            $paymentStatus = $newSaleRemaining <= 0.01 ? 'paid' : 'partial';

            $sale->update([
                'paid_afn'          => $newPaidAfn,
                'due_afn'           => $newSaleRemaining,
                'remaining_due_afn' => $newSaleRemaining,
                'payment_status'    => $paymentStatus,
            ]);

            if ($customer) {
                app(CustomerLedgerService::class)->recordPayment($salePayment);

                $customer->update([
                    'current_due_afn' => $newCustomerDue,
                ]);

                $latest = $customer->sales()->notTrashed()->latest('id')->first();
                if ($latest && $latest->id !== $sale->id) {
                    $latest->update([
                        'remaining_due_afn' => $newCustomerDue,
                        'due_afn'           => $newCustomerDue,
                        'payment_status'    => $newCustomerDue <= 0.01 ? 'paid' : 'partial',
                    ]);
                }
            }

            return Receipt::create([
                'customer_id'       => $sale->customer_id,
                'sale_id'           => $sale->id,
                'sale_payment_id'   => $salePayment->id,
                'receipt_number'    => Receipt::generateReceiptNumber(),
                'amount_afn'        => $amountAfn,
                'remaining_due_afn' => $balanceAfter,
                'receipt_type'      => 'payment_receipt',
                'created_by'        => Auth::id(),
            ]);
        });

        return redirect()
            ->route('receipts.print', $receipt)
            ->with('success', 'Payment recorded.');
    }

    /* =========================================================
     |  PRINT RECEIPT
     ========================================================= */

    public function printReceipt(Request $request, Sale $sale): RedirectResponse
    {
        DB::transaction(function () use ($request, $sale) {
            Receipt::create([
                'customer_id'       => $sale->customer_id,
                'sale_id'           => $sale->id,
                'sale_payment_id'   => null,
                'receipt_number'    => Receipt::generateReceiptNumber(),
                'amount_afn'        => $sale->received_now_afn,
                'remaining_due_afn' => $sale->remaining_due_afn,
                'receipt_type'      => $request->input('receipt_type', 'sale_invoice'),
                'created_by'        => Auth::id(),
            ]);

            $sale->markAsPrinted(Auth::id());
        });

        return back()->with('success', 'Receipt printed successfully.');
    }
}