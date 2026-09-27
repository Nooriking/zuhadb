<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddSalePaymentRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UpdateSaleRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\ExchangeRateHistory;
use App\Models\ProductBatch;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Models\Receipt;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $sales,
    ) {}

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
            'avg_usd_rate' => round(ExchangeRateHistory::averageThisMonth(), 2),
            'latest_usd_rate' => (float) (ExchangeRateHistory::latestRate()?->usd_to_afn ?? 0),
        ];

        $saleDates = $sales->getCollection()
            ->map(fn (Sale $sale) => optional($sale->sale_date)->toDateString())
            ->filter()
            ->unique()
            ->values();

        $ratesByDate = ExchangeRateHistory::query()
            ->whereIn('rate_date', $saleDates)
            ->get(['rate_date', 'usd_to_afn'])
            ->mapWithKeys(fn ($row) => [
                Carbon::parse($row->rate_date)->toDateString() => round((float) $row->usd_to_afn, 2),
            ]);

        // Fallback: nearest earlier rate for dates without an exact entry
        $missingDates = $saleDates->reject(fn ($date) => $ratesByDate->has($date));
        foreach ($missingDates as $date) {
            $fallback = ExchangeRateHistory::rateOnDate($date);
            if ($fallback) {
                $ratesByDate->put($date, round((float) $fallback->usd_to_afn, 2));
            }
        }

        $sales->getCollection()->each(function (Sale $sale) use ($ratesByDate) {
            $date = optional($sale->sale_date)->toDateString();
            $sale->setAttribute('exchange_rate', $date ? $ratesByDate->get($date) : null);
        });

        $monthlyRates = ExchangeRateHistory::query()
            ->selectRaw('YEAR(rate_date) as year, MONTH(rate_date) as month, ROUND(AVG(usd_to_afn), 2) as average_rate, COUNT(*) as days')
            ->groupByRaw('YEAR(rate_date), MONTH(rate_date)')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(6)
            ->get();

        return Inertia::render('sales/index', [
            'sales'     => $sales,
            'monthlyRates' => $monthlyRates,
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
        $this->authorize('create', Sale::class);

        $sale = $this->sales->createSale($request->validated(), $request->user());

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
                $prepared = $this->sales->prepareSaleLine($branchId, $item, $variants);

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

    public function addPayment(AddSalePaymentRequest $request, Sale $sale): RedirectResponse
    {
        $this->authorize('create', Sale::class);

        $receipt = $this->sales->addPayment($sale, $request->validated(), $request->user());

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