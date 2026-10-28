<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ExchangeRateHistory;
use App\Models\ProductBarcode;
use App\Models\ProductPriceHistory;
use App\Models\ActivityNotification;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Currency;
use App\Services\CurrencyService;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    ) {}

    /**
     * Branch ID از کاربر — با چک وجود شعبه و fallback امن
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
                'branch_id' => 'هیچ شعبه‌ای در سیستم وجود ندارد. لطفاً ابتدا یک شعبه ایجاد کنید.',
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
            ->whereNull('variant_id')
            ->with([
                'brand:id,name',
                'variants' => fn ($q) => $q
                    ->notTrashed()
                    ->select(
                        'id', 'product_id',
                        'volume_ml', 'concentration',
                        'stock_quantity', 'sale_price_afn',
                        'wholesale_price_afn', 'wholesale_min_qty'
                    )
                    ->with([
                        'barcodes:id,variant_id,barcode,barcode_type,is_primary,status,current_stock',
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

        $avgRatesByVariant = Product::query()
            ->whereIn('variant_id', $variantIds)
            ->whereNotNull('exchange_rate')
            ->where('exchange_rate', '>', 0)
            ->where('trash', false)
            ->selectRaw('variant_id, ROUND(AVG(exchange_rate), 2) as avg_exchange_rate')
            ->groupBy('variant_id')
            ->pluck('avg_exchange_rate', 'variant_id');

        $products->getCollection()->each(function ($product) use ($avgRatesByVariant) {
            $rates = $product->variants
                ->map(fn ($variant) => $avgRatesByVariant->get($variant->id))
                ->filter(fn ($rate) => $rate !== null && (float) $rate > 0)
                ->map(fn ($rate) => (float) $rate)
                ->values();

            $product->setAttribute(
                'exchange_rate',
                $rates->isNotEmpty() ? round($rates->avg(), 2) : null
            );
        });

        $monthlyRates = ExchangeRateHistory::query()
            ->selectRaw('YEAR(rate_date) as year, MONTH(rate_date) as month, ROUND(AVG(usd_to_afn), 2) as average_rate, COUNT(*) as days')
            ->groupByRaw('YEAR(rate_date), MONTH(rate_date)')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(6)
            ->get();

        return Inertia::render('products/index', [
            'products' => $products,
            'monthlyRates' => $monthlyRates,
            'brands'   => Brand::notTrashed()
                ->active()
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
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
            'brands' => Brand::notTrashed()
                ->active()
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),

            'investors' => \App\Models\Investor::query()
                ->select('id', 'full_name as name')
                ->orderBy('full_name')
                ->get(),

            'suppliers' => \App\Models\Supplier::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'currencies' => Currency::formOptions(),
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
            ->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'name' => trim(($variant->product?->brand?->name ? $variant->product->brand->name.' — ' : '').($variant->product?->name ?? 'Product')),
                'volume_ml' => $variant->volume_ml,
                'concentration' => $variant->concentration,
                'purchase_price_afn' => $variant->purchase_price_afn,
                'sale_price_afn' => $variant->sale_price_afn,
                'stock_quantity' => $variant->stock_quantity,
                'barcodes' => $variant->barcodes->pluck('barcode')->filter()->values(),
            ]);

        return Inertia::render('products/receive', [
            'variants' => $variants,
            'investors' => \App\Models\Investor::query()
                ->select('id', 'full_name as name')
                ->orderBy('full_name')
                ->get(),
            'investments' => \App\Models\Investment::query()
                ->withAvailableCapital()
                ->with('investor:id,full_name')
                ->orderByDesc('investment_date')
                ->get()
                ->map(fn (\App\Models\Investment $investment) => [
                    'id' => $investment->id,
                    'investment_number' => $investment->investment_number,
                    'investor_id' => $investment->investor_id,
                    'investor_name' => $investment->investor?->full_name,
                    'available_amount_afn' => $investment->available_amount_afn,
                    'capital_amount_afn' => $investment->capital_amount_afn,
                    'status' => $investment->status,
                ]),
            'suppliers' => \App\Models\Supplier::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
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
            'variant' => [
                'id' => $variant->id,
                'name' => trim(($variant->product?->brand?->name ? $variant->product->brand->name.' — ' : '').($variant->product?->name ?? 'Product')),
                'volume_ml' => $variant->volume_ml,
                'concentration' => $variant->concentration,
                'purchase_price_afn' => $variant->purchase_price_afn,
                'sale_price_afn' => $variant->sale_price_afn,
                'stock_quantity' => $variant->stock_quantity,
                'barcodes' => $variant->barcodes->pluck('barcode')->filter()->values(),
            ],
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

        $this->getBranchId();

        $this->purchases->receiveStock($data, $request->user(), $this->getBranchId());

        return redirect()->route('products.index')->with('success', 'New stock was added.');
    }

    /* =========================================================
     |  STORE — ✅ با محاسبه سود دوکان
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

            unset($data['variants'], $data['purchases'], $data['image']);

            /* ═══ 1) Create main product ═══ */
            $product = Product::create($data);

            /* ═══ 2) Create variants + barcodes ═══ */
            $variantIds     = [];
            $variantDataMap = [];

            foreach ($variantsData as $index => $variantData) {
                $barcodes = $variantData['barcodes'] ?? [];
                unset($variantData['barcodes']);

                $variantData = $this->normalizeVariantData($variantData);
                $variantData = $this->applyVariantMoney($variantData, "variants.{$index}");
                $variantData['product_id'] = $product->id;
                $variantData['user_id']    = Auth::id();

                $variant = ProductVariant::create($variantData);
                $variantIds[$index] = $variant->id;

                // ✅ اضافه شد: sale_price_afn در map
                $variantDataMap[$index] = [
                    'purchase_price_afn'  => (float) $variant->purchase_price_afn,
                    'wholesale_price_afn' => (float) $variant->wholesale_price_afn,
                    'wholesale_min_qty'   => (int) $variant->wholesale_min_qty,
                    'sale_price_afn'      => (float) $variant->sale_price_afn,
                ];

                $this->syncBarcodes($variant, $barcodes);
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

                $qty          = (float) $product->quantityToBaseUnits(
                    (int) ($purchaseData['quantity'] ?? 1),
                    (string) ($purchaseData['quantity_unit'] ?? 'base')
                );
                $exchangeRate = (float) ($purchaseData['exchange_rate'] ?? 0);
                $profitUnit   = $this->profitToLedger(
                    (float) ($purchaseData['profit_per_unit'] ?? 0),
                    $purchaseData['profit_currency_id'] ?? null,
                    "purchases.{$index}.profit_currency_id"
                );

                $investorId = $purchaseData['investor_id'] ?? null;
                if (empty($investorId) || $investorId === 'store' || $investorId === 'none') {
                    $investorId = null;
                    $profitUnit = 0;  // ✅ store-owned → investor profit = 0
                }

                $supplierId = $purchaseData['supplier_id'] ?? null;
                if (empty($supplierId) || $supplierId === 'none') {
                    $supplierId = null;
                }

                $variantInfo = $variantDataMap[$variantIndex] ?? [
                    'purchase_price_afn'  => 0,
                    'wholesale_price_afn' => 0,
                    'wholesale_min_qty'   => 0,
                    'sale_price_afn'      => 0,
                ];

                $purchasePricePerUnit = (float) $variantInfo['purchase_price_afn'];
                $salePricePerUnit     = (float) ($variantInfo['sale_price_afn'] ?? 0);
                $purchasePriceAfn     = $purchasePricePerUnit * $qty;

                /* ✅ محاسبه سودها */
                $lineProfit      = $profitUnit * $qty;  // سود investor
                $storeProfitUnit = max(0, $salePricePerUnit - $purchasePricePerUnit - $profitUnit);
                $lineStoreProfit = $storeProfitUnit * $qty;

                /* ═══ Purchase Row در products ═══ */
                $purchaseRow = Product::create([
                    'name'        => $product->name,
                    'brand_id'    => $product->brand_id,
                    'gender'      => $product->gender,
                    'status'      => $product->status,
                    'description' => $product->description,
                    'user_id'     => Auth::id(),
                    'trash'       => false,

                    'variant_id'         => $variantId,
                    'investor_id'        => $investorId,
                    'supplier_id'        => $supplierId,
                    'invoice_number'     => $purchaseData['invoice_number'] ?? null,
                    'purchase_date'      => $purchaseData['purchase_date'] ?? now()->toDateString(),
                    'exchange_rate'      => $exchangeRate > 0 ? $exchangeRate : null,
                    'quantity'           => (int) $qty,
                    'remaining_quantity' => (int) $qty,

                    'purchase_price_afn' => $purchasePriceAfn,

                    'wholesale_price_afn' => $variantInfo['wholesale_price_afn'],
                    'wholesale_min_qty'   => $variantInfo['wholesale_min_qty'],

                    // ✅ Investor profit
                    'profit_per_unit' => $profitUnit,
                    'line_total'      => $purchasePriceAfn,
                    'line_profit'     => $lineProfit,

                    // ✅ Store profit (جدید)
                    'store_profit_per_unit' => $storeProfitUnit,
                    'line_store_profit'     => $lineStoreProfit,

                    'bill_image'     => $billImage,
                    'notes'          => $purchaseData['notes'] ?? null,
                    'payment_status' => $purchaseData['payment_status'] ?? 'unpaid',
                ]);
                $this->applyPaidNow($purchaseRow, (float) ($purchaseData['paid_now'] ?? 0));

                /* ═══ ساخت ProductBatch با فیلدهای جدید ═══ */
                $this->createProductBatch(
                    branchId:           $branchId,
                    variantId:          $variantId,
                    purchaseRowId:      $purchaseRow->id,
                    investorId:         $investorId,
                    qty:                (int) $qty,
                    unitCostAfn:        $purchasePricePerUnit,
                    salePriceAfn:       $salePricePerUnit,        // ✅ جدید
                    exchangeRate:       $exchangeRate > 0 ? $exchangeRate : 1,
                    profitPerUnit:      $profitUnit,              // سود investor per unit
                    storeProfitPerUnit: $storeProfitUnit,         // ✅ جدید
                    receivedDate:       $purchaseData['purchase_date'] ?? now()->toDateString(),
                );

                $this->rememberDailyRate(
                    $purchaseData['purchase_date'] ?? now()->toDateString(),
                    $exchangeRate
                );

                Inventory::increase(
                    branchId: $branchId,
                    variantId: $variantId,
                    qty: (int) $qty,
                    type: 'purchase',
                    batchId: null,
                    referenceType: Product::class,
                    referenceId: $purchaseRow->id,
                    notes: "New purchase — {$product->name}",
                    unitCostAfn: $purchasePricePerUnit
                );
            }

            $this->refreshVariantStock($product);

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
                ]),
        ]);

        $purchases = Product::whereNotNull('variant_id')
            ->where('name', $product->name)
            ->where('brand_id', $product->brand_id)
            ->where('trash', false)
            ->with([
                'variant:id,product_id,volume_ml,concentration',
                'investor:id,full_name',
                'supplier:id,name',
            ])
            ->orderByDesc('purchase_date')
            ->get();

        $invoices = \App\Models\SupplierInvoice::query()
            ->whereIn('supplier_id', $purchases->pluck('supplier_id')->filter()->unique())
            ->get();

        $purchases->each(function (Product $row) use ($invoices) {
            $number = mb_substr($row->invoice_number ?: ('P-'.$row->id), 0, 50);
            $invoice = $invoices->first(fn ($item) => (int) $item->supplier_id === (int) $row->supplier_id && $item->invoice_no === $number);
            $row->setAttribute('invoice_id', $invoice?->id);
            $row->setAttribute('invoice_paid_afn', $invoice ? (float) $invoice->paid_afn : 0);
            $row->setAttribute('invoice_due_afn', $invoice ? (float) $invoice->due_afn : 0);
        });

        return Inertia::render('products/show', [
            'product'   => $product,
            'purchases' => $purchases,
        ]);
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
                ]),
        ]);

        $purchases = Product::whereNotNull('variant_id')
            ->where('name', $product->name)
            ->where('brand_id', $product->brand_id)
            ->where('trash', false)
            ->orderByDesc('purchase_date')
            ->get();

        return Inertia::render('products/edit', [
            'product'   => $product,
            'purchases' => $purchases,
            'brands'    => Brand::notTrashed()
                ->active()
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'investors' => \App\Models\Investor::query()
                ->select('id', 'full_name as name')
                ->orderBy('full_name')
                ->get(),
            'suppliers' => \App\Models\Supplier::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'currencies' => Currency::formOptions(
                $product->variants->flatMap(fn ($variant) => [
                    $variant->purchase_currency_id,
                    $variant->sale_currency_id,
                    $variant->wholesale_currency_id,
                ])->all()
            ),
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

            unset($data['variants'], $data['purchases'], $data['image']);

            $product->update($data);

            $variantIndexToId = [];
            $variantDataMap   = [];

            foreach ($variantsData as $index => $variantData) {
                $barcodes = $variantData['barcodes'] ?? [];
                unset($variantData['barcodes']);

                $variantId = $variantData['id'] ?? null;
                unset($variantData['id']);

                $variantData = $this->normalizeVariantData($variantData);
                $variantData = $this->applyVariantMoney($variantData, "variants.{$index}");

                if ($variantId) {
                    $variant = ProductVariant::where('id', $variantId)
                        ->where('product_id', $product->id)
                        ->first();

                    if ($variant) {
                        $oldPurchasePrice = (float) $variant->purchase_price_afn;
                        $oldSalePrice     = (float) $variant->sale_price_afn;

                        $variantData['user_id'] = Auth::id();
                        $variant->update($variantData);

                        $variantIndexToId[$index] = $variant->id;

                        $this->syncBarcodes($variant, $barcodes);

                        $newPurchasePrice = (float) ($variantData['purchase_price_afn'] ?? $oldPurchasePrice);
                        $newSalePrice     = (float) ($variantData['sale_price_afn'] ?? $oldSalePrice);

                        $this->recordPriceDropLoss($variant, $oldPurchasePrice, $newPurchasePrice);

                        $this->recordPriceChange(
                            variant: $variant,
                            oldPurchase: $oldPurchasePrice,
                            newPurchase: $newPurchasePrice,
                            oldSale: $oldSalePrice,
                            newSale: $newSalePrice,
                            reason: 'Product update'
                        );

                        // ✅ sale_price_afn اضافه شد
                        $variantDataMap[$index] = [
                            'purchase_price_afn'  => (float) $variant->purchase_price_afn,
                            'wholesale_price_afn' => (float) $variant->wholesale_price_afn,
                            'wholesale_min_qty'   => (int) $variant->wholesale_min_qty,
                            'sale_price_afn'      => (float) $variant->sale_price_afn,
                        ];
                    }
                } else {
                    $variantData['product_id'] = $product->id;
                    $variantData['user_id']    = Auth::id();

                    $variant = ProductVariant::create($variantData);
                    $variantIndexToId[$index] = $variant->id;

                    $this->syncBarcodes($variant, $barcodes);

                    $variantDataMap[$index] = [
                        'purchase_price_afn'  => (float) $variant->purchase_price_afn,
                        'wholesale_price_afn' => (float) $variant->wholesale_price_afn,
                        'wholesale_min_qty'   => (int) $variant->wholesale_min_qty,
                        'sale_price_afn'      => (float) $variant->sale_price_afn,
                    ];
                }
            }

            ProductVariant::where('product_id', $product->id)
                ->whereNotIn('id', array_values($variantIndexToId))
                ->update(['trash' => true, 'user_id' => Auth::id()]);

            $this->syncPurchases($product, $purchasesData, $variantIndexToId, $variantDataMap);

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
                $purchaseIds = Product::whereIn('variant_id', $variantIds)
                    ->whereNotNull('variant_id')
                    ->pluck('id')
                    ->all();

                Product::whereIn('id', $purchaseIds)
                    ->update(['trash' => true, 'user_id' => Auth::id()]);

                ProductBatch::whereIn('purchase_item_id', $purchaseIds)
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
        $product = Product::onlyTrash()->whereNull('variant_id')->findOrFail($id);

        DB::transaction(function () use ($product) {
            $product->update(['trash' => false, 'user_id' => Auth::id()]);
            $product->variants()->update(['trash' => false, 'user_id' => Auth::id()]);

            $variantIds = $product->variants()->pluck('id')->all();
            if (! empty($variantIds)) {
                $purchaseIds = Product::whereIn('variant_id', $variantIds)
                    ->whereNotNull('variant_id')
                    ->pluck('id')
                    ->all();

                Product::whereIn('id', $purchaseIds)
                    ->update(['trash' => false, 'user_id' => Auth::id()]);

                ProductBatch::whereIn('purchase_item_id', $purchaseIds)
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
            ->whereNull('variant_id')
            ->with(['variants.barcodes', 'variants'])
            ->findOrFail($id);

        DB::transaction(function () use ($product) {
            $variantIds = $product->variants->pluck('id')->all();

            $purchaseRows = Product::whereIn('variant_id', $variantIds)
                ->whereNotNull('variant_id')
                ->get();

            $purchaseIds = $purchaseRows->pluck('id')->all();

            foreach ($purchaseRows as $row) {
                if ($row->bill_image && Storage::disk('public')->exists($row->bill_image)) {
                    Storage::disk('public')->delete($row->bill_image);
                }
            }

            Inventory::whereIn('variant_id', $variantIds)->forceDelete();

            ProductPriceHistory::whereIn('variant_id', $variantIds)->forceDelete();

            ProductBatch::whereIn('purchase_item_id', $purchaseIds)->forceDelete();
            ProductBatch::whereIn('variant_id', $variantIds)->forceDelete();

            ProductBarcode::whereIn('variant_id', $variantIds)->forceDelete();

            Product::whereIn('id', $purchaseIds)->forceDelete();

            ProductVariant::where('product_id', $product->id)->forceDelete();
            $product->forceDelete();
        });

        return redirect()
            ->route('products.trash')
            ->with('success', 'Product was permanently deleted.');
    }

   public function trash(): Response
{
    $products = Product::onlyTrash()          // ✅ اصلاح شد (قبلاً trashed بود)
        ->whereNull('variant_id')
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

    private function profitAfnToUsd(float $profitAfn, float $exchangeRate, mixed $investorId): float
    {
        if (empty($investorId) || $exchangeRate <= 0) {
            return 0;
        }

        return round($profitAfn / $exchangeRate, 2);
    }

    /**
     * ⭐ ساخت ProductBatch از یک purchase row — ✅ نسخه با سود دوکان
     */
    private function createProductBatch(
        int $branchId,
        int $variantId,
        int $purchaseRowId,
        ?int $investorId,
        int $qty,
        float $unitCostAfn,
        float $salePriceAfn,           // ✅ جدید
        float $exchangeRate,
        float $profitPerUnit,          // سود investor per unit (AFN)
        float $storeProfitPerUnit,     // ✅ جدید — سود دوکان per unit
        string $receivedDate
    ): ProductBatch {
        unset($exchangeRate);

        if (! Branch::where('id', $branchId)->exists()) {
            throw ValidationException::withMessages([
                'branch_id' => "Branch with ID {$branchId} does not exist.",
            ]);
        }

        $totalCostAfn = $unitCostAfn * $qty;

        return ProductBatch::create([
            'branch_id'                  => $branchId,
            'variant_id'                 => $variantId,
            'purchase_item_id'           => $purchaseRowId,
            'investor_id'                => $investorId,
            'batch_code'                 => ProductBatch::generateBatchCode(),

            'quantity'                   => $qty,
            'remaining_quantity'         => $qty,

            'unit_cost_afn'              => $unitCostAfn,
            'sale_price_afn'             => $salePriceAfn,           // ✅ جدید
            'total_cost_afn'             => $totalCostAfn,

            'profit_per_unit_afn'        => $profitPerUnit,          // سود investor per unit
            'store_profit_per_unit_afn'  => $storeProfitPerUnit,     // ✅ جدید

            'received_date'              => $receivedDate,
            'status'                     => 'active',
            'user_id'                    => Auth::id(),
            'trash'                      => false,
        ]);
    }

    /**
     * ⭐ آپدیت ProductBatch وقتی purchase تغییر کرد — ✅ نسخه با سود دوکان
     */
    private function updateProductBatchForPurchase(
        int $purchaseRowId,
        int $variantId,
        int $branchId,
        int $newQty,
        int $oldQty,
        float $unitCostAfn,
        float $salePriceAfn,           // ✅ جدید
        float $exchangeRate,
        float $profitPerUnit,
        float $storeProfitPerUnit,     // ✅ جدید
        ?int $investorId,
        string $receivedDate
    ): void {
        $batch = ProductBatch::where('purchase_item_id', $purchaseRowId)->first();

        if (! $batch) {
            if ($newQty > 0) {
                $this->createProductBatch(
                    branchId:           $branchId,
                    variantId:          $variantId,
                    purchaseRowId:      $purchaseRowId,
                    investorId:         $investorId,
                    qty:                $newQty,
                    unitCostAfn:        $unitCostAfn,
                    salePriceAfn:       $salePriceAfn,
                    exchangeRate:       $exchangeRate,
                    profitPerUnit:      $profitPerUnit,
                    storeProfitPerUnit: $storeProfitPerUnit,
                    receivedDate:       $receivedDate,
                );
            }
            return;
        }

        $diff         = $newQty - $oldQty;
        $newRemaining = max(0, $batch->remaining_quantity + $diff);

        unset($exchangeRate);

        $batch->update([
            'quantity'                   => $newQty,
            'remaining_quantity'         => $newRemaining,
            'investor_id'                => $investorId,
            'unit_cost_afn'              => $unitCostAfn,
            'sale_price_afn'             => $salePriceAfn,           // ✅ جدید
            'total_cost_afn'             => $unitCostAfn * $newQty,
            'profit_per_unit_afn'        => $profitPerUnit,
            'store_profit_per_unit_afn'  => $storeProfitPerUnit,     // ✅ جدید
            'status'                     => $newRemaining > 0 ? 'active' : 'depleted',
            'user_id'                    => Auth::id(),
        ]);
    }

    /**
     * ⭐ ثبت ضرر ناشی از افت قیمت خرید
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

        Expense::create([
            'branch_id'      => $this->getBranchId(),
            'expense_number' => Expense::generateExpenseNumber(),
            'title'          => 'Price drop loss',
            'category'       => 'other',
            'amount_afn'     => $loss,
            'expense_date'   => now()->toDateString(),
            'payment_method' => 'other',
            'description'    => "{$name}: purchase price fell from {$oldPurchase} to {$newPurchase} AFN on {$qty} pieces still in stock.",
            'created_by'     => Auth::id(),
            'user_id'        => Auth::id(),
            'trash'          => false,
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
        $defaults = [
            'volume_ml'           => null,
            'concentration'       => null,
            'purchase_price_afn'  => 0,
            'sale_price_afn'      => 0,
            'wholesale_price_afn' => 0,
            'wholesale_min_qty'   => 0,
            'profit_per_unit'     => 0,
            'stock_quantity'      => 0,
            'minimum_stock'       => 0,
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

        foreach ([
            'volume_ml', 'purchase_price_afn',
            'sale_price_afn', 'wholesale_price_afn', 'wholesale_min_qty',
            'profit_per_unit', 'stock_quantity', 'minimum_stock',
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
     * Store entered amounts in their currencies and write AFN ledger snapshots.
     *
     * @param  array<string, mixed>  $variantData
     * @return array<string, mixed>
     */
    private function applyVariantMoney(array $variantData, string $errorPrefix): array
    {
        $pairs = [
            'purchase' => ['amount' => 'purchase_amount', 'currency' => 'purchase_currency_id', 'ledger' => 'purchase_price_afn'],
            'sale' => ['amount' => 'sale_amount', 'currency' => 'sale_currency_id', 'ledger' => 'sale_price_afn'],
            'wholesale' => ['amount' => 'wholesale_amount', 'currency' => 'wholesale_currency_id', 'ledger' => 'wholesale_price_afn'],
        ];

        foreach ($pairs as $pair) {
            $entered = $variantData[$pair['amount']] ?? $variantData[$pair['ledger']] ?? 0;
            $variantData[$pair['amount']] = $entered === '' || $entered === null ? 0 : $entered;
            $currencyId = $variantData[$pair['currency']] ?? null;

            if (empty($currencyId)) {
                $variantData[$pair['ledger']] = $variantData[$pair['amount']];
                $variantData[$pair['currency']] = null;

                continue;
            }

            $currency = $this->currencies->requireActive((int) $currencyId, $errorPrefix.'.'.$pair['currency']);
            $variantData[$pair['ledger']] = $this->currencies->toLedger((string) $variantData[$pair['amount']], $currency);
            $variantData[$pair['currency']] = $currency->id;
        }

        return $variantData;
    }

    private function profitToLedger(float $amount, mixed $currencyId, string $field): float
    {
        if ($amount <= 0 || empty($currencyId)) {
            return $amount;
        }

        $currency = $this->currencies->requireActive((int) $currencyId, $field);

        return (float) $this->currencies->toLedger((string) $amount, $currency);
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
                'current_stock' => (int) ($data['current_stock'] ?? 0),
            ];

            if ($existing->has($barcode)) {
                $existing->get($barcode)->update($payload);
            } else {
                ProductBarcode::create(array_merge($payload, [
                    'variant_id' => $variant->id,
                    'barcode'    => $barcode,
                ]));
            }
        }
    }

    /**
     * ⭐ Sync purchases + batch + inventory — ✅ با سود دوکان
     */
    private function syncPurchases(
        Product $product,
        array $purchasesData,
        array $variantIndexToId,
        array $variantDataMap = []
    ): void {
        $existingIds = [];
        $branchId    = $this->getBranchId();

        foreach ($purchasesData as $index => $purchaseData) {
            $variantIndex = $purchaseData['variant_index'] ?? null;
            $variantIndex = is_numeric($variantIndex) ? (int) $variantIndex : null;

            $variantId = $variantIndex !== null
                ? ($variantIndexToId[$variantIndex] ?? null)
                : null;

            if (! $variantId) {
                continue;
            }

            $billImage = null;
            if (request()->hasFile("purchases.{$index}.bill_image")) {
                $billImage = request()->file("purchases.{$index}.bill_image")
                    ->store('bills', 'public');
            }

            $purchaseId = $purchaseData['id'] ?? null;
            $quantityUnit = (string) ($purchaseData['quantity_unit'] ?? 'base');
            $profitCurrencyId = $purchaseData['profit_currency_id'] ?? null;
            unset(
                $purchaseData['id'],
                $purchaseData['variant_index'],
                $purchaseData['quantity_unit'],
                $purchaseData['profit_currency_id']
            );

            $qty          = (float) $product->quantityToBaseUnits((int) ($purchaseData['quantity'] ?? 1), $quantityUnit);
            $exchangeRate = (float) ($purchaseData['exchange_rate'] ?? 0);
            $profitUnit   = $this->profitToLedger(
                (float) ($purchaseData['profit_per_unit'] ?? 0),
                $profitCurrencyId,
                "purchases.{$index}.profit_currency_id"
            );

            $investorId = $purchaseData['investor_id'] ?? null;
            if (empty($investorId) || $investorId === 'store' || $investorId === 'none') {
                $investorId = null;
                $profitUnit = 0;  // ✅ store-owned
            }

            $supplierId = $purchaseData['supplier_id'] ?? null;
            if (empty($supplierId) || $supplierId === 'none') {
                $supplierId = null;
            }

            $variantInfo = $variantDataMap[$variantIndex] ?? [
                'purchase_price_afn'  => 0,
                'wholesale_price_afn' => 0,
                'wholesale_min_qty'   => 0,
                'sale_price_afn'      => 0,
            ];

            if (($variantInfo['purchase_price_afn'] ?? 0) <= 0) {
                $variant = ProductVariant::find($variantId);
                if ($variant) {
                    $variantInfo = [
                        'purchase_price_afn'  => (float) $variant->purchase_price_afn,
                        'wholesale_price_afn' => (float) $variant->wholesale_price_afn,
                        'wholesale_min_qty'   => (int) $variant->wholesale_min_qty,
                        'sale_price_afn'      => (float) $variant->sale_price_afn,
                    ];
                }
            }

            $purchasePricePerUnit = (float) $variantInfo['purchase_price_afn'];
            $salePricePerUnit     = (float) ($variantInfo['sale_price_afn'] ?? 0);
            $purchasePriceAfn     = $purchasePricePerUnit * $qty;

            // ✅ محاسبه سودها
            $lineProfit      = $profitUnit * $qty;
            $storeProfitUnit = max(0, $salePricePerUnit - $purchasePricePerUnit - $profitUnit);
            $lineStoreProfit = $storeProfitUnit * $qty;

            $rowData = [
                'name'        => $product->name,
                'brand_id'    => $product->brand_id,
                'gender'      => $product->gender,
                'status'      => $product->status,
                'description' => $product->description,
                'user_id'     => Auth::id(),

                'variant_id'         => $variantId,
                'investor_id'        => $investorId,
                'supplier_id'        => $supplierId,
                'invoice_number'     => $purchaseData['invoice_number'] ?? null,
                'purchase_date'      => $purchaseData['purchase_date'] ?? now()->toDateString(),
                'exchange_rate'      => $exchangeRate > 0 ? $exchangeRate : null,
                'quantity'           => (int) $qty,
                'remaining_quantity' => (int) $qty,

                'purchase_price_afn' => $purchasePriceAfn,

                'wholesale_price_afn' => $variantInfo['wholesale_price_afn'],
                'wholesale_min_qty'   => $variantInfo['wholesale_min_qty'],

                // ✅ Investor profit
                'profit_per_unit' => $profitUnit,
                'line_total'      => $purchasePriceAfn,
                'line_profit'     => $lineProfit,

                // ✅ Store profit (جدید)
                'store_profit_per_unit' => $storeProfitUnit,
                'line_store_profit'     => $lineStoreProfit,

                'notes'          => $purchaseData['notes'] ?? null,
                'payment_status' => $purchaseData['payment_status'] ?? 'unpaid',
            ];

            if ($billImage) {
                $rowData['bill_image'] = $billImage;
            }

            if ($purchaseId) {
                /* ═══ Update existing ═══ */
                $existing = Product::where('id', $purchaseId)
                    ->whereNotNull('variant_id')
                    ->first();

                if ($existing) {
                    $oldQty = (int) $existing->quantity;
                    $newQty = (int) $qty;
                    $diff   = $newQty - $oldQty;

                    if ($billImage && $existing->bill_image && Storage::disk('public')->exists($existing->bill_image)) {
                        Storage::disk('public')->delete($existing->bill_image);
                    }

                    $existing->update($rowData);
                    $this->rememberDailyRate(
                        $purchaseData['purchase_date'] ?? now()->toDateString(),
                        $exchangeRate
                    );
                    $existingIds[] = $existing->id;

                    /* ⭐ آپدیت ProductBatch با فیلدهای جدید */
                    $this->updateProductBatchForPurchase(
                        purchaseRowId:      $existing->id,
                        variantId:          $variantId,
                        branchId:           $branchId,
                        newQty:             $newQty,
                        oldQty:             $oldQty,
                        unitCostAfn:        $purchasePricePerUnit,
                        salePriceAfn:       $salePricePerUnit,        // ✅ جدید
                        exchangeRate:       $exchangeRate > 0 ? $exchangeRate : 1,
                        profitPerUnit:      $profitUnit,
                        storeProfitPerUnit: $storeProfitUnit,         // ✅ جدید
                        investorId:         $investorId,
                        receivedDate:       $purchaseData['purchase_date'] ?? now()->toDateString(),
                    );

                    /* ⭐ آپدیت inventory */
                    if ($diff !== 0) {
                        if ($diff > 0) {
                            Inventory::increase(
                                branchId: $branchId,
                                variantId: $variantId,
                                qty: $diff,
                                type: 'adjustment',
                                referenceType: Product::class,
                                referenceId: $existing->id,
                                notes: "Purchase updated: qty {$oldQty} → {$newQty}",
                                unitCostAfn: $purchasePricePerUnit
                            );
                        } else {
                            Inventory::decrease(
                                branchId: $branchId,
                                variantId: $variantId,
                                qty: abs($diff),
                                type: 'adjustment',
                                referenceType: Product::class,
                                referenceId: $existing->id,
                                notes: "Purchase updated: qty {$oldQty} → {$newQty}"
                            );
                        }
                    }
                }
            } else {
                /* ═══ Create new ═══ */
                $variant = ProductVariant::find($variantId);

                if ($variant) {
                    $oldPurchasePrice = (float) $variant->purchase_price_afn;
                    $newPurchasePrice = (float) $variantInfo['purchase_price_afn'];

                    if ($oldPurchasePrice > 0 && $oldPurchasePrice != $newPurchasePrice) {
                        $this->recordPriceChange(
                            variant:     $variant,
                            oldPurchase: $oldPurchasePrice,
                            newPurchase: $newPurchasePrice,
                            oldSale:     (float) $variant->sale_price_afn,
                            newSale:     (float) $variant->sale_price_afn,
                            reason:      'New purchase with different price',
                        );
                    }
                }

                $row = Product::create($rowData);
                $this->applyPaidNow($row, (float) ($purchaseData['paid_now'] ?? 0));
                $existingIds[] = $row->id;

                /* ⭐ ساخت ProductBatch با فیلدهای جدید */
                $this->createProductBatch(
                    branchId:           $branchId,
                    variantId:          $variantId,
                    purchaseRowId:      $row->id,
                    investorId:         $investorId,
                    qty:                (int) $qty,
                    unitCostAfn:        $purchasePricePerUnit,
                    salePriceAfn:       $salePricePerUnit,        // ✅ جدید
                    exchangeRate:       $exchangeRate > 0 ? $exchangeRate : 1,
                    profitPerUnit:      $profitUnit,
                    storeProfitPerUnit: $storeProfitUnit,         // ✅ جدید
                    receivedDate:       $purchaseData['purchase_date'] ?? now()->toDateString(),
                );

                $this->rememberDailyRate(
                    $purchaseData['purchase_date'] ?? now()->toDateString(),
                    $exchangeRate
                );

                Inventory::increase(
                    branchId: $branchId,
                    variantId: $variantId,
                    qty: (int) $qty,
                    type: 'purchase',
                    referenceType: Product::class,
                    referenceId: $row->id,
                    notes: "New purchase — {$product->name}",
                    unitCostAfn: $purchasePricePerUnit
                );
            }
        }

        /* ⭐ حذف purchase های حذف‌شده + batch + inventory */
        $variantIds = array_values($variantIndexToId);

        if (! empty($variantIds)) {
            Product::whereIn('variant_id', $variantIds)
                ->whereNotIn('id', $existingIds)
                ->get()
                ->each(function ($p) use ($branchId) {
                    if ((int) $p->remaining_quantity > 0) {
                        Inventory::decrease(
                            branchId: $branchId,
                            variantId: $p->variant_id,
                            qty: (int) $p->remaining_quantity,
                            type: 'adjustment',
                            referenceType: Product::class,
                            referenceId: $p->id,
                            notes: "Purchase removed (remaining: {$p->remaining_quantity})"
                        );
                    }

                    ProductBatch::where('purchase_item_id', $p->id)
                        ->update(['trash' => true, 'user_id' => Auth::id()]);

                    $p->update(['trash' => true, 'user_id' => Auth::id()]);
                });
        }
    }

    private function rememberDailyRate(?string $date, float $rate): void
    {
        if ($rate <= 0 || ! $date) {
            return;
        }

        ExchangeRateHistory::updateOrCreate(
            ['rate_date' => $date],
            ['usd_to_afn' => $rate, 'created_by' => Auth::id()]
        );
    }

    private function applyPaidNow(Product $purchase, float $paidNow): void
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

    /**
     * ⭐ Refresh cached stock_quantity
     */
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