<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddSalePaymentRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UpdateSaleRequest;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\ExchangeRateHistory;
use App\Models\ProductBatch;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Models\Receipt;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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

        $missingDates = $saleDates->reject(fn ($date) => $ratesByDate->has($date));
        foreach ($missingDates as $date) {
            $fallback = ExchangeRateHistory::rateOnDate($date);
            if ($fallback) {
                $ratesByDate->put($date, round((float) $fallback->usd_to_afn, 2));
            }
        }

        $sales->getCollection()->each(function (Sale $sale) use ($ratesByDate) {
            if ($sale->usd_to_ledger_rate !== null && (float) $sale->usd_to_ledger_rate > 0) {
                $sale->setAttribute('exchange_rate', round((float) $sale->usd_to_ledger_rate, 4));
                $sale->setAttribute('exchange_rate_kind', $sale->fx_rate_kind ?: 'catalog');

                return;
            }

            $date = optional($sale->sale_date)->toDateString();
            $reference = $date ? $ratesByDate->get($date) : null;
            $sale->setAttribute('exchange_rate', $reference);
            $sale->setAttribute('exchange_rate_kind', $reference ? 'daily_reference' : null);
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
            'canEditCompleted' => $user?->isSuperAdmin() ?? false,
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
                    'product:id,name,brand_id,base_unit_name,pack_unit_name,units_per_pack',
                    'product.brand:id,name',
                ])
                ->select(
                    'id', 'product_id', 'name', 'volume_ml', 'concentration',
                    'sale_price_afn', 'wholesale_price_afn', 'wholesale_min_qty', 'purchase_price_afn'
                )
                ->get(),
            'nextNumber' => Sale::generateInvoiceNumber(),
            'supplierCredits' => \App\Models\Product::supplierCredits(),
            'currencies' => Currency::formOptions(),
        ]);
    }

    /* =========================================================
     |  STORE
     ========================================================= */

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        foreach ($data['items'] as $index => $item) {
            $variant = ProductVariant::query()->with('product')->find($item['variant_id']);
            $product = $variant?->product;
            if ($product) {
                $data['items'][$index]['quantity'] = $product->quantityToBaseUnits(
                    (int) $item['quantity'],
                    (string) ($item['quantity_unit'] ?? 'base')
                );
            }
            unset($data['items'][$index]['quantity_unit']);
        }

        $sale = $this->sales->createSale($data, $request->user());

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

        $user = Auth::user();

        return Inertia::render('sales/show', [
            'sale' => $sale,
            'canEdit' => $sale->status !== 'cancelled'
                && ($sale->status !== 'completed' || ($user?->isSuperAdmin() ?? false)),
            'canEditCompleted' => $user?->isSuperAdmin() ?? false,
        ]);
    }

    /* =========================================================
     |  RECEIPT
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

    public function edit(Sale $sale): Response|RedirectResponse
    {
        if ($sale->trash) abort(404);

        $user = Auth::user();

        if ($sale->status === 'cancelled') {
            return redirect()
                ->route('sales.show', $sale->id)
                ->with('error', 'Cancelled sales cannot be edited.');
        }

        if ($sale->status === 'completed' && ! ($user?->isSuperAdmin() ?? false)) {
            return redirect()
                ->route('sales.show', $sale->id)
                ->with('error', 'Completed sales cannot be edited.');
        }

        $sale->load(['items', 'payments']);

        return Inertia::render('sales/edit', [
            'sale'      => $sale,
            'branches'  => Branch::notTrashed()->active()->visibleTo($user)->select('id', 'name', 'code')->get(),
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
            'canEditCompleted' => $user?->isSuperAdmin() ?? false,
        ]);
    }

    /* =========================================================
     |  UPDATE
     |  âœ… Ù†Ø§Ù… ÙÛŒÙ„Ø¯ ØµØ­ÛŒØ­: remaining_quantity
     ========================================================= */

    public function update(UpdateSaleRequest $request, Sale $sale): RedirectResponse
    {
        $user = Auth::user();

        if ($sale->status === 'completed' && ! ($user?->isSuperAdmin() ?? false)) {
            return back()->with('error', 'Completed sales cannot be edited.');
        }

        if ($sale->status === 'cancelled') {
            return back()->with('error', 'Cancelled sales cannot be edited.');
        }

        DB::transaction(function () use ($request, $sale) {
            $data  = $request->validated();
            $items = $data['items'];
            unset($data['items'], $data['payments']);

            // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
            //  âœ… 1. REVERT: Ø¨Ø±Ú¯Ø±Ø¯Ø§Ù†Ø¯Ù† Ø§Ø«Ø± ÙØ±ÙˆØ´ Ù‚Ø¨Ù„ÛŒ
            // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

            $sale->loadMissing(['items.batch']);

            $qtyColumn = self::detectBatchQtyColumn();

            if ($qtyColumn) {
                foreach ($sale->items as $item) {
                    if (! $item->batch_id) {
                        continue;
                    }

                    $batch = ProductBatch::lockForUpdate()->find($item->batch_id);

                    if ($batch) {
                        $batch->increment($qtyColumn, (int) $item->quantity);
                        Log::info("Revert: batch #{$batch->id} +{$item->quantity} (column: {$qtyColumn})");
                    }
                }
            } else {
                Log::warning('SaleController@update: Ø³ØªÙˆÙ† Ù…ÙˆØ¬ÙˆØ¯ÛŒ Ø¯Ø± product_batches Ù¾ÛŒØ¯Ø§ Ù†Ø´Ø¯.');
            }

            // Ø¨Ø±Ú¯Ø±Ø¯Ø§Ù†Ø¯Ù† Ù‚Ø±Ø¶ Ù…Ø´ØªØ±ÛŒ Ù‚Ø¨Ù„ÛŒ
            if ($sale->customer_id && (float) $sale->remaining_due_afn > 0) {
                $oldCustomer = Customer::lockForUpdate()->find($sale->customer_id);
                if ($oldCustomer) {
                    $oldCustomer->decrement('current_due_afn', (float) $sale->remaining_due_afn);
                }
            }

            // Ø­Ø°Ù SaleItem Ù‡Ø§ÛŒ Ù‚Ø¨Ù„ÛŒ
            SaleItem::where('sale_id', $sale->id)->delete();

            // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
            //  âœ… 2. RE-APPLY
            // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

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

            foreach ($lineData as $line) {
                $line['sale_id'] = $sale->id;
                SaleItem::create($line);
            }

            // Ø§Ø¹Ù…Ø§Ù„ Ù‚Ø±Ø¶ Ø¬Ø¯ÛŒØ¯ Ù…Ø´ØªØ±ÛŒ
            if ($sale->customer_id && (float) $sale->remaining_due_afn > 0) {
                $newCustomer = Customer::lockForUpdate()->find($sale->customer_id);
                if ($newCustomer) {
                    $newCustomer->increment('current_due_afn', (float) $sale->remaining_due_afn);
                }
            }
        });

        return redirect()
            ->route('sales.show', $sale->id)
            ->with('success', "Sale \"{$sale->invoice_number}\" updated successfully.");
    }

    /* =========================================================
     |  ðŸ†˜ ØªØ´Ø®ÛŒØµ Ø®ÙˆØ¯Ú©Ø§Ø± Ù†Ø§Ù… ÙÛŒÙ„Ø¯ Ù…ÙˆØ¬ÙˆØ¯ÛŒ
     |  âœ… Ù†Ø§Ù… ØµØ­ÛŒØ­: remaining_quantity
     ========================================================= */

    private static ?string $batchQtyColumnCache = null;
    private static bool $batchQtyColumnChecked = false;

    /**
     * Ù†Ø§Ù… ÙÛŒÙ„Ø¯ Ù…ÙˆØ¬ÙˆØ¯ÛŒ Ø¯Ø± product_batches Ø±Ø§ Ø¨Ø±Ù…ÛŒâ€ŒÚ¯Ø±Ø¯Ø§Ù†Ø¯.
     *
     * âš ï¸ Ù†Ú©ØªÙ‡ Ù…Ù‡Ù…: 'quantity' Ø±Ø§ Ø¯Ø± Ù„ÛŒØ³Øª Ù†Ù…ÛŒâ€ŒÚ¯Ø°Ø§Ø±ÛŒÙ… Ú†ÙˆÙ† Ù…Ù‚Ø¯Ø§Ø± Ø§ÙˆÙ„ÛŒÙ‡ Ø§Ø³ØªØŒ
     * Ù†Ù‡ Ù…ÙˆØ¬ÙˆØ¯ÛŒ ÙØ¹Ù„ÛŒ. Ù…ÙˆØ¬ÙˆØ¯ÛŒ ÙØ¹Ù„ÛŒ 'remaining_quantity' Ø§Ø³Øª.
     */
    private static function detectBatchQtyColumn(): ?string
    {
        if (self::$batchQtyColumnChecked) {
            return self::$batchQtyColumnCache;
        }

        self::$batchQtyColumnChecked = true;

        try {
            $columns = Schema::getColumnListing('product_batches');

            // âœ… 'remaining_quantity' Ø§ÙˆÙ„ÙˆÛŒØª Ø§ÙˆÙ„ â€” Ù†Ø§Ù… ÙˆØ§Ù‚Ø¹ÛŒ Ø¯Ø± Ù¾Ø±ÙˆÚ˜Ù‡
            // âš ï¸ 'quantity' Ø­Ø°Ù Ø´Ø¯ Ú†ÙˆÙ† Ù…Ù‚Ø¯Ø§Ø± Ø§ÙˆÙ„ÛŒÙ‡ Ø§Ø³Øª
            $candidates = [
                'remaining_quantity',
                'remaining_qty',
                'available_quantity',
                'available_qty',
                'current_quantity',
                'current_qty',
                'quantity_remaining',
                'remaining',
                'stock',
                'quantity_in_stock',
                'in_stock',
            ];

            foreach ($candidates as $col) {
                if (in_array($col, $columns, true)) {
                    self::$batchQtyColumnCache = $col;
                    Log::info("SaleController: Ø§Ø² Ø³ØªÙˆÙ† '{$col}' Ø¨Ø±Ø§ÛŒ Ù…ÙˆØ¬ÙˆØ¯ÛŒ product_batches Ø§Ø³ØªÙØ§Ø¯Ù‡ Ù…ÛŒâ€ŒØ´ÙˆØ¯.");
                    return $col;
                }
            }

            Log::error(
                'SaleController: Ù‡ÛŒÚ† Ø³ØªÙˆÙ† Ù…ÙˆØ¬ÙˆØ¯ÛŒ Ø¯Ø± product_batches Ù¾ÛŒØ¯Ø§ Ù†Ø´Ø¯. Ø³ØªÙˆÙ†â€ŒÙ‡Ø§ÛŒ Ù…ÙˆØ¬ÙˆØ¯: '
                . implode(', ', $columns)
            );
        } catch (\Throwable $e) {
            Log::error('SaleController@detectBatchQtyColumn: ' . $e->getMessage());
        }

        self::$batchQtyColumnCache = null;
        return null;
    }

    /* =========================================================
     |  DESTROY / RESTORE / FORCE DELETE / TRASH
     ========================================================= */

    public function destroy(Sale $sale): RedirectResponse
    {
        $user = Auth::user();

        if ($sale->status === 'completed' && ! ($user?->isSuperAdmin() ?? false)) {
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
     |  ADD PAYMENT
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

    /* =========================================================
     |  SALES REPORT
     ========================================================= */

    public function report(Request $request): Response
    {
        $search        = $request->query('search');
        $branchId      = $request->query('branch_id');
        $customerId    = $request->query('customer_id');
        $salespersonId = $request->query('salesperson_id');
        $paymentStatus = $request->query('payment_status');
        $status        = $request->query('status') ?: 'completed';
        $onlyDue       = $request->boolean('only_due');
        $dateFrom      = $request->query('date_from');
        $dateTo        = $request->query('date_to');
        $user          = $request->user();

        if ($user?->isBranchScoped()) {
            $branchId = $user->branch_id;
        }

        $buildBase = function () use ($search, $branchId, $customerId, $salespersonId, $paymentStatus, $status, $onlyDue, $dateFrom, $dateTo) {
            return Sale::query()
                ->where('sales.trash', false)
                ->whereNull('sales.deleted_at')
                ->when($search, function ($q, $s) {
                    $digits = preg_replace('/\D+/', '', (string) $s);
                    $q->where(function ($q) use ($s, $digits) {
                        $q->where('sales.invoice_number', 'like', "%{$s}%")
                          ->orWhere('sales.receipt_no', 'like', "%{$s}%")
                          ->orWhere('sales.customer_phone_snapshot', 'like', "%{$s}%")
                          ->orWhereHas('customer', function ($customer) use ($s, $digits) {
                              $customer->where('name', 'like', "%{$s}%")
                                  ->orWhere('phone', 'like', "%{$s}%");
                              if ($digits !== '') {
                                  $customer->orWhere('phone', 'like', "%{$digits}%");
                              }
                          });
                    });
                })
                ->when($branchId, fn ($q, $v) => $q->where('sales.branch_id', $v))
                ->when($customerId, fn ($q, $v) => $q->where('sales.customer_id', $v))
                ->when($salespersonId, fn ($q, $v) => $q->where('sales.created_by', $v))
                ->when($paymentStatus, fn ($q, $v) => $q->where('sales.payment_status', $v))
                ->when($status, fn ($q, $v) => $q->where('sales.status', $v))
                ->when($onlyDue, fn ($q) => $q->where('sales.remaining_due_afn', '>', 0))
                ->when($dateFrom, fn ($q, $v) => $q->whereDate('sales.sale_date', '>=', $v))
                ->when($dateTo, fn ($q, $v) => $q->whereDate('sales.sale_date', '<=', $v));
        };

        $returnsBase = function () use ($branchId, $customerId, $dateFrom, $dateTo) {
            return SaleReturn::query()
                ->notTrashed()
                ->completed()
                ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
                ->when($customerId, fn ($q, $v) => $q->where('customer_id', $v))
                ->when($dateFrom, fn ($q, $v) => $q->whereDate('return_date', '>=', $v))
                ->when($dateTo, fn ($q, $v) => $q->whereDate('return_date', '<=', $v));
        };

        $sales = $buildBase()
            ->with([
                'branch:id,name,code',
                'customer:id,name,phone',
                'creator:id,name',
                'returns' => fn ($q) => $q->notTrashed()->completed()->select('id', 'sale_id', 'total_afn', 'return_number'),
                'returns.items' => fn ($q) => $q->select('id', 'sale_return_id', 'expense_bearer', 'expense_amount_afn', 'condition'),
            ])
            ->withCount('items')
            ->withSum([
                'returns as return_amount_afn' => fn ($q) => $q->notTrashed()->completed(),
            ], 'total_afn')
            ->orderByDesc('sales.sale_date')
            ->orderByDesc('sales.id')
            ->paginate(25)
            ->withQueryString()
            ->through(function (Sale $sale) {
                $returnAmount = (float) ($sale->return_amount_afn ?? 0);
                $net = (float) $sale->total_afn;
                $gross = $net + $returnAmount;
                $types = $sale->returns
                    ->flatMap->items
                    ->filter(fn ($item) => $item->isUsed() || (float) $item->expense_amount_afn > 0)
                    ->pluck('expense_bearer')
                    ->unique()
                    ->values();

                return [
                    'id'                => $sale->id,
                    'invoice_number'    => $sale->invoice_number,
                    'sale_date'         => $sale->sale_date,
                    'items_count'       => $sale->items_count,
                    'amount_afn'        => round($gross, 2),
                    'return_amount_afn' => round($returnAmount, 2),
                    'net_afn'           => round($net, 2),
                    'return_type'       => $types->count() > 1 ? 'mixed' : ($types->first() ?: null),
                    'payment_status'    => $sale->payment_status,
                    'status'            => $sale->status,
                    'branch'            => $sale->branch,
                    'customer'          => $sale->customer,
                    'creator'           => $sale->creator,
                ];
            });

        $summaryRow = $buildBase()
            ->selectRaw('
                COALESCE(SUM(sales.total_afn), 0) as net_sales,
                COALESCE(SUM(sales.paid_afn), 0) as total_paid,
                COALESCE(SUM(sales.remaining_due_afn), 0) as total_due,
                COALESCE(SUM(sales.total_cost_afn), 0) as total_cost,
                COALESCE(SUM(sales.total_profit_afn), 0) as total_profit,
                COALESCE(SUM(sales.store_profit_afn), 0) as store_profit,
                COALESCE(SUM(sales.total_investor_profit_afn), 0) as investor_profit,
                COUNT(*) as count,
                COUNT(DISTINCT sales.customer_id) as customer_count
            ')
            ->first();

        $totalReturns = (float) $returnsBase()->sum('total_afn');
        $netSales = (float) $summaryRow->net_sales;
        $grossSales = $netSales + $totalReturns;

        $totalItems = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->when($branchId, fn ($q, $v) => $q->where('sales.branch_id', $v))
            ->when($customerId, fn ($q, $v) => $q->where('sales.customer_id', $v))
            ->when($salespersonId, fn ($q, $v) => $q->where('sales.created_by', $v))
            ->when($paymentStatus, fn ($q, $v) => $q->where('sales.payment_status', $v))
            ->when($status, fn ($q, $v) => $q->where('sales.status', $v))
            ->when($onlyDue, fn ($q) => $q->where('sales.remaining_due_afn', '>', 0))
            ->when($dateFrom, fn ($q, $v) => $q->whereDate('sales.sale_date', '>=', $v))
            ->when($dateTo, fn ($q, $v) => $q->whereDate('sales.sale_date', '<=', $v))
            ->sum('sale_items.quantity');

        $summary = [
            'gross_sales'     => round($grossSales, 2),
            'total_returns'   => round($totalReturns, 2),
            'net_sales'       => round($netSales, 2),
            'total_sales'     => round($netSales, 2),
            'total_paid'      => (float) $summaryRow->total_paid,
            'total_due'       => (float) $summaryRow->total_due,
            'total_cost'      => (float) $summaryRow->total_cost,
            'total_profit'    => (float) $summaryRow->total_profit,
            'store_profit'    => (float) $summaryRow->store_profit,
            'investor_profit' => (float) $summaryRow->investor_profit,
            'count'           => (int) $summaryRow->count,
            'customer_count'  => (int) $summaryRow->customer_count,
            'total_items'     => (int) $totalItems,
            'avg_sale'        => $summaryRow->count > 0
                ? round($netSales / (int) $summaryRow->count, 2)
                : 0,
        ];

        $byBranch = $buildBase()
            ->join('branches', 'sales.branch_id', '=', 'branches.id')
            ->selectRaw('
                sales.branch_id,
                branches.name as branch_name,
                COALESCE(SUM(sales.total_afn), 0) as total_sales,
                COALESCE(SUM(sales.total_profit_afn), 0) as total_profit,
                COALESCE(SUM(sales.remaining_due_afn), 0) as total_due,
                COUNT(*) as count
            ')
            ->groupBy('sales.branch_id', 'branches.name')
            ->orderByDesc('total_sales')
            ->get()
            ->map(fn ($row) => [
                'branch_id'    => $row->branch_id,
                'branch_name'  => $row->branch_name,
                'total_sales'  => (float) $row->total_sales,
                'total_profit' => (float) $row->total_profit,
                'total_due'    => (float) $row->total_due,
                'count'        => (int) $row->count,
            ]);

        $salesByDay = $buildBase()
            ->selectRaw('
                DATE(sales.sale_date) as date,
                COALESCE(SUM(sales.total_afn), 0) as net_sales,
                COUNT(*) as count
            ')
            ->groupByRaw('DATE(sales.sale_date)')
            ->orderBy('date')
            ->limit(90)
            ->get()
            ->keyBy('date');

        $returnsByDay = $returnsBase()
            ->selectRaw('DATE(return_date) as date, COALESCE(SUM(total_afn), 0) as total_returns')
            ->groupByRaw('DATE(return_date)')
            ->pluck('total_returns', 'date');

        $allDates = $salesByDay->keys()->merge($returnsByDay->keys())->unique()->sort()->values();

        $byDay = $allDates->map(function ($date) use ($salesByDay, $returnsByDay) {
            $net = (float) ($salesByDay[$date]->net_sales ?? 0);
            $returns = (float) ($returnsByDay[$date] ?? 0);

            return [
                'date'          => $date,
                'net_sales'     => $net,
                'total_returns' => $returns,
                'gross_sales'   => $net + $returns,
                'total_sales'   => $net,
                'total_profit'  => 0,
                'count'         => (int) ($salesByDay[$date]->count ?? 0),
            ];
        })->values();

        $byCustomer = $buildBase()
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->selectRaw('
                sales.customer_id,
                COALESCE(customers.name, "Walk-in") as customer_name,
                COALESCE(SUM(sales.total_afn), 0) as total_sales,
                COALESCE(SUM(sales.remaining_due_afn), 0) as total_due,
                COUNT(*) as count
            ')
            ->groupBy('sales.customer_id', 'customers.name')
            ->orderByDesc('total_sales')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'customer_id'   => $row->customer_id,
                'customer_name' => $row->customer_name,
                'total_sales'   => (float) $row->total_sales,
                'total_due'     => (float) $row->total_due,
                'count'         => (int) $row->count,
            ]);

        $byProduct = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_variants', 'sale_items.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->when($branchId, fn ($q, $v) => $q->where('sales.branch_id', $v))
            ->when($customerId, fn ($q, $v) => $q->where('sales.customer_id', $v))
            ->when($salespersonId, fn ($q, $v) => $q->where('sales.created_by', $v))
            ->when($paymentStatus, fn ($q, $v) => $q->where('sales.payment_status', $v))
            ->when($status, fn ($q, $v) => $q->where('sales.status', $v))
            ->when($onlyDue, fn ($q) => $q->where('sales.remaining_due_afn', '>', 0))
            ->when($dateFrom, fn ($q, $v) => $q->whereDate('sales.sale_date', '>=', $v))
            ->when($dateTo, fn ($q, $v) => $q->whereDate('sales.sale_date', '<=', $v))
            ->selectRaw('
                sale_items.variant_id,
                products.name as product_name,
                product_variants.name as variant_name,
                COALESCE(SUM(sale_items.quantity), 0) as total_quantity,
                COALESCE(SUM(sale_items.line_total_afn), 0) as total_sales,
                COALESCE(SUM(sale_items.store_profit_afn), 0) as total_profit,
                COALESCE(SUM(sale_items.store_profit_afn - sale_items.investor_profit_afn), 0) as store_profit,
                COALESCE(SUM(sale_items.investor_profit_afn), 0) as investor_profit
            ')
            ->groupBy('sale_items.variant_id', 'products.name', 'product_variants.name')
            ->orderByDesc('total_quantity')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'variant_id'      => $row->variant_id,
                'product_name'    => $row->product_name,
                'variant_name'    => $row->variant_name,
                'total_quantity'  => (int) $row->total_quantity,
                'total_sales'     => (float) $row->total_sales,
                'total_profit'    => (float) $row->total_profit,
                'store_profit'    => (float) $row->store_profit,
                'investor_profit' => (float) $row->investor_profit,
            ]);

        $salespeople = User::query()
            ->whereIn('role', [User::ROLE_CASHIER, User::ROLE_SALESPERSON, User::ROLE_ADMIN, User::ROLE_BRANCH_MANAGER])
            ->where('trash', false)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('sales/report', [
            'sales'       => $sales,
            'summary'     => $summary,
            'byBranch'    => $byBranch,
            'byDay'       => $byDay,
            'byCustomer'  => $byCustomer,
            'byProduct'   => $byProduct,
            'branches'    => Branch::notTrashed()->visibleTo($user)->select('id', 'name', 'code')->get(),
            'customers'   => Customer::notTrashed()->select('id', 'name', 'phone')->orderBy('name')->get(),
            'salespeople' => $salespeople,
            'filters'     => [
                'search'         => $search,
                'branch_id'      => $branchId,
                'customer_id'    => $customerId,
                'salesperson_id' => $salespersonId,
                'payment_status' => $paymentStatus,
                'status'         => $status,
                'only_due'       => $onlyDue,
                'date_from'      => $dateFrom,
                'date_to'        => $dateTo,
            ],
            'canEditCompleted' => $user?->isSuperAdmin() ?? false,
        ]);
    }
}
