<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Investment;
use App\Models\InvestorTransaction;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ExchangeRateHistory;
use App\Models\ProductBarcode;
use App\Models\ProductPrice;
use App\Models\ProductPriceHistory;
use App\Models\ActivityNotification;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Currency;
use App\Services\CatalogPriceConverter;
use App\Services\CurrencyService;
use App\Services\ExchangeService;
use App\Services\InvestorMultiCurrencyDashboardService;
use App\Services\PurchaseService;
use App\Support\Money;
use App\Support\OriginalCurrencyMoney;
use App\Support\PurchaseValuation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly CurrencyService $currencies,
        private readonly ExchangeService $exchange,
        private readonly PurchaseValuation $purchaseValuation,
        private readonly InvestorMultiCurrencyDashboardService $inventoryFx,
        private readonly CatalogPriceConverter $catalogPrices,
    ) {}

    /**
     * Branch ID from user — with branch existence check and safe fallback
     */
    private function getBranchId(): int
    {
        $branchId = (int) (auth()->user()?->branch_id ?? 0);

        if ($branchId > 0 && Branch::where('id', $branchId)->exists()) {
            return $branchId;
        }

        $fallback = Branch::query()
            ->where('trash', false)
            ->orderBy('id')
            ->value('id');

        if (! $fallback) {
            throw ValidationException::withMessages([
                'branch_id' => 'No branch exists in the system. Please create a branch first.',
            ]);
        }

        return (int) $fallback;
    }

    /* =========================================================
     |  INDEX
     ========================================================= */

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search'   => ['nullable', 'string', 'max:100'],
            'status'   => ['nullable', 'in:active,inactive'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'gender'   => ['nullable', 'string', 'max:20'],
        ]);

        $search  = $validated['search']   ?? null;
        $status  = $validated['status']   ?? null;
        $brandId = $validated['brand_id'] ?? null;
        $gender  = $validated['gender']   ?? null;

        $products = Product::query()
            ->with([
                'brand:id,name',
                'variants' => fn ($q) => $q
                    ->notTrashed()
                    ->select(
                        'id', 'product_id',
                        'name', 'sku', 'image',
                        'volume_ml', 'concentration',
                    )
                    ->withSum('inventories as stock_quantity', 'available_quantity')
                    ->with([
                        'barcodes:id,variant_id,barcode,barcode_type,is_primary,status,current_stock',
                        'prices',
                    ]),
            ])
            ->withCount(['variants' => fn ($q) => $q->where('trash', false)])
            ->notTrashed()
            ->when($search, function ($query, $search) {
                $term = trim($search);

                $query->where(function ($q) use ($term) {
                    $q->where('products.name', 'like', "%{$term}%")
                      ->orWhereHas('brand', function ($qb) use ($term) {
                          $qb->where('name', 'like', "%{$term}%");
                      })
                      ->orWhereIn('products.id', function ($sub) use ($term) {
                          $sub->select('pv.product_id')
                              ->from('product_barcodes as pb')
                              ->join('product_variants as pv', 'pv.id', '=', 'pb.variant_id')
                              ->where('pb.barcode', 'like', "%{$term}%")
                              ->whereNull('pv.deleted_at');
                      });
                });
            })
            ->when($status, fn ($q, $s) => $q->where('products.status', $s))
            ->when($brandId, fn ($q, $b) => $q->where('brand_id', $b))
            ->when($gender, fn ($q, $g) => $q->where('gender', $g))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $variantIds = $products->getCollection()->flatMap(fn ($product) => $product->variants->pluck('id'))->unique()->values();

        $batchRows = ProductBatch::query()
            ->whereIn('variant_id', $variantIds)
            ->where('trash', false)
            ->whereNotNull('exchange_rate')
            ->get(['variant_id', 'exchange_rate', 'unit_cost_currency', 'unit_cost_afn', 'quantity', 'total_cost_afn']);

        // Weighted average FX from historical batch snapshots (unit cost original → AFN).
        $weightedByVariant = $batchRows->groupBy('variant_id')->map(function ($rows) {
            return $this->currencies->weightedAverageFxFromEquivalents($rows->map(fn ($row) => [
                'original_amount' => (float) $row->unit_cost_currency * (int) $row->quantity,
                'afn_amount' => $row->total_cost_afn ?: ((float) $row->unit_cost_afn * (int) $row->quantity),
            ])) ?? $this->currencies->weightedAverageRate($rows->map(fn ($row) => [
                'rate' => $row->exchange_rate,
                'amount' => $row->total_cost_afn ?: ((float) $row->unit_cost_afn * (int) $row->quantity),
            ]));
        });

        $products->getCollection()->each(function ($product) use ($weightedByVariant) {
            $rates = $product->variants
                ->map(fn ($variant) => $weightedByVariant->get($variant->id))
                ->filter(fn ($rate) => $rate !== null && (float) $rate > 0)
                ->values();

            $combined = $this->currencies->weightedAverageRate(
                $product->variants->map(fn ($variant) => [
                    'rate' => $weightedByVariant->get($variant->id),
                    'amount' => '1',
                ])
            );

            $product->setAttribute(
                'exchange_rate',
                $combined !== null ? round((float) $combined, 4) : ($rates->isNotEmpty() ? round((float) $rates->first(), 4) : null)
            );
            $product->setAttribute('exchange_rate_kind', $combined !== null ? 'weighted_average' : null);
        });

        $monthlyRates = Cache::remember('products.index.monthly_rates', 300, function () {
            return ExchangeRateHistory::query()
                ->selectRaw('YEAR(rate_date) as year, MONTH(rate_date) as month, ROUND(AVG(usd_to_afn), 2) as average_rate, COUNT(*) as days')
                ->groupByRaw('YEAR(rate_date), MONTH(rate_date)')
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->limit(6)
                ->get();
        });

        return Inertia::render('products/index', [
            'products' => $products,
            'monthlyRates' => $monthlyRates,
            'brands'   => $this->cachedActiveBrands(),
            'filters'  => [
                'search'   => $search,
                'status'   => $status,
                'brand_id' => $brandId,
                'gender'   => $gender,
            ],
        ]);
    }

    /* =========================================================
     |  CREATE
     ========================================================= */

    public function create(): Response
    {
        return Inertia::render('products/create', [
            'brands' => $this->cachedActiveBrands(),

            'investors' => \App\Models\Investor::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),

            'suppliers' => \App\Models\Supplier::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'currencies' => Currency::formOptions(),
            'fxBoard' => $this->currencies->formBoardContext(now()),
        ]);
    }

    public function receive(): Response
    {
        $variants = ProductVariant::query()
            ->with(['product:id,name,brand_id', 'product.brand:id,name', 'barcodes:id,variant_id,barcode'])
            ->where('trash', false)
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(fn (ProductVariant $variant) => $this->receiveVariantPayload($variant));

        $investments = Investment::query()
            ->with(['investor:id,name', 'currency:id,code'])
            ->where('trash', false)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(function (Investment $investment) {
                $amount = (float) $investment->amount_afn;

                return [
                    'id' => $investment->id,
                    'investor_id' => $investment->investor_id,
                    'investment_number' => '#'.$investment->id,
                    'source_type' => 'goods',
                    'currency_code' => $investment->currency?->code,
                    'available_amount_afn' => $amount,
                    'label' => trim(
                        '#'.$investment->id
                        .' · '.number_format($amount, 2).' AFN'
                    ),
                ];
            })
            ->values();

        return Inertia::render('products/receive', [
            'variants' => $variants,
            'investors' => \App\Models\Investor::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'investments' => $investments,
            'suppliers' => \App\Models\Supplier::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'currencies' => Currency::formOptions(),
            'fxBoard' => $this->currencies->formBoardContext(now()),
            'canManageRates' => (bool) auth()->user()?->canManageExchangeRates(),
        ]);
    }

    public function lookupReceiveBarcode(Request $request): JsonResponse
    {
        $barcode = trim((string) $request->query('barcode', ''));
        if ($barcode === '') {
            return response()->json(['found' => false, 'message' => 'Enter a barcode.']);
        }

        $row = ProductBarcode::query()
            ->with(['variant.product.brand:id,name', 'variant.barcodes:id,variant_id,barcode'])
            ->where('barcode', $barcode)
            ->first();

        $variant = $row?->variant;
        if (! $variant || $variant->trash) {
            return response()->json(['found' => false, 'message' => 'No product matches this barcode.']);
        }

        return response()->json([
            'found' => true,
            'variant' => $this->receiveVariantPayload($variant),
        ]);
    }

    public function receiveStore(\App\Http\Requests\ReceiveStockRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (($data['funding_source'] ?? '') === 'office') {
            $data['investor_id'] = null;
            $data['investment_id'] = null;
            $data['profit_per_unit'] = 0;
        }

        // Board FX lives in exchange_rates / PurchaseService — never seed
        // usd_to_afn_rate / aed_to_afn_rate / rate_source onto product_batches.
        $this->getBranchId();

        $this->purchases->receiveStock($data, $request->user(), $this->getBranchId());

        return redirect()->route('products.index')->with('success', 'New stock was added.');
    }

    /* =========================================================
     |  STORE — ✅ with store profit calculation
     ========================================================= */

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validated();

        $product = DB::transaction(function () use ($request, $data) {
            $branchId = $this->getBranchId();

            $data['user_id']  = Auth::id();
            $data['brand_id'] = ! empty($data['brand_id']) ? $data['brand_id'] : null;

            $variantsData  = $data['variants']  ?? [];
            $purchasesData = $data['purchases'] ?? [];
            // Purchase/catalog valuation uses Central Exchange Board (exchange_rates) only.
            $rateOverrides = $this->boardRateOverridesFromExchangeService();

            unset(
                $data['variants'],
                $data['purchases'],
                $data['image'],
                $data['usd_to_afn_rate'],
                $data['usd_to_aed_rate'],
            );

            /* ═══ 1) Create main product ═══ */
            $product = Product::create($data);

            /* ═══ 2) Create variants + barcodes ═══ */
            $variantIds     = [];
            $variantDataMap = [];

            foreach ($variantsData as $index => $variantData) {
                $barcodes = $variantData['barcodes'] ?? [];
                $prices = $variantData['prices'] ?? [];
                unset($variantData['barcodes'], $variantData['prices']);

                $variantData = $this->normalizeVariantData($variantData);
                $variantData = $this->applyVariantMoney($variantData, "variants.{$index}", $rateOverrides);
                $moneyMap = $this->moneyMapFromArray($variantData);
                $variantDataMap[$index] = $moneyMap;

                $variant = ProductVariant::create(array_merge(
                    $this->onlyVariantColumns($variantData),
                    [
                        'product_id' => $product->id,
                        'user_id' => Auth::id(),
                    ]
                ));
                $variantIds[$index] = $variant->id;

                $this->syncBarcodes($variant, $barcodes);
                $this->syncCatalogEquivalentPrices($product, $variant, $moneyMap);
                $this->syncVariantPrices($product, $variant, is_array($prices) ? $prices : []);
            }

            /* ═══ 3) Create purchases + batch + inventory ═══ */
            foreach ($purchasesData as $index => $purchaseData) {
                $variantIndex = $purchaseData['variant_index'] ?? null;
                $variantIndex = is_numeric($variantIndex) ? (int) $variantIndex : null;
                $variantId    = $variantIndex !== null ? ($variantIds[$variantIndex] ?? null) : null;

                if (! $variantId) {
                    continue;
                }

                $billImage = null;
                if ($request->hasFile("purchases.{$index}.bill_image")) {
                    $billImage = $request->file("purchases.{$index}.bill_image")
                        ->store('bills', 'public');
                }

                $qty = (float) ($purchaseData['quantity'] ?? 1);
                // Prefer explicit purchase exchange_rate; otherwise Central Exchange Board
                // via PurchaseValuation (never invent a silent 1:1).
                $exchangeRate = (float) ($purchaseData['exchange_rate'] ?? 0);
                $purchaseDate = $purchaseData['purchase_date'] ?? now()->toDateString();
                $rateAt = \Illuminate\Support\Carbon::parse($purchaseDate)->endOfDay();

                $investorId = $purchaseData['investor_id'] ?? null;
                if (empty($investorId) || $investorId === 'store' || $investorId === 'none') {
                    $investorId = null;
                }

                $variantInfo = $variantDataMap[$variantIndex] ?? $this->emptyVariantMoneyMap();
                $profitCurrencyId = $purchaseData['profit_currency_id']
                    ?? $variantInfo['purchase_currency_id']
                    ?? null;

                $profitSnap = $investorId
                    ? $this->profitViaBoard(
                        (float) ($purchaseData['profit_per_unit'] ?? 0),
                        $profitCurrencyId,
                        "purchases.{$index}.profit_currency_id",
                        $rateAt,
                        $rateOverrides,
                    )
                    : $this->profitViaBoard(0, null, "purchases.{$index}.profit_currency_id");
                $profitUnit = $profitSnap['afn'];

                $supplierId = $purchaseData['supplier_id'] ?? null;
                if (empty($supplierId) || $supplierId === 'none') {
                    $supplierId = null;
                }

                $money = $this->purchaseMoneyFromVariant(
                    $variantInfo,
                    (int) $qty,
                    $exchangeRate > 0 ? (string) $exchangeRate : null,
                    $rateAt,
                    $rateOverrides,
                );
                $purchasePricePerUnit = (float) $money['unit_cost_afn'];
                $salePricePerUnit = (float) ($variantInfo['sale_price_afn'] ?? 0);
                $purchasePriceAfn = (float) $money['line_cost_afn'];

                $lineProfit = $profitUnit * $qty;
                // Preview only — sale-time residual after FIFO is authoritative.
                $storeProfitUnit = round($salePricePerUnit - $purchasePricePerUnit - $profitUnit, 2);
                $lineStoreProfit = round($storeProfitUnit * $qty, 2);

                $investmentId = null;
                if ($investorId) {
                    $investment = Investment::createFromPurchase([
                        'investor_id' => (int) $investorId,
                        'currency_id' => $money['purchase_currency_id'] ?? null,
                        'amount_currency' => $money['purchase_line_amount'] ?? $purchasePriceAfn,
                        'amount_afn' => $purchasePriceAfn,
                        'exchange_rate' => $money['rate_to_ledger'] ?? null,
                        'investment_date' => $purchaseDate,
                        'notes' => 'Auto-created on product create',
                        'created_by' => Auth::id(),
                    ]);
                    $investmentId = $investment->id;

                    InvestorTransaction::record(
                        investorId: (int) $investorId,
                        type: 'goods_deposit',
                        amountAfn: $purchasePriceAfn,
                        balanceType: 'capital',
                        options: [
                            'investment_id' => $investmentId,
                            'currency_id' => $money['purchase_currency_id'] ?? null,
                            'currency_code' => $money['purchase_currency_code'] ?? null,
                            'amount_currency' => $money['purchase_line_amount'] ?? $purchasePriceAfn,
                            'exchange_rate' => $money['rate_to_ledger'] ?? null,
                            'description' => "Goods contribution — {$product->name}",
                            'transaction_date' => $purchaseDate,
                        ]
                    );
                }

                $batch = $this->createProductBatch(
                    branchId: $branchId,
                    variantId: $variantId,
                    investmentId: $investmentId,
                    qty: (int) $qty,
                    unitCostAfn: $purchasePricePerUnit,
                    unitCostCurrency: (float) $money['purchase_unit_amount'],
                    salePriceAfn: $salePricePerUnit,
                    exchangeRate: (float) $money['rate_to_ledger'],
                    profitPerUnitAfn: $profitUnit,
                    storeProfitPerUnit: $storeProfitUnit,
                    receivedDate: $purchaseDate,
                    invoiceNumber: $purchaseData['invoice_number'] ?? null,
                    purchaseDate: $purchaseDate,
                    paymentStatus: $purchaseData['payment_status'] ?? ($supplierId ? 'unpaid' : 'paid'),
                    billImage: $billImage,
                    notes: $purchaseData['notes'] ?? null,
                    purchaseCurrencyId: $money['purchase_currency_id'] ?? null,
                    purchaseAmountCurrency: isset($money['purchase_line_amount'])
                        ? (float) $money['purchase_line_amount']
                        : null,
                );

                if ($supplierId) {
                    $this->syncSupplierInvoiceForBatch(
                        (int) $supplierId,
                        $batch,
                        $purchasePriceAfn,
                        (float) ($money['rate_to_ledger'] ?? 0),
                    );
                    $this->applyPaidNowToBatch(
                        $batch,
                        (int) $supplierId,
                        (float) ($purchaseData['paid_now'] ?? 0),
                        (float) ($money['rate_to_ledger'] ?? 0),
                    );
                }

                Inventory::increase(
                    branchId: $branchId,
                    variantId: $variantId,
                    qty: (int) $qty,
                    type: 'purchase',
                    batchId: $batch->id,
                    referenceType: ProductBatch::class,
                    referenceId: $batch->id,
                    notes: "New purchase — {$product->name}",
                    unitCostAfn: $purchasePricePerUnit
                );
            }

            return $product;
        });

        $this->notifySuperAdmins('Product created', $product->name.' was created.', '/products/'.$product->id);

        return redirect()
            ->route('products.index')
            ->with('success', 'Product created successfully.');
    }

    /* =========================================================
     |  SHOW / EDIT
     ========================================================= */

    public function show(Product $product): Response
    {
        if ($product->trash) {
            abort(404);
        }

        $product->load([
            'brand:id,name',
            'creator:id,name,email',
            'variants' => fn ($q) => $q
                ->notTrashed()
                ->with([
                    'barcodes:id,variant_id,barcode,barcode_type,is_primary,status,current_stock',
                    'prices.currency:id,code,symbol,name',
                ]),
        ]);

        $variantIds = $product->variants->pluck('id');

        $batches = ProductBatch::query()
            ->whereIn('variant_id', $variantIds)
            ->where('trash', false)
            ->with([
                'variant:id,product_id,volume_ml,concentration',
                'investment:id,investor_id',
                'investment.investor:id,name',
                'purchaseCurrency:id,code,symbol',
            ])
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get();

        $invoiceNumbers = $batches
            ->map(fn (ProductBatch $batch) => mb_substr($batch->invoice_number ?: ('B-'.$batch->id), 0, 50))
            ->unique()
            ->values();
        $invoices = \App\Models\SupplierInvoice::query()
            ->whereIn('invoice_no', $invoiceNumbers)
            ->get()
            ->keyBy('invoice_no');

        $purchases = $batches->map(function (ProductBatch $batch) use ($invoices) {
            $number = mb_substr($batch->invoice_number ?: ('B-'.$batch->id), 0, 50);
            $invoice = $invoices->get($number);

            return [
                'id' => $batch->id,
                'invoice_number' => $batch->invoice_number,
                'purchase_date' => optional($batch->purchase_date)->toDateString(),
                'quantity' => $batch->quantity,
                'remaining_quantity' => $batch->remaining_quantity,
                'payment_status' => $batch->payment_status,
                'notes' => $batch->notes,
                'bill_image' => $batch->bill_image,
                'variant_id' => $batch->variant_id,
                'investor_id' => $batch->investment?->investor_id,
                'investment_id' => $batch->investment_id,
                'unit_cost_afn' => $batch->unit_cost_afn,
                'total_cost_afn' => $batch->total_cost_afn,
                'profit_per_unit_afn' => $batch->profit_per_unit_afn,
                'sale_price_afn' => $batch->sale_price_afn,
                'exchange_rate' => $batch->exchange_rate,
                'purchase_currency_id' => $batch->purchase_currency_id,
                'purchase_currency_code' => $batch->purchaseCurrency?->code,
                'purchase_unit_amount' => $batch->unit_cost_currency,
                'purchase_amount_currency' => $batch->purchase_amount_currency,
                'invoice_id' => $invoice?->id,
                'supplier_id' => $invoice?->supplier_id,
                'invoice_paid_afn' => $invoice ? (float) $invoice->paid_afn : 0,
                'invoice_due_afn' => $invoice ? (float) $invoice->due_afn : 0,
                'variant' => $batch->variant,
                'investor' => $batch->investment?->investor,
            ];
        });

        return Inertia::render('products/show', [
            'product' => $product,
            'purchases' => $purchases,
            'inventoryValuation' => [
                'remaining_cost_afn' => round((float) $batches->sum(fn (ProductBatch $b) => (float) $b->remaining_cost_afn), 2),
                'remaining_qty' => (int) $batches->sum('remaining_quantity'),
            ],
            'fxBoard' => $this->currencies->formBoardContext(now()),
        ]);
    }

    /**
     * Attach original-currency investor PPU from the related FIFO batch so edit/show
     * do not display AFN ledger profit under a foreign currency label.
     *
     * @param  \Illuminate\Support\Collection<int, Product>  $purchases
     */
    private function attachOriginalProfitFromBatches($purchases): void
    {
        if ($purchases->isEmpty()) {
            return;
        }

        $batches = ProductBatch::query()
            ->whereIn('purchase_item_id', $purchases->pluck('id')->all())
            ->where('trash', false)
            ->get(['purchase_item_id', 'profit_per_unit_currency', 'profit_currency_id', 'profit_currency_code', 'profit_per_unit_afn', 'profit_per_unit_usd', 'profit_per_unit_aed'])
            ->keyBy('purchase_item_id');

        foreach ($purchases as $purchase) {
            $batch = $batches->get($purchase->id);
            if (! $batch) {
                $purchase->setAttribute('profit_per_unit_original', null);
                continue;
            }

            $original = $batch->profit_per_unit_currency;
            if ($original === null || (float) $original <= 0) {
                $code = strtoupper((string) ($batch->profit_currency_code ?? 'AFN'));
                $original = match ($code) {
                    'USD' => $batch->profit_per_unit_usd,
                    'AED' => $batch->profit_per_unit_aed,
                    default => $batch->profit_per_unit_afn,
                };
            }

            $purchase->setAttribute('profit_per_unit_original', $original);
            if ($batch->profit_currency_id) {
                $purchase->setAttribute('profit_currency_id', $batch->profit_currency_id);
            }
            if ($batch->profit_currency_code) {
                $purchase->setAttribute('profit_currency_code', $batch->profit_currency_code);
            }
        }
    }

    /**
     * Remaining-stock valuation by original purchase currency (reporting only; FIFO unchanged).
     *
     * @param  \Illuminate\Support\Collection<int, Product>  $purchases
     * @return array<string, mixed>
     */
    private function buildProductInventoryValuation($purchases): array
    {
        $byCurrency = [];
        foreach (OriginalCurrencyMoney::CODES as $code) {
            $byCurrency[$code] = [
                'code' => $code,
                'original_amount' => '0.00',
                'historical_afn' => '0.00',
                'units' => 0,
                'batches' => 0,
                'weighted_average_fx_to_afn' => null,
                'kind' => 'historical_weighted_average',
                'available' => false,
            ];
        }

        $rowsByCurrency = ['USD' => [], 'AED' => [], 'AFN' => []];

        foreach ($purchases as $purchase) {
            $batches = $purchase->relationLoaded('purchaseBatches')
                ? $purchase->purchaseBatches
                : ProductBatch::query()->where('purchase_item_id', $purchase->id)->where('trash', false)->get();

            foreach ($batches as $batch) {
                $remaining = max(0, (int) $batch->remaining_quantity);
                if ($remaining <= 0) {
                    continue;
                }

                $batch->loadMissing('purchaseCurrency:id,code');
                $code = strtoupper((string) ($batch->purchaseCurrency?->code ?? 'AFN'));
                if (! in_array($code, OriginalCurrencyMoney::CODES, true)) {
                    $code = 'AFN';
                }

                if (! in_array($code, OriginalCurrencyMoney::CODES, true)) {
                    continue;
                }

                $unitOriginal = (string) ($batch->unit_cost_currency ?? '0');
                if ((float) $unitOriginal <= 0) {
                    $unitOriginal = (string) ($batch->unit_cost_afn ?? '0');
                }
                $unitAfn = (string) ($batch->unit_cost_afn ?? '0');
                $original = Money::round(Money::mul($unitOriginal, (string) $remaining, 8), 2);
                $afn = Money::round(Money::mul($unitAfn, (string) $remaining, 8), 2);

                $byCurrency[$code]['original_amount'] = Money::add($byCurrency[$code]['original_amount'], $original, 2);
                $byCurrency[$code]['historical_afn'] = Money::add($byCurrency[$code]['historical_afn'], $afn, 2);
                $byCurrency[$code]['units'] += $remaining;
                $byCurrency[$code]['batches']++;
                $rowsByCurrency[$code][] = [
                    'original_amount' => $original,
                    'afn_amount' => $afn,
                    'quantity' => $remaining,
                ];
            }
        }

        foreach (['USD', 'AED'] as $code) {
            $avg = $this->inventoryFx->weightedAverageFromRemaining($rowsByCurrency[$code]);
            $byCurrency[$code]['weighted_average_fx_to_afn'] = $avg['rate'];
            $byCurrency[$code]['available'] = $avg['available'];
        }
        if ((float) $byCurrency['AFN']['original_amount'] > 0) {
            $byCurrency['AFN']['weighted_average_fx_to_afn'] = '1.00000000';
            $byCurrency['AFN']['available'] = true;
            $byCurrency['AFN']['kind'] = 'ledger';
        }

        return [
            'basis' => 'historical_batch_cost',
            'basis_label' => 'Historical weighted average from remaining purchase rows (not current market FX). FIFO sales costing is unchanged.',
            'by_currency' => $byCurrency,
            'total_afn_remaining' => Money::round(
                Money::add(
                    Money::add($byCurrency['AFN']['historical_afn'], $byCurrency['USD']['historical_afn'], 8),
                    $byCurrency['AED']['historical_afn'],
                    8
                ),
                2
            ),
        ];
    }

    public function edit(Product $product): Response
    {
        if ($product->trash) {
            abort(404);
        }

        $product->load([
            'variants' => fn ($q) => $q
                ->notTrashed()
                ->with([
                    'barcodes:id,variant_id,barcode,barcode_type,is_primary,status,current_stock',
                    'prices.currency:id,code,symbol,name',
                ]),
        ]);

        $variantIds = $product->variants->pluck('id');

        $batches = ProductBatch::query()
            ->whereIn('variant_id', $variantIds)
            ->where('trash', false)
            ->with([
                'variant:id,product_id,volume_ml,concentration',
                'investment:id,investor_id',
                'investment.investor:id,name',
                'purchaseCurrency:id,code,symbol',
            ])
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get();

        $purchases = $batches->map(function (ProductBatch $batch) {
            return [
                'id' => $batch->id,
                'invoice_number' => $batch->invoice_number,
                'purchase_date' => optional($batch->purchase_date)->toDateString(),
                'quantity' => $batch->quantity,
                'remaining_quantity' => $batch->remaining_quantity,
                'payment_status' => $batch->payment_status,
                'notes' => $batch->notes,
                'bill_image' => $batch->bill_image,
                'variant_id' => $batch->variant_id,
                'investor_id' => $batch->investment?->investor_id,
                'investment_id' => $batch->investment_id,
                'unit_cost_afn' => $batch->unit_cost_afn,
                'total_cost_afn' => $batch->total_cost_afn,
                'purchase_price_afn' => $batch->total_cost_afn,
                'profit_per_unit' => $batch->profit_per_unit_afn,
                'profit_per_unit_original' => $batch->profit_per_unit_afn,
                'profit_currency_id' => $batch->purchase_currency_id,
                'store_profit_per_unit' => $batch->store_profit_per_unit_afn,
                'sale_price_afn' => $batch->sale_price_afn,
                'exchange_rate' => $batch->exchange_rate,
                'purchase_unit_amount' => $batch->unit_cost_currency,
                'purchase_currency_id' => $batch->purchase_currency_id,
                'purchase_currency_code' => $batch->purchaseCurrency?->code,
                'purchase_amount_currency' => $batch->purchase_amount_currency,
                'variant' => $batch->variant,
                'investor' => $batch->investment?->investor,
            ];
        });

        return Inertia::render('products/edit', [
            'product'   => $product,
            'purchases' => $purchases,
            'brands'    => $this->cachedActiveBrands(),
            'investors' => \App\Models\Investor::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'suppliers' => \App\Models\Supplier::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'currencies' => Currency::formOptions(
                $product->variants->flatMap(fn ($variant) => [
                    $variant->catalog_currency_id,
                    $variant->purchase_currency_id,
                    $variant->sale_currency_id,
                    $variant->wholesale_currency_id,
                ])->merge($batches->pluck('purchase_currency_id'))->all()
            ),
            'fxBoard' => $this->currencies->formBoardContext(now()),
        ]);
    }

    /* =========================================================
     |  UPDATE
     ========================================================= */

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        if ($product->trash) {
            abort(404);
        }

        $data = $request->validated();

        DB::transaction(function () use ($request, $product, $data) {
            $data['user_id']  = Auth::id();
            $data['brand_id'] = ! empty($data['brand_id']) ? $data['brand_id'] : null;

            $variantsData  = $data['variants']  ?? [];
            $purchasesData = $data['purchases'] ?? [];
            // Purchase/catalog valuation uses Central Exchange Board (exchange_rates) only.
            $rateOverrides = $this->boardRateOverridesFromExchangeService();

            unset(
                $data['variants'],
                $data['purchases'],
                $data['image'],
                $data['usd_to_afn_rate'],
                $data['usd_to_aed_rate'],
            );

            $product->update($data);

            $variantIndexToId = [];
            $variantDataMap   = [];

            foreach ($variantsData as $index => $variantData) {
                $barcodes = $variantData['barcodes'] ?? [];
                $prices = $variantData['prices'] ?? [];
                unset($variantData['barcodes'], $variantData['prices']);

                $variantId = $variantData['id'] ?? null;
                unset($variantData['id']);

                $variantData = $this->normalizeVariantData($variantData);
                $variantData = $this->applyVariantMoney($variantData, "variants.{$index}", $rateOverrides);

                $moneyMap = $this->moneyMapFromArray($variantData);
                $variantDataMap[$index] = $moneyMap;

                if ($variantId) {
                    $variant = ProductVariant::where('id', $variantId)
                        ->where('product_id', $product->id)
                        ->first();

                    if ($variant) {
                        $oldPurchasePrice = $variant->activePriceAmount('purchase', 'AFN');
                        $oldSalePrice     = $variant->activePriceAmount('sale', 'AFN');

                        $variant->update(array_merge(
                            $this->onlyVariantColumns($variantData),
                            ['user_id' => Auth::id()]
                        ));

                        $variantIndexToId[$index] = $variant->id;

                        $this->syncBarcodes($variant, $barcodes);
                        $this->syncCatalogEquivalentPrices($product, $variant, $moneyMap);
                        $this->syncVariantPrices($product, $variant, is_array($prices) ? $prices : []);

                        $newPurchasePrice = (float) ($moneyMap['purchase_price_afn'] ?? $oldPurchasePrice);
                        $newSalePrice     = (float) ($moneyMap['sale_price_afn'] ?? $oldSalePrice);

                        $this->recordPriceDropLoss($variant, $oldPurchasePrice, $newPurchasePrice);

                        $this->recordPriceChange(
                            variant: $variant,
                            oldPurchase: $oldPurchasePrice,
                            newPurchase: $newPurchasePrice,
                            oldSale: $oldSalePrice,
                            newSale: $newSalePrice,
                            reason: 'Product update'
                        );
                    }
                } else {
                    $variant = ProductVariant::create(array_merge(
                        $this->onlyVariantColumns($variantData),
                        [
                            'product_id' => $product->id,
                            'user_id' => Auth::id(),
                        ]
                    ));
                    $variantIndexToId[$index] = $variant->id;

                    $this->syncBarcodes($variant, $barcodes);
                    $this->syncCatalogEquivalentPrices($product, $variant, $moneyMap);
                    $this->syncVariantPrices($product, $variant, is_array($prices) ? $prices : []);
                }
            }

            ProductVariant::where('product_id', $product->id)
                ->whereNotIn('id', array_values($variantIndexToId))
                ->update(['trash' => true, 'user_id' => Auth::id()]);

            $this->syncPurchases($product, $purchasesData, $variantIndexToId, $variantDataMap, $rateOverrides);

            $this->refreshVariantStock($product);
        });

        $this->notifySuperAdmins('Product updated', $product->name.' was updated.', '/products/'.$product->id);

        return redirect()
            ->route('products.index')
            ->with('success', 'Product updated successfully.');
    }

    /* =========================================================
     |  DESTROY / RESTORE / FORCE DELETE
     ========================================================= */

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->trash) {
            return back()->with('error', 'Product is already in trash.');
        }

        DB::transaction(function () use ($product) {
            $product->update(['trash' => true, 'user_id' => Auth::id()]);
            $product->variants()->update(['trash' => true, 'user_id' => Auth::id()]);

            $variantIds = $product->variants()->pluck('id')->all();
            if (! empty($variantIds)) {
                ProductBatch::whereIn('variant_id', $variantIds)
                    ->update(['trash' => true, 'user_id' => Auth::id()]);

                Inventory::whereIn('variant_id', $variantIds)
                    ->update(['trash' => true, 'user_id' => Auth::id()]);
            }
        });

        $this->notifySuperAdmins('Product deleted', $product->name.' was moved to trash.', '/products');

        return redirect()
            ->route('products.index')
            ->with('success', 'Product was moved to trash.');
    }

    public function restore(int $id): RedirectResponse
    {
        $product = Product::onlyTrash()->findOrFail($id);

        DB::transaction(function () use ($product) {
            $product->update(['trash' => false, 'user_id' => Auth::id()]);
            $product->variants()->update(['trash' => false, 'user_id' => Auth::id()]);

            $variantIds = $product->variants()->pluck('id')->all();
            if (! empty($variantIds)) {
                ProductBatch::whereIn('variant_id', $variantIds)
                    ->update(['trash' => false, 'user_id' => Auth::id()]);

                Inventory::whereIn('variant_id', $variantIds)
                    ->update(['trash' => false, 'user_id' => Auth::id()]);
            }
        });

        return redirect()
            ->route('products.index')
            ->with('success', 'Product was restored.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $product = Product::onlyTrash()
            ->with(['variants.barcodes', 'variants'])
            ->findOrFail($id);

        DB::transaction(function () use ($product) {
            $variantIds = $product->variants->pluck('id')->all();

            $batches = ProductBatch::query()
                ->whereIn('variant_id', $variantIds)
                ->get();

            foreach ($batches as $batch) {
                if ($batch->bill_image && Storage::disk('public')->exists($batch->bill_image)) {
                    Storage::disk('public')->delete($batch->bill_image);
                }
            }

            Inventory::whereIn('variant_id', $variantIds)->forceDelete();

            ProductPriceHistory::whereIn('variant_id', $variantIds)->forceDelete();

            ProductBatch::whereIn('variant_id', $variantIds)->forceDelete();

            ProductBarcode::whereIn('variant_id', $variantIds)->forceDelete();

            ProductVariant::where('product_id', $product->id)->forceDelete();
            $product->forceDelete();
        });

        return redirect()
            ->route('products.trash')
            ->with('success', 'Product was permanently deleted.');
    }

   public function trash(): Response
{
    $products = Product::onlyTrash()
        ->with([
            'brand:id,name',
            'creator:id,name',
            'variants' => fn ($q) => $q->notTrashed(),
        ])
        ->orderByDesc('updated_at')
        ->paginate(15);

    return Inertia::render('products/trash', [
        'products' => $products,
    ]);
}

    public function toggleStatus(Product $product): RedirectResponse
    {
        if ($product->trash) {
            abort(404);
        }

        $product->update([
            'status'  => $product->status === 'active' ? 'inactive' : 'active',
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', "Product status changed to {$product->status}.");
    }

    /* =========================================================
     |  CHECK BARCODE
     ========================================================= */

    public function checkBarcode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode'           => ['required', 'string', 'max:100'],
            'ignore_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
        ]);

        $barcode       = $validated['barcode'];
        $ignoreVariant = $validated['ignore_variant_id'] ?? null;

        $query = ProductBarcode::query()
            ->where('barcode', $barcode)
            ->with(['variant.product.brand']);

        if ($ignoreVariant) {
            $query->where('variant_id', '!=', $ignoreVariant);
        }

        $existing = $query->first();

        if ($existing) {
            $brandName   = $existing->variant?->product?->brand?->name;
            $variantName = $existing->variant?->full_name;

            $productName = trim(
                ($brandName ? $brandName . ' — ' : '') . ($variantName ?? '')
            );

            return response()->json([
                'exists'       => true,
                'product_name' => $productName ?: null,
            ]);
        }

        return response()->json(['exists' => false]);
    }

    /* =========================================================
     |  PRIVATE HELPERS
     ========================================================= */

    /**
     * @return array<string, mixed>
     */
    private function receiveVariantPayload(ProductVariant $variant): array
    {
        $product = $variant->product;

        return [
            'id' => $variant->id,
            'name' => trim(($product?->brand?->name ? $product->brand->name.' — ' : '').($product?->name ?? 'Product')),
            'volume_ml' => $variant->volume_ml,
            'concentration' => $variant->concentration,
            'purchase_price_afn' => $variant->activePriceAmount('purchase', 'AFN'),
            'sale_price_afn' => $variant->activePriceAmount('sale', 'AFN'),
            'stock_quantity' => $variant->availableQuantity(),
            'barcodes' => $variant->barcodes->pluck('barcode')->filter()->values(),
        ];
    }

    private function createProductBatch(
        int $branchId,
        int $variantId,
        ?int $investmentId,
        int $qty,
        float $unitCostAfn,
        float $unitCostCurrency,
        float $salePriceAfn,
        float $exchangeRate,
        float $profitPerUnitAfn,
        float $storeProfitPerUnit,
        string $receivedDate,
        ?string $invoiceNumber = null,
        ?string $purchaseDate = null,
        ?string $paymentStatus = null,
        ?string $billImage = null,
        ?string $notes = null,
        ?int $purchaseCurrencyId = null,
        ?float $purchaseAmountCurrency = null,
    ): ProductBatch {
        if (! Branch::where('id', $branchId)->exists()) {
            throw ValidationException::withMessages([
                'branch_id' => "Branch with ID {$branchId} does not exist.",
            ]);
        }

        if ($exchangeRate <= 0) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'Batch exchange_rate must come from the exchange_rates board (or an explicit purchase override) and be greater than zero.',
            ]);
        }

        return ProductBatch::create([
            'branch_id' => $branchId,
            'variant_id' => $variantId,
            'investment_id' => $investmentId,
            'batch_code' => ProductBatch::generateBatchCode(),
            'invoice_number' => $invoiceNumber,
            'purchase_date' => $purchaseDate ?? $receivedDate,
            'payment_status' => $paymentStatus,
            'bill_image' => $billImage,
            'notes' => $notes,
            'purchase_currency_id' => $purchaseCurrencyId,
            'purchase_amount_currency' => $purchaseAmountCurrency,
            'quantity' => $qty,
            'remaining_quantity' => $qty,
            'unit_cost_currency' => $unitCostCurrency,
            'exchange_rate' => $exchangeRate,
            'unit_cost_afn' => $unitCostAfn,
            'sale_price_afn' => $salePriceAfn,
            'profit_per_unit_afn' => $profitPerUnitAfn,
            'store_profit_per_unit_afn' => $storeProfitPerUnit,
            'received_date' => $receivedDate,
            'status' => 'active',
            'user_id' => Auth::id(),
            'trash' => false,
        ]);
    }

    private function updateProductBatch(
        ProductBatch $batch,
        int $newQty,
        int $oldQty,
        float $unitCostAfn,
        float $unitCostCurrency,
        float $salePriceAfn,
        float $exchangeRate,
        float $profitPerUnitAfn,
        float $storeProfitPerUnit,
        ?int $investmentId,
        string $receivedDate,
        ?string $invoiceNumber = null,
        ?string $purchaseDate = null,
        ?string $paymentStatus = null,
        ?string $billImage = null,
        ?string $notes = null,
        ?int $purchaseCurrencyId = null,
        ?float $purchaseAmountCurrency = null,
    ): void {
        $diff = $newQty - $oldQty;
        $newRemaining = max(0, $batch->remaining_quantity + $diff);

        $payload = [
            'quantity' => $newQty,
            'remaining_quantity' => $newRemaining,
            'investment_id' => $investmentId,
            'unit_cost_afn' => $unitCostAfn,
            'unit_cost_currency' => $unitCostCurrency,
            'exchange_rate' => $exchangeRate,
            'sale_price_afn' => $salePriceAfn,
            'profit_per_unit_afn' => $profitPerUnitAfn,
            'store_profit_per_unit_afn' => $storeProfitPerUnit,
            'received_date' => $receivedDate,
            'purchase_date' => $purchaseDate ?? $receivedDate,
            'invoice_number' => $invoiceNumber ?? $batch->invoice_number,
            'payment_status' => $paymentStatus ?? $batch->payment_status,
            'notes' => $notes ?? $batch->notes,
            'purchase_currency_id' => $purchaseCurrencyId ?? $batch->purchase_currency_id,
            'purchase_amount_currency' => $purchaseAmountCurrency ?? $batch->purchase_amount_currency,
            'status' => $newRemaining > 0 ? 'active' : 'depleted',
            'user_id' => Auth::id(),
        ];

        if ($billImage) {
            $payload['bill_image'] = $billImage;
        }

        $batch->update($payload);
    }

    /**
     * ⭐ Record loss from purchase price drop
     */
    private function recordPriceDropLoss(ProductVariant $variant, float $oldPurchase, float $newPurchase): void
    {
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

        app(\App\Services\ExpenseService::class)->createLedgerOnly([
            'branch_id' => $this->getBranchId(),
            'title' => 'Price drop loss',
            'category' => 'other',
            'amount_afn' => $loss,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'other',
            'description' => "{$name}: purchase price fell from {$oldPurchase} to {$newPurchase} AFN on {$qty} pieces still in stock.",
        ], Auth::id());
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
            variantId:       $variant->id,
            oldPurchaseAfn:  $oldPurchase,
            newPurchaseAfn:  $newPurchase,
            oldSaleAfn:      $oldSale,
            newSaleAfn:      $newSale,
            reason:          $reason
        );
    }

    private function normalizeVariantData(array $variantData): array
    {
        // Legacy clients may still post this; column was removed.
        unset($variantData['minimum_stock']);

        $defaults = [
            'volume_ml'           => null,
            'concentration'       => null,
            'purchase_price_afn'  => 0,
            'purchase_amount'     => 0,
            'sale_price_afn'      => 0,
            'sale_amount'         => 0,
            'wholesale_price_afn' => 0,
            'wholesale_amount'    => 0,
            'wholesale_min_qty'   => 0,
            'profit_per_unit'     => 0,
            'stock_quantity'      => 0,
        ];

        foreach ($defaults as $key => $default) {
            if (
                ! array_key_exists($key, $variantData) ||
                $variantData[$key] === '' ||
                $variantData[$key] === null
            ) {
                $variantData[$key] = $default;
            }
        }

        // Keep entered original amounts aligned when only ledger-named fields are posted.
        foreach ([
            'purchase' => ['amount' => 'purchase_amount', 'ledger' => 'purchase_price_afn'],
            'sale' => ['amount' => 'sale_amount', 'ledger' => 'sale_price_afn'],
            'wholesale' => ['amount' => 'wholesale_amount', 'ledger' => 'wholesale_price_afn'],
        ] as $pair) {
            $amount = $variantData[$pair['amount']] ?? null;
            $ledger = $variantData[$pair['ledger']] ?? null;
            if (($amount === null || $amount === '' || $amount === 0) && $ledger !== null && $ledger !== '') {
                $variantData[$pair['amount']] = $ledger;
            }
            if (($ledger === null || $ledger === '' || $ledger === 0) && $amount !== null && $amount !== '') {
                $variantData[$pair['ledger']] = $amount;
            }
        }

        foreach ([
            'volume_ml', 'purchase_price_afn', 'purchase_amount',
            'sale_price_afn', 'sale_amount',
            'wholesale_price_afn', 'wholesale_amount', 'wholesale_min_qty',
            'profit_per_unit', 'stock_quantity',
        ] as $numericField) {
            if (isset($variantData[$numericField]) && $variantData[$numericField] !== null) {
                $variantData[$numericField] = is_numeric($variantData[$numericField])
                    ? $variantData[$numericField] + 0
                    : 0;
            }
        }

        return $variantData;
    }

    /**
     * @return array<string, mixed>
     */
    private function variantMoneyMap(ProductVariant $variant): array
    {
        $variant->loadMissing('prices');

        $purchase = $variant->prices->firstWhere(fn ($p) => $p->price_type === 'purchase' && $p->is_active && (int) $p->min_qty === 0)
            ?? $variant->prices->firstWhere(fn ($p) => $p->price_type === 'purchase' && $p->is_active);
        $sale = $variant->prices->firstWhere(fn ($p) => $p->price_type === 'sale' && $p->is_active && (int) $p->min_qty === 0)
            ?? $variant->prices->firstWhere(fn ($p) => $p->price_type === 'sale' && $p->is_active);
        $wholesale = $variant->prices->where('price_type', 'wholesale')->where('is_active', true)->sortByDesc('min_qty')->first();

        $purchaseAfn = $variant->activePriceAmount('purchase', 'AFN');
        $saleAfn = $variant->activePriceAmount('sale', 'AFN');
        $wholesaleAfn = $variant->activePriceAmount('wholesale', 'AFN', max(1, (int) ($wholesale?->min_qty ?? 1)));

        return [
            'purchase_price_afn' => $purchaseAfn,
            'purchase_amount' => (float) ($purchase?->amount ?? $purchaseAfn),
            'purchase_amount_usd' => $variant->activePriceAmount('purchase', 'USD') ?: null,
            'purchase_amount_aed' => $variant->activePriceAmount('purchase', 'AED') ?: null,
            'purchase_currency_id' => $purchase?->currency_id,
            'sale_price_afn' => $saleAfn,
            'sale_amount' => (float) ($sale?->amount ?? $saleAfn),
            'sale_amount_usd' => $variant->activePriceAmount('sale', 'USD') ?: null,
            'sale_amount_aed' => $variant->activePriceAmount('sale', 'AED') ?: null,
            'sale_currency_id' => $sale?->currency_id,
            'wholesale_price_afn' => $wholesaleAfn,
            'wholesale_amount' => (float) ($wholesale?->amount ?? $wholesaleAfn),
            'wholesale_amount_usd' => $variant->activePriceAmount('wholesale', 'USD', max(1, (int) ($wholesale?->min_qty ?? 1))) ?: null,
            'wholesale_amount_aed' => $variant->activePriceAmount('wholesale', 'AED', max(1, (int) ($wholesale?->min_qty ?? 1))) ?: null,
            'wholesale_currency_id' => $wholesale?->currency_id,
            'wholesale_min_qty' => (int) ($wholesale?->min_qty ?? 0),
            'catalog_currency_id' => $sale?->currency_id ?? $purchase?->currency_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyVariantMoneyMap(): array
    {
        return [
            'purchase_price_afn' => 0,
            'purchase_amount' => 0,
            'purchase_currency_id' => null,
            'wholesale_price_afn' => 0,
            'wholesale_min_qty' => 0,
            'sale_price_afn' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $variantInfo
     * @return array<string, mixed>
     */
    private function purchaseMoneyFromVariant(
        array $variantInfo,
        int $qty,
        ?string $transactionRate,
        \Carbon\CarbonInterface|\DateTimeInterface|string|null $at = null,
        array $rateOverrides = [],
    ): array {
        $currency = null;
        if (! empty($variantInfo['purchase_currency_id'])) {
            $currency = Currency::query()->notTrashed()->find((int) $variantInfo['purchase_currency_id']);
        }

        $original = (string) ($variantInfo['purchase_amount'] ?? $variantInfo['purchase_price_afn'] ?? 0);

        return $this->purchaseValuation->snapshot(
            $original,
            $qty,
            $currency,
            $transactionRate,
            $at,
            $rateOverrides,
        );
    }

    /**
     * Columns safe to persist / expose for a product_batches purchase line.
     * Board pair snapshots (usd_to_afn_rate, …) are intentionally omitted —
     * batches store only exchange_rate (purchase currency → AFN).
     *
     * @param  array<string, mixed>  $money
     * @return array<string, mixed>
     */
    private function purchaseSnapshotColumns(array $money): array
    {
        return [
            'exchange_rate' => $money['exchange_rate'],
            'purchase_currency_id' => $money['purchase_currency_id'],
            'purchase_currency_code' => $money['purchase_currency_code'],
            'purchase_currency_symbol' => $money['purchase_currency_symbol'],
            'purchase_unit_amount' => $money['purchase_unit_amount'],
            'purchase_line_amount' => $money['purchase_line_amount'],
            'rate_to_ledger' => $money['rate_to_ledger'],
            'rate_kind' => $money['rate_kind'],
            'unit_amount_usd' => $money['unit_amount_usd'] ?? null,
            'unit_amount_aed' => $money['unit_amount_aed'] ?? null,
            'line_amount_usd' => $money['line_amount_usd'] ?? null,
            'line_amount_aed' => $money['line_amount_aed'] ?? null,
        ];
    }

    /**
     * Unified catalog pricing: one currency for purchase/sale/wholesale, then AFN/USD/AED via FxBoard.
     *
     * Never substitutes for:
     * - historical purchase cost snapshots (PurchaseValuation / purchase FX board)
     * - sale transaction FX snapshots
     * - investor profit configuration FX (profitViaBoard / batch profit columns)
     * - reporting equivalents built from remaining inventory weighted averages
     *
     * @param  array<string, mixed>  $variantData
     * @param  array{usd_to_afn?: string|float|null, usd_to_aed?: string|float|null, aed_to_afn?: string|float|null, source?: string|null}  $rateOverrides
     * @return array<string, mixed>
     */
    private function applyVariantMoney(array $variantData, string $errorPrefix, array $rateOverrides = []): array
    {
        $ledger = Currency::ledger();
        $fallbackId = $variantData['catalog_currency_id']
            ?? $variantData['sale_currency_id']
            ?? $variantData['purchase_currency_id']
            ?? $variantData['wholesale_currency_id']
            ?? $ledger?->id;

        $purchaseCurrencyId = $variantData['purchase_currency_id'] ?? $fallbackId;
        $saleCurrencyId = $variantData['sale_currency_id'] ?? $fallbackId;
        $wholesaleCurrencyId = $variantData['wholesale_currency_id'] ?? $fallbackId ?? $saleCurrencyId;

        if (empty($purchaseCurrencyId) || empty($saleCurrencyId) || empty($wholesaleCurrencyId)) {
            throw ValidationException::withMessages([
                $errorPrefix.'.sale_currency_id' => 'Select a pricing currency (AFN, USD, or AED) for each price field.',
            ]);
        }

        foreach (
            [
                'purchase_currency_id' => $purchaseCurrencyId,
                'sale_currency_id' => $saleCurrencyId,
                'wholesale_currency_id' => $wholesaleCurrencyId,
            ] as $field => $currencyId
        ) {
            $currency = $this->currencies->requireActive((int) $currencyId, $errorPrefix.'.'.$field);
            $code = strtoupper((string) $currency->code);
            if (! in_array($code, CatalogPriceConverter::SUPPORTED, true)) {
                throw ValidationException::withMessages([
                    $errorPrefix.'.'.$field => 'Pricing currency must be AFN, USD, or AED.',
                ]);
            }
        }

        $converted = $this->catalogPrices->convertIndependentSet(
            [
                'purchase' => [
                    'amount' => $variantData['purchase_amount'] ?? $variantData['purchase_price_afn'] ?? 0,
                    'currency' => (int) $purchaseCurrencyId,
                ],
                'sale' => [
                    'amount' => $variantData['sale_amount'] ?? $variantData['sale_price_afn'] ?? 0,
                    'currency' => (int) $saleCurrencyId,
                ],
                'wholesale' => [
                    'amount' => $variantData['wholesale_amount'] ?? $variantData['wholesale_price_afn'] ?? 0,
                    'currency' => (int) $wholesaleCurrencyId,
                ],
            ],
            $rateOverrides,
            2,
            $errorPrefix,
        );

        // Keep catalog_currency_id as the sale/display currency for legacy list/show consumers.
        $variantData['catalog_currency_id'] = $converted['sale']['currency_id'];
        $variantData['purchase_currency_id'] = $converted['purchase']['currency_id'];
        $variantData['sale_currency_id'] = $converted['sale']['currency_id'];
        $variantData['wholesale_currency_id'] = $converted['wholesale']['currency_id'];

        $variantData['purchase_amount'] = $converted['purchase']['amount'];
        $variantData['purchase_price_afn'] = $converted['purchase']['afn'];
        $variantData['purchase_amount_usd'] = $converted['purchase']['usd'];
        $variantData['purchase_amount_aed'] = $converted['purchase']['aed'];

        $variantData['sale_amount'] = $converted['sale']['amount'];
        $variantData['sale_price_afn'] = $converted['sale']['afn'];
        $variantData['sale_amount_usd'] = $converted['sale']['usd'];
        $variantData['sale_amount_aed'] = $converted['sale']['aed'];

        $variantData['wholesale_amount'] = $converted['wholesale']['amount'];
        $variantData['wholesale_price_afn'] = $converted['wholesale']['afn'];
        $variantData['wholesale_amount_usd'] = $converted['wholesale']['usd'];
        $variantData['wholesale_amount_aed'] = $converted['wholesale']['aed'];

        foreach ($converted['snapshot'] as $key => $value) {
            $variantData[$key] = $value;
        }

        return $variantData;
    }

    /**
     * Persist AFN/USD/AED catalog equivalents into product_prices for PriceResolver.
     * Reference prices only — never historical transaction amounts.
     *
     * @param  array<string, mixed>  $moneyMap
     */
    private function syncCatalogEquivalentPrices(Product $product, ProductVariant $variant, array $moneyMap = []): void
    {
        $currencyIds = Currency::query()
            ->notTrashed()
            ->whereIn('code', CatalogPriceConverter::SUPPORTED)
            ->where('status', 'active')
            ->get()
            ->keyBy(fn (Currency $currency) => strtoupper((string) $currency->code));

        if ($currencyIds->count() < 3) {
            return;
        }

        if ($moneyMap === []) {
            $moneyMap = $this->variantMoneyMap($variant);
        }

        $minQty = max(0, (int) ($moneyMap['wholesale_min_qty'] ?? 0));

        $matrix = [
            'purchase' => [
                'AFN' => $moneyMap['purchase_price_afn'] ?? null,
                'USD' => $moneyMap['purchase_amount_usd'] ?? null,
                'AED' => $moneyMap['purchase_amount_aed'] ?? null,
            ],
            'sale' => [
                'AFN' => $moneyMap['sale_price_afn'] ?? null,
                'USD' => $moneyMap['sale_amount_usd'] ?? null,
                'AED' => $moneyMap['sale_amount_aed'] ?? null,
            ],
            'wholesale' => [
                'AFN' => $moneyMap['wholesale_price_afn'] ?? null,
                'USD' => $moneyMap['wholesale_amount_usd'] ?? null,
                'AED' => $moneyMap['wholesale_amount_aed'] ?? null,
            ],
        ];

        foreach ($matrix as $priceType => $byCode) {
            foreach ($byCode as $code => $amount) {
                if ($amount === null || $amount === '') {
                    continue;
                }

                $currency = $currencyIds->get($code);
                if (! $currency) {
                    continue;
                }

                $qty = $priceType === 'wholesale' ? $minQty : 0;

                ProductPrice::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'price_type' => $priceType,
                        'currency_id' => $currency->id,
                        'min_qty' => $qty,
                    ],
                    [
                        'currency_code' => $currency->code,
                        'amount' => Money::round((string) $amount, 4),
                        'is_active' => true,
                        'valid_from' => null,
                        'valid_to' => null,
                    ]
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $variantData
     * @return array<string, mixed>
     */
    private function onlyVariantColumns(array $variantData): array
    {
        return array_intersect_key($variantData, array_flip([
            'product_id',
            'user_id',
            'name',
            'sku',
            'image',
            'volume_ml',
            'concentration',
            'trash',
        ]));
    }

    /**
     * @param  array<string, mixed>  $variantData
     * @return array<string, mixed>
     */
    private function moneyMapFromArray(array $variantData): array
    {
        return [
            'purchase_price_afn' => (float) ($variantData['purchase_price_afn'] ?? 0),
            'purchase_amount' => $variantData['purchase_amount'] ?? ($variantData['purchase_price_afn'] ?? 0),
            'purchase_amount_usd' => $variantData['purchase_amount_usd'] ?? null,
            'purchase_amount_aed' => $variantData['purchase_amount_aed'] ?? null,
            'purchase_currency_id' => $variantData['purchase_currency_id'] ?? null,
            'sale_price_afn' => (float) ($variantData['sale_price_afn'] ?? 0),
            'sale_amount' => $variantData['sale_amount'] ?? ($variantData['sale_price_afn'] ?? 0),
            'sale_amount_usd' => $variantData['sale_amount_usd'] ?? null,
            'sale_amount_aed' => $variantData['sale_amount_aed'] ?? null,
            'sale_currency_id' => $variantData['sale_currency_id'] ?? null,
            'wholesale_price_afn' => (float) ($variantData['wholesale_price_afn'] ?? 0),
            'wholesale_amount' => $variantData['wholesale_amount'] ?? ($variantData['wholesale_price_afn'] ?? 0),
            'wholesale_amount_usd' => $variantData['wholesale_amount_usd'] ?? null,
            'wholesale_amount_aed' => $variantData['wholesale_amount_aed'] ?? null,
            'wholesale_currency_id' => $variantData['wholesale_currency_id'] ?? null,
            'wholesale_min_qty' => (int) ($variantData['wholesale_min_qty'] ?? 0),
            'catalog_currency_id' => $variantData['catalog_currency_id'] ?? null,
        ];
    }

    private function syncSupplierInvoiceForBatch(
        int $supplierId,
        ProductBatch $batch,
        float $totalAfn,
        float $exchangeRate,
    ): void {
        $invoiceNo = mb_substr($batch->invoice_number ?: ('B-'.$batch->id), 0, 50);
        $date = optional($batch->purchase_date)->toDateString() ?: now()->toDateString();
        $existing = \App\Models\SupplierInvoice::query()
            ->where('supplier_id', $supplierId)
            ->where('invoice_no', $invoiceNo)
            ->whereDate('purchase_date', $date)
            ->first();
        $paid = min($totalAfn, round((float) ($existing->paid_afn ?? 0), 2));

        \App\Models\SupplierInvoice::query()->updateOrCreate(
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
                'created_by' => $existing->created_by ?? Auth::id(),
            ]
        );
    }

    private function applyPaidNowToBatch(
        ProductBatch $batch,
        int $supplierId,
        float $paidNow,
        float $exchangeRate = 0,
    ): void {
        if ($paidNow <= 0) {
            return;
        }

        $invoiceNo = mb_substr($batch->invoice_number ?: ('B-'.$batch->id), 0, 50);
        $invoice = \App\Models\SupplierInvoice::query()
            ->where('supplier_id', $supplierId)
            ->where('invoice_no', $invoiceNo)
            ->first();

        $invoice?->takePayment(
            $paidNow,
            optional($batch->purchase_date)->toDateString() ?: now()->toDateString(),
            $exchangeRate,
            'Paid on purchase',
            Auth::id(),
        );
    }

    /**
     * Active brands for product forms / filters (shared across index/create/edit).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Brand>
     */
    private function cachedActiveBrands()
    {
        return Cache::remember('products.active_brands', 300, function () {
            return Brand::notTrashed()
                ->active()
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * Snapshot board rates from the central exchange_rates table for purchase/catalog valuation.
     * Never silently falls back to 1:1 or an empty board — missing rates must fail loudly.
     *
     * @return array{usd_to_afn: string, aed_to_afn: string, usd_to_aed: string, source: string, effective_at?: string|null}
     */
    private function boardRateOverridesFromExchangeService(): array
    {
        try {
            $usd = $this->exchange->snapshot('USD', 'AFN');
            $aed = $this->exchange->snapshot('AED', 'AFN');
            $current = $this->exchange->getCurrentRates();

            $usdRate = (string) $usd['rate'];
            $aedRate = (string) $aed['rate'];
            $usdToAed = (string) $current['usd_to_aed'];

            if ((float) $usdRate <= 0 || (float) $aedRate <= 0 || (float) $usdToAed <= 0) {
                throw ValidationException::withMessages([
                    'exchange_rate' => 'Exchange board rates must be greater than zero. Post USD→AFN and AED→AFN on the Exchange Rates page.',
                ]);
            }

            return [
                'usd_to_afn' => $usdRate,
                'aed_to_afn' => $aedRate,
                'usd_to_aed' => $usdToAed,
                'source' => (string) ($usd['source'] ?? 'exchange_rates'),
                'effective_at' => $usd['effective_at'] ?? $aed['effective_at'] ?? null,
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to load exchange board rates for product valuation.', [
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'exchange_rate' => 'Could not load exchange rates from the exchange_rates table. Post rates on the Exchange Rates page and try again.',
            ]);
        }
    }

    /**
     * Convert a catalog money amount to AFN using catalog rates, falling back to board overrides.
     *
     * @param  array{usd_to_afn?: string|float|null, usd_to_aed?: string|float|null, aed_to_afn?: string|float|null, source?: string|null}  $rateOverrides
     */
    private function catalogAmountToLedger(
        string $amount,
        \App\Models\Currency $currency,
        string $field,
        array $rateOverrides = [],
    ): string {
        try {
            return $this->currencies->toLedger($amount, $currency);
        } catch (\Illuminate\Validation\ValidationException $catalogError) {
            if ($rateOverrides === [] || empty($rateOverrides['usd_to_afn'])) {
                throw $catalogError;
            }

            $ledger = \App\Models\Currency::ledger();
            $board = $this->currencies->boardAt(null, $rateOverrides, allowCatalogFallback: false);
            $eq = $board->equivalents(
                \App\Support\Money::round($amount, 4),
                strtoupper((string) $currency->code),
                (int) ($ledger?->decimal_places ?? 2),
            );

            return $eq['afn'];
        }
    }

    /**
     * Convert fixed investor profit to AFN using the transaction FxBoard when available.
     *
     * @param  array{usd_to_afn?: string|float|null, aed_to_afn?: string|float|null, source?: string|null}  $rateOverrides
     * @return array{afn: float, original: float, code: string, currency_id: int|null, usd: float, aed: float}
     */
    private function profitViaBoard(
        float $amount,
        mixed $currencyId,
        string $field,
        \Carbon\CarbonInterface|\DateTimeInterface|string|null $at = null,
        array $rateOverrides = [],
    ): array {
        $ledger = Currency::ledger();
        if ($amount <= 0) {
            return [
                'afn' => 0.0,
                'original' => 0.0,
                'code' => (string) ($ledger?->code ?? 'AFN'),
                'currency_id' => $ledger?->id,
                'usd' => 0.0,
                'aed' => 0.0,
            ];
        }

        $currency = ! empty($currencyId)
            ? $this->currencies->requireActive((int) $currencyId, $field)
            : $ledger;

        $board = $this->currencies->boardAt($at, $rateOverrides, allowCatalogFallback: false);
        $code = strtoupper((string) ($currency?->code ?? 'AFN'));
        $eq = $board->equivalents(\App\Support\Money::round((string) $amount, 4), $code, 4);

        return [
            'afn' => (float) \App\Support\Money::round($eq['afn'], 2),
            'original' => $amount,
            'code' => $code,
            'currency_id' => $currency?->id ?? $ledger?->id,
            'usd' => (float) $eq['usd'],
            'aed' => (float) $eq['aed'],
        ];
    }

    /** @deprecated Prefer profitViaBoard — kept for callers that only need AFN. */
    private function profitToLedger(float $amount, mixed $currencyId, string $field): float
    {
        return $this->profitViaBoard($amount, $currencyId, $field)['afn'];
    }

    /**
     * Sync per-currency product_prices rows for a variant.
     * Rows submitted with _delete / is_active=0 are deactivated (not hard-deleted).
     * Rows omitted from the form are left untouched.
     *
     * @param  list<array<string, mixed>>  $prices
     */
    private function syncVariantPrices(Product $product, ProductVariant $variant, array $prices): void
    {
        if ($prices === []) {
            return;
        }

        foreach ($prices as $row) {
            if (! is_array($row)) {
                continue;
            }

            $currencyId = (int) ($row['currency_id'] ?? 0);
            if ($currencyId <= 0) {
                continue;
            }

            $currency = Currency::query()->notTrashed()->find($currencyId);
            if (! $currency) {
                continue;
            }

            $priceType = (string) ($row['price_type'] ?? 'sale');
            if (! in_array($priceType, ['purchase', 'sale', 'wholesale'], true)) {
                $priceType = 'sale';
            }

            $minQty = max(0, (int) ($row['min_qty'] ?? 0));
            $delete = ! empty($row['_delete']) || (isset($row['is_active']) && filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN) === false && ! empty($row['id']));
            $isActive = $delete ? false : filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $amount = round((float) ($row['amount'] ?? 0), 4);

            if ($amount < 0) {
                continue;
            }

            ProductPrice::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'price_type' => $priceType,
                    'currency_id' => $currency->id,
                    'min_qty' => $minQty,
                ],
                [
                    'currency_code' => $currency->code,
                    'amount' => $amount,
                    'is_active' => $isActive,
                    'valid_from' => $row['valid_from'] ?? null,
                    'valid_to' => $row['valid_to'] ?? null,
                ]
            );
        }
    }

    private function syncBarcodes(ProductVariant $variant, array $barcodes): void
    {
        $existing = ProductBarcode::where('variant_id', $variant->id)
            ->get()
            ->keyBy('barcode');

        $incoming = collect($barcodes)
            ->filter(fn ($b) => is_array($b) && ! empty($b['barcode']))
            ->keyBy('barcode');

        foreach ($existing as $barcode => $row) {
            if (! $incoming->has($barcode)) {
                $row->forceDelete();
            }
        }

        $conflicts = [];
        foreach ($incoming as $barcode => $data) {
            if ($existing->has($barcode)) {
                continue;
            }

            $found = ProductBarcode::where('barcode', $barcode)
                ->where('variant_id', '!=', $variant->id)
                ->with(['variant.product.brand'])
                ->first();

            if ($found) {
                $brandName   = $found->variant?->product?->brand?->name;
                $variantName = $found->variant?->full_name;

                $usedBy = trim(
                    ($brandName ? $brandName . ' — ' : '') . ($variantName ?? '')
                );

                $conflicts[$barcode] = $usedBy ?: 'another variant';
            }
        }

        if (! empty($conflicts)) {
            $messages = [];
            foreach ($conflicts as $barcode => $usedBy) {
                $messages[] = "Barcode \"{$barcode}\" is already used by: {$usedBy}";
            }

            throw ValidationException::withMessages([
                'barcodes' => $messages,
            ]);
        }

        foreach ($incoming as $barcode => $data) {
            $payload = [
                'barcode_type'  => $data['barcode_type'] ?? 'EAN13',
                'is_primary'    => ! empty($data['is_primary']),
                'status'        => $data['status'] ?? 'active',
            ];

            if ($existing->has($barcode)) {
                // Inventory / batches are authoritative — never rewrite stock from product CRUD.
                $existing->get($barcode)->update($payload);
            } else {
                ProductBarcode::create(array_merge($payload, [
                    'variant_id'    => $variant->id,
                    'barcode'       => $barcode,
                    'current_stock' => 0,
                ]));
            }
        }
    }

    /**
     * Sync purchase batches + inventory for product update.
     *
     * @param  array{usd_to_afn?: string|float|null, aed_to_afn?: string|float|null, usd_to_aed?: string|float|null, source?: string|null}  $rateOverrides
     */
    private function syncPurchases(
        Product $product,
        array $purchasesData,
        array $variantIndexToId,
        array $variantDataMap = [],
        array $rateOverrides = [],
    ): void {
        $keptBatchIds = [];
        $branchId = $this->getBranchId();

        foreach ($purchasesData as $index => $purchaseData) {
            $variantIndex = $purchaseData['variant_index'] ?? null;
            $variantIndex = is_numeric($variantIndex) ? (int) $variantIndex : null;
            $variantId = $variantIndex !== null ? ($variantIndexToId[$variantIndex] ?? null) : null;
            if (! $variantId) {
                continue;
            }

            $billImage = null;
            if (request()->hasFile("purchases.{$index}.bill_image")) {
                $billImage = request()->file("purchases.{$index}.bill_image")->store('bills', 'public');
            }

            $batchId = $purchaseData['id'] ?? null;
            $profitCurrencyId = $purchaseData['profit_currency_id'] ?? null;
            $qty = (float) ($purchaseData['quantity'] ?? 1);
            $exchangeRate = (float) ($purchaseData['exchange_rate'] ?? 0);
            $purchaseDate = $purchaseData['purchase_date'] ?? now()->toDateString();
            $rateAt = \Illuminate\Support\Carbon::parse($purchaseDate)->endOfDay();

            $investorId = $purchaseData['investor_id'] ?? null;
            if (empty($investorId) || $investorId === 'store' || $investorId === 'none') {
                $investorId = null;
            }

            $supplierId = $purchaseData['supplier_id'] ?? null;
            if (empty($supplierId) || $supplierId === 'none') {
                $supplierId = null;
            }

            $variantInfo = $variantDataMap[$variantIndex] ?? $this->emptyVariantMoneyMap();
            if (($variantInfo['purchase_price_afn'] ?? 0) <= 0) {
                $variant = ProductVariant::find($variantId);
                if ($variant) {
                    $variantInfo = $this->variantMoneyMap($variant);
                }
            }

            $profitCurrencyId = $profitCurrencyId ?: ($variantInfo['purchase_currency_id'] ?? null);
            $profitSnap = $investorId
                ? $this->profitViaBoard(
                    (float) ($purchaseData['profit_per_unit'] ?? 0),
                    $profitCurrencyId,
                    "purchases.{$index}.profit_currency_id",
                    $rateAt,
                    $rateOverrides,
                )
                : $this->profitViaBoard(0, null, "purchases.{$index}.profit_currency_id");
            $profitUnit = $profitSnap['afn'];

            $money = $this->purchaseMoneyFromVariant(
                $variantInfo,
                (int) $qty,
                $exchangeRate > 0 ? (string) $exchangeRate : null,
                $rateAt,
                $rateOverrides,
            );
            $purchasePricePerUnit = (float) $money['unit_cost_afn'];
            $salePricePerUnit = (float) ($variantInfo['sale_price_afn'] ?? 0);
            $purchasePriceAfn = (float) $money['line_cost_afn'];
            $storeProfitUnit = round($salePricePerUnit - $purchasePricePerUnit - $profitUnit, 2);

            $investmentId = null;
            if ($investorId) {
                $existingBatch = $batchId ? ProductBatch::query()->find($batchId) : null;
                $investmentId = $existingBatch?->investment_id;
                if (! $investmentId) {
                    $investment = Investment::createFromPurchase([
                        'investor_id' => (int) $investorId,
                        'currency_id' => $money['purchase_currency_id'] ?? null,
                        'amount_currency' => $money['purchase_line_amount'] ?? $purchasePriceAfn,
                        'amount_afn' => $purchasePriceAfn,
                        'exchange_rate' => $money['rate_to_ledger'] ?? null,
                        'investment_date' => $purchaseDate,
                        'notes' => 'Auto-created on product update',
                        'created_by' => Auth::id(),
                    ]);
                    $investmentId = $investment->id;
                }
            }

            $existing = $batchId
                ? ProductBatch::query()->whereKey($batchId)->where('trash', false)->first()
                : null;

            if ($existing) {
                $oldQty = (int) $existing->quantity;
                $newQty = (int) $qty;
                $diff = $newQty - $oldQty;

                if ($billImage && $existing->bill_image && Storage::disk('public')->exists($existing->bill_image)) {
                    Storage::disk('public')->delete($existing->bill_image);
                }

                $this->updateProductBatch(
                    batch: $existing,
                    newQty: $newQty,
                    oldQty: $oldQty,
                    unitCostAfn: $purchasePricePerUnit,
                    unitCostCurrency: (float) $money['purchase_unit_amount'],
                    salePriceAfn: $salePricePerUnit,
                    exchangeRate: (float) $money['rate_to_ledger'],
                    profitPerUnitAfn: $profitUnit,
                    storeProfitPerUnit: $storeProfitUnit,
                    investmentId: $investmentId,
                    receivedDate: $purchaseDate,
                    invoiceNumber: $purchaseData['invoice_number'] ?? $existing->invoice_number,
                    purchaseDate: $purchaseDate,
                    paymentStatus: $purchaseData['payment_status'] ?? $existing->payment_status,
                    billImage: $billImage,
                    notes: $purchaseData['notes'] ?? $existing->notes,
                    purchaseCurrencyId: $money['purchase_currency_id'] ?? null,
                    purchaseAmountCurrency: isset($money['purchase_line_amount'])
                        ? (float) $money['purchase_line_amount']
                        : null,
                );
                $keptBatchIds[] = $existing->id;

                if ($diff > 0) {
                    Inventory::increase(
                        branchId: $branchId,
                        variantId: $variantId,
                        qty: $diff,
                        type: 'adjustment',
                        batchId: $existing->id,
                        notes: "Purchase updated: qty {$oldQty} → {$newQty}",
                        unitCostAfn: $purchasePricePerUnit
                    );
                } elseif ($diff < 0) {
                    Inventory::decrease(
                        branchId: $branchId,
                        variantId: $variantId,
                        qty: abs($diff),
                        type: 'adjustment',
                        batchId: $existing->id,
                        notes: "Purchase updated: qty {$oldQty} → {$newQty}"
                    );
                }
            } else {
                $batch = $this->createProductBatch(
                    branchId: $branchId,
                    variantId: $variantId,
                    investmentId: $investmentId,
                    qty: (int) $qty,
                    unitCostAfn: $purchasePricePerUnit,
                    unitCostCurrency: (float) $money['purchase_unit_amount'],
                    salePriceAfn: $salePricePerUnit,
                    exchangeRate: (float) $money['rate_to_ledger'],
                    profitPerUnitAfn: $profitUnit,
                    storeProfitPerUnit: $storeProfitUnit,
                    receivedDate: $purchaseDate,
                    invoiceNumber: $purchaseData['invoice_number'] ?? null,
                    purchaseDate: $purchaseDate,
                    paymentStatus: $purchaseData['payment_status'] ?? ($supplierId ? 'unpaid' : 'paid'),
                    billImage: $billImage,
                    notes: $purchaseData['notes'] ?? null,
                    purchaseCurrencyId: $money['purchase_currency_id'] ?? null,
                    purchaseAmountCurrency: isset($money['purchase_line_amount'])
                        ? (float) $money['purchase_line_amount']
                        : null,
                );
                $keptBatchIds[] = $batch->id;

                if ($supplierId) {
                    $this->syncSupplierInvoiceForBatch(
                        (int) $supplierId,
                        $batch,
                        $purchasePriceAfn,
                        (float) ($money['rate_to_ledger'] ?? 0),
                    );
                    $this->applyPaidNowToBatch(
                        $batch,
                        (int) $supplierId,
                        (float) ($purchaseData['paid_now'] ?? 0),
                        (float) ($money['rate_to_ledger'] ?? 0),
                    );
                }

                Inventory::increase(
                    branchId: $branchId,
                    variantId: $variantId,
                    qty: (int) $qty,
                    type: 'purchase',
                    batchId: $batch->id,
                    referenceType: ProductBatch::class,
                    referenceId: $batch->id,
                    notes: "New purchase — {$product->name}",
                    unitCostAfn: $purchasePricePerUnit
                );
            }
        }

        $variantIds = array_values($variantIndexToId);
        if ($variantIds !== []) {
            ProductBatch::query()
                ->whereIn('variant_id', $variantIds)
                ->where('trash', false)
                ->when($keptBatchIds !== [], fn ($q) => $q->whereNotIn('id', $keptBatchIds))
                ->when($keptBatchIds === [], fn ($q) => $q)
                ->get()
                ->each(function (ProductBatch $batch) use ($branchId) {
                    if ((int) $batch->remaining_quantity > 0) {
                        Inventory::decrease(
                            branchId: $branchId,
                            variantId: (int) $batch->variant_id,
                            qty: (int) $batch->remaining_quantity,
                            type: 'adjustment',
                            batchId: $batch->id,
                            notes: "Purchase removed (remaining: {$batch->remaining_quantity})"
                        );
                    }
                    $batch->update(['trash' => true, 'user_id' => Auth::id()]);
                });
        }
    }

    /** @deprecated Use applyPaidNowToBatch */
    private function applyPaidNow(ProductBatch $purchase, ?int $supplierId, float $paidNow, float $exchangeRate = 0): void
    {
        if ($paidNow <= 0 || ! $supplierId) {
            return;
        }

        $invoiceNo = mb_substr($purchase->invoice_number ?: ('P-'.$purchase->id), 0, 50);
        $invoice = \App\Models\SupplierInvoice::query()
            ->where('supplier_id', $supplierId)
            ->where('invoice_no', $invoiceNo)
            ->first();

        $invoice?->takePayment(
            $paidNow,
            optional($purchase->purchase_date)->toDateString() ?: now()->toDateString(),
            $exchangeRate,
            'Paid on purchase',
            Auth::id(),
        );
    }

    private function notifySuperAdmins(string $title, string $body, string $url): void
    {
        if (Auth::user()?->isSuperAdmin()) {
            return;
        }

        $now = now();

        User::query()
            ->notTrashed()
            ->where('status', 'active')
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->pluck('id')
            ->each(function (int $userId) use ($title, $body, $url, $now) {
                ActivityNotification::create([
                    'user_id'    => $userId,
                    'section'    => 'products',
                    'title'      => $title,
                    'body'       => $body,
                    'url'        => $url,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    /** Inventories is the stock source of truth — no variant cache to refresh. */
    private function refreshVariantStock(Product $product): void
    {
        // no-op
    }

    /**
     * Map product_prices onto familiar catalog money keys for Inertia UI.
     */
    private function attachVariantCatalogMoney(ProductVariant $variant): void
    {
        $money = $this->variantMoneyMap($variant);
        foreach ($money as $key => $value) {
            $variant->setAttribute($key, $value);
        }
    }

    /**
     * Map batch cost/FX/profit onto a purchase shell for Inertia UI.
     */
    private function attachPurchaseBatchMoney(Product $row, ?ProductBatch $batch): void
    {
        $row->setAttribute('variant_id', $batch?->variant_id);
        $row->setAttribute('investor_id', $batch?->investor_id);
        $row->setAttribute('purchase_price_afn', $batch?->total_cost_afn);
        $row->setAttribute('unit_cost_afn', $batch?->unit_cost_afn);
        $row->setAttribute('total_cost_afn', $batch?->total_cost_afn);
        $row->setAttribute('profit_per_unit', $batch?->profit_per_unit_afn);
        $row->setAttribute('store_profit_per_unit', $batch?->store_profit_per_unit_afn);
        $row->setAttribute('sale_price_afn', $batch?->sale_price_afn);
        $row->setAttribute('exchange_rate', $batch?->exchange_rate);
        $row->setAttribute('purchase_currency_id', $batch?->purchase_currency_id);
        $row->setAttribute('purchase_amount_currency', $batch?->purchase_amount_currency);
        $row->setAttribute('purchase_unit_amount', $batch?->unit_cost_currency);
        $row->setAttribute('profit_currency_id', $batch?->purchase_currency_id);
        $row->setRelation('variant', $batch?->variant);
        $row->setRelation('investor', $batch?->investment?->investor);
    }

    /**
     * Purchase batches linked to catalog variants (source of truth after schema slim-down).
     *
     * @param  \Illuminate\Support\Collection<int, int>|array<int, int>  $variantIds
     * @return \Illuminate\Support\Collection<int, ProductBatch>
     */
    private function purchasesForVariants($variantIds, array $with = [])
    {
        $ids = collect($variantIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return ProductBatch::query()
            ->whereIn('variant_id', $ids->all())
            ->where('trash', false)
            ->with($with)
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get();
    }
}
