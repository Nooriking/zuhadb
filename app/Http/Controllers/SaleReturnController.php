<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\ProductBarcode;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Services\CustomerLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SaleReturnController extends Controller
{
    /* =========================================================
     |  INDEX
     ========================================================= */

    public function index(Request $request): Response
    {
        $search    = $request->query('search');
        $branchId  = $request->query('branch_id');
        $status    = $request->query('status');
        $customerId = $request->query('customer_id');

        $saleReturns = SaleReturn::query()
            ->with([
                'sale:id,invoice_number',
                'branch:id,name,code',
                'customer:id,name,phone,current_due_afn',
                'creator:id,name',
            ])
            ->withCount('items')
            ->notTrashed()
            ->when($search, function ($q, $s) {
                $q->where(function ($q) use ($s) {
                    $q->where('return_number', 'like', "%{$s}%")
                      ->orWhereHas('sale', fn ($q) => $q->where('invoice_number', 'like', "%{$s}%"))
                      ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$s}%"));
                });
            })
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->when($customerId, fn ($q, $v) => $q->where('customer_id', $v))
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        /* ═══ Stats ═══ */
        $stats = [
            'total_returns'   => SaleReturn::notTrashed()->count(),
            'today_returns'   => SaleReturn::notTrashed()->whereDate('return_date', today())->count(),
            'total_amount'    => (float) SaleReturn::notTrashed()->completed()->sum('total_afn'),
            'today_amount'    => (float) SaleReturn::notTrashed()->completed()->whereDate('return_date', today())->sum('total_afn'),
        ];

        return Inertia::render('sale-returns/index', [
            'saleReturns' => $saleReturns,
            'branches'    => Branch::notTrashed()->select('id', 'name', 'code')->get(),
            'customers'   => Customer::notTrashed()->select('id', 'name', 'phone')->orderBy('name')->get(),
            'filters'     => [
                'search'      => $search,
                'branch_id'   => $branchId,
                'status'      => $status,
                'customer_id' => $customerId,
            ],
            'stats' => $stats,
        ]);
    }

    /* =========================================================
     |  CREATE
     ========================================================= */

    public function create(Request $request): Response
    {
        $saleId       = $request->query('sale_id');
        $scanBarcode  = trim((string) $request->query('barcode', ''));
        $selectedSale = null;
        $saleItems    = [];

        if ($saleId) {
            $selectedSale = Sale::with([
                'customer:id,name,phone,current_due_afn',
                'branch:id,name,code',
            ])->find($saleId);

            if ($selectedSale) {
                $saleItems = $this->buildSaleItemsForReturn($selectedSale);
            }
        }

        /* ═══ Sales قابل انتخاب — فقط completed با remaining_due یا هر کاملی ═══ */
        $sales = Sale::query()
            ->notTrashed()
            ->completed()
            ->with('customer:id,name,phone')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'invoice_number', 'total_afn', 'branch_id', 'customer_id', 'sale_date']);

        return Inertia::render('sale-returns/create', [
            'sales'        => $sales,
            'selectedSale' => $selectedSale ? [
                'id'             => $selectedSale->id,
                'invoice_number' => $selectedSale->invoice_number,
                'customer'       => $selectedSale->customer,
                'branch'         => $selectedSale->branch,
                'total_afn'      => $selectedSale->total_afn,
                'remaining_due_afn' => $selectedSale->remaining_due_afn,
            ] : null,
            'saleItems'    => $saleItems,
            'scanBarcode'  => $scanBarcode !== '' ? $scanBarcode : null,
        ]);
    }

    public function lookupBarcode(Request $request): JsonResponse
    {
        $barcode = trim((string) $request->query('barcode', ''));

        if ($barcode === '') {
            return response()->json(['found' => false, 'message' => 'Barcode is empty.']);
        }

        $variantId = ProductBarcode::query()
            ->where('barcode', $barcode)
            ->value('variant_id');

        $candidates = SaleItem::query()
            ->where(function ($query) use ($barcode, $variantId) {
                $query->where('barcode_snapshot', $barcode);
                if ($variantId) {
                    $query->orWhere('variant_id', $variantId);
                }
            })
            ->whereHas('sale', fn ($query) => $query->notTrashed()->where('status', 'completed'))
            ->with(['sale:id,invoice_number,sale_date,customer_id,branch_id,status', 'sale.customer:id,name,phone'])
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        foreach ($candidates as $saleItem) {
            $alreadyReturned = (int) SaleReturnItem::query()
                ->where('sale_item_id', $saleItem->id)
                ->whereHas('saleReturn', fn ($query) => $query->where('status', '!=', SaleReturn::STATUS_CANCELLED))
                ->sum('quantity');

            $maxReturnable = max(0, (int) $saleItem->quantity - $alreadyReturned);
            if ($maxReturnable <= 0) {
                continue;
            }

            return response()->json([
                'found' => true,
                'sale_id' => $saleItem->sale_id,
                'sale_item_id' => $saleItem->id,
                'barcode' => $barcode,
                'invoice_number' => $saleItem->sale?->invoice_number,
                'customer' => $saleItem->sale?->customer?->name,
                'product' => $saleItem->product_name_snapshot,
                'max_returnable' => $maxReturnable,
            ]);
        }

        return response()->json([
            'found' => false,
            'message' => $candidates->isEmpty()
                ? "No completed sale found for barcode \"{$barcode}\"."
                : "Barcode \"{$barcode}\" was sold, but nothing is left to return.",
        ]);
    }

    /* =========================================================
     |  STORE
     ========================================================= */

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sale_id'      => ['required', 'exists:sales,id'],
            'return_date'  => ['required', 'date'],
            'reason'       => ['nullable', 'string', 'max:2000'],
            'status'       => ['required', 'in:draft,completed,cancelled'],

            'items'                  => ['required', 'array', 'min:1'],
            'items.*.sale_item_id'   => ['required', 'exists:sale_items,id'],
            'items.*.quantity'       => ['required', 'integer', 'min:1'],
            'items.*.condition'          => ['required', 'in:good,damaged,expired,other,used'],
            'items.*.reason'             => ['nullable', 'string', 'max:500'],
            'items.*.expense_amount_afn' => ['nullable', 'numeric', 'min:0'],
        ]);

        $saleReturn = DB::transaction(function () use ($validated) {
            $sale = Sale::with('customer')->findOrFail($validated['sale_id']);

            /* ═══ ساخت SaleReturn ═══ */
            $customer       = $sale->customer;
            $previousDueAfn = $customer ? (float) $customer->current_due_afn : 0;

            $saleReturn = SaleReturn::create([
                'sale_id'         => $sale->id,
                'branch_id'       => $sale->branch_id,
                'customer_id'     => $sale->customer_id,
                'return_number'   => SaleReturn::generateReturnNumber(),
                'subtotal_afn'    => 0,
                'total_afn'       => 0,
                'previous_due_afn' => $previousDueAfn,
                'new_due_afn'     => $previousDueAfn,
                'refund_amount_afn' => 0,
                'refund_method'   => null,
                'reason'          => $validated['reason'] ?? null,
                'return_date'     => $validated['return_date'],
                'status'          => $validated['status'],
                'created_by'      => Auth::id(),
            ]);

            /* ═══ ساخت SaleReturnItem ها ═══ */
            $subtotalAfn = 0;

            foreach ($validated['items'] as $itemData) {
                $saleItem = SaleItem::findOrFail($itemData['sale_item_id']);

                $qty       = (int) $itemData['quantity'];
                $unitPrice = (float) $saleItem->sale_price_afn;
                $lineTotal = $qty * $unitPrice;

                SaleReturnItem::create([
                    'sale_return_id' => $saleReturn->id,
                    'sale_item_id'   => $saleItem->id,
                    'batch_id'       => $saleItem->batch_id,
                    'variant_id'     => $saleItem->variant_id,
                    'quantity'       => $qty,
                    'unit_price_afn' => $unitPrice,
                    'total_afn'      => $lineTotal,
                    'condition'          => $itemData['condition'],
                    'reason'             => $itemData['reason'] ?? null,
                    'expense_amount_afn' => round((float) ($itemData['expense_amount_afn'] ?? 0), 2),
                ]);

                $subtotalAfn += $lineTotal;
            }

            $saleReturn = $saleReturn->fresh('items');
            $this->applyReturnTotals($saleReturn);

            foreach ($saleReturn->items as $item) {
                $this->syncItemExpense($saleReturn, $item);
            }

            if ($saleReturn->status === SaleReturn::STATUS_COMPLETED) {
                $this->deductReturnFromSale($saleReturn);
            }

            return $saleReturn;
        });

        return redirect()
            ->route('sale-returns.show', $saleReturn->id)
            ->with('success', "Return \"{$saleReturn->return_number}\" created successfully.");
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(SaleReturn $saleReturn): Response
    {
        if ($saleReturn->trash) abort(404);

        $saleReturn->load([
            'sale:id,invoice_number,total_afn,remaining_due_afn,sale_date',
            'branch:id,name,code',
            'customer:id,name,phone,current_due_afn,credit_limit_afn',
            'creator:id,name,email',
            'items.variant.product:id,name',
            'items.variant.product.brand:id,name',
            'items.batch:id,batch_code',
            'items.saleItem:id,sale_price_afn',
            'items.expense:id,expense_number,title,amount_afn,expense_date',
        ]);

        $expenses = $saleReturn->items
            ->pluck('expense')
            ->filter()
            ->unique('id')
            ->values();

        return Inertia::render('sale-returns/show', [
            'saleReturn' => $saleReturn,
            'expenses' => $expenses,
        ]);
    }

    /* =========================================================
     |  EDIT
     ========================================================= */

    public function edit(SaleReturn $saleReturn): Response
    {
        if ($saleReturn->trash) abort(404);

        if ($saleReturn->status === 'completed') {
            return redirect()
                ->route('sale-returns.show', $saleReturn->id)
                ->with('error', 'Completed returns cannot be edited.');
        }

        $saleReturn->load(['items.variant.product:id,name', 'items.variant.product.brand:id,name', 'sale:id,invoice_number']);

        return Inertia::render('sale-returns/edit', [
            'saleReturn' => $saleReturn,
        ]);
    }

    /* =========================================================
     |  UPDATE
     ========================================================= */

    public function update(Request $request, SaleReturn $saleReturn): RedirectResponse
    {
        if ($saleReturn->status === 'completed') {
            return back()->with('error', 'Completed returns cannot be edited.');
        }

        $validated = $request->validate([
            'return_date' => ['required', 'date'],
            'reason'      => ['nullable', 'string', 'max:2000'],
            'status'      => ['required', 'in:draft,completed,cancelled'],

            'items'                => ['required', 'array', 'min:1'],
            'items.*.id'           => ['required', 'exists:sale_return_items,id'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'items.*.condition'          => ['required', 'in:good,damaged,expired,other,used'],
            'items.*.reason'             => ['nullable', 'string', 'max:500'],
            'items.*.expense_amount_afn' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $saleReturn) {
            $wasCompleted = $saleReturn->status === SaleReturn::STATUS_COMPLETED;

            $saleReturn->update([
                'return_date' => $validated['return_date'],
                'reason'      => $validated['reason'] ?? null,
                'status'      => $validated['status'],
            ]);

            foreach ($validated['items'] as $itemData) {
                $item = SaleReturnItem::findOrFail($itemData['id']);
                $unitPrice = (float) $item->unit_price_afn;
                $lineTotal = $itemData['quantity'] * $unitPrice;

                $item->update([
                    'quantity'           => $itemData['quantity'],
                    'total_afn'          => $lineTotal,
                    'condition'          => $itemData['condition'],
                    'reason'             => $itemData['reason'] ?? null,
                    'expense_amount_afn' => round((float) ($itemData['expense_amount_afn'] ?? 0), 2),
                ]);
            }

            $saleReturn = $saleReturn->fresh('items');
            $this->applyReturnTotals($saleReturn);

            foreach ($saleReturn->items as $item) {
                $this->syncItemExpense($saleReturn, $item);
            }

            if (! $wasCompleted && $saleReturn->status === SaleReturn::STATUS_COMPLETED) {
                $this->deductReturnFromSale($saleReturn);
            }
        });

        return redirect()
            ->route('sale-returns.show', $saleReturn->id)
            ->with('success', "Return \"{$saleReturn->return_number}\" updated successfully.");
    }

    /* =========================================================
     |  MARK AS COMPLETED — اعمال نهایی: انبار + ledger + قرض
     ========================================================= */

    public function markAsCompleted(SaleReturn $saleReturn): RedirectResponse
    {
        if ($saleReturn->isCompleted()) {
            return back()->with('error', 'This return is already completed.');
        }

        if ($saleReturn->trash) {
            return back()->with('error', 'Cannot complete a trashed return.');
        }

        DB::transaction(function () use ($saleReturn) {
            $saleReturn->load('items', 'customer');

            /* ═══ ۱. برگرداندن اقلام سالم به انبار ═══ */
            foreach ($saleReturn->items as $item) {
                if ($item->shouldReturnToStock() && $item->batch_id) {
                    $batch = ProductBatch::find($item->batch_id);

                    if ($batch) {
                        $batch->restoreStock($item->quantity);

                        Inventory::increase(
                            branchId:      $saleReturn->branch_id,
                            variantId:     $item->variant_id,
                            qty:           $item->quantity,
                            type:          'sale_return',
                            batchId:       $batch->id,
                            referenceType: SaleReturn::class,
                            referenceId:   $saleReturn->id,
                            notes:         "Returned via {$saleReturn->return_number}"
                        );
                    }
                }
                /* اگر خراب/منقضی → به انبار برنمی‌گردد */
            }

            /* ═══ ۲. آپدیت مشتری + ledger ═══ */
            if ($saleReturn->customer_id) {
                $customer = Customer::find($saleReturn->customer_id);

                if ($customer) {
                    $ledger = app(CustomerLedgerService::class);
                    $ledger->recordReturn($saleReturn);

                    $customer->update([
                        'current_due_afn' => $saleReturn->new_due_afn,
                    ]);
                }
            }

            /* ═══ ۳. آپدیت sale.remaining_due_afn ═══ */
            if ($saleReturn->sale_id) {
                $sale = Sale::find($saleReturn->sale_id);

                if ($sale && $saleReturn->customer_id) {
                    $customer = Customer::find($saleReturn->customer_id);
                    if ($customer) {
                        $sale->update([
                            'remaining_due_afn' => $customer->current_due_afn,
                            'due_afn'           => $customer->current_due_afn,
                            'payment_status'    => $customer->current_due_afn <= 0.01 ? 'paid' : 'partial',
                        ]);
                    }
                }
            }

            $saleReturn = $saleReturn->fresh('items');
            foreach ($saleReturn->items as $item) {
                $this->syncItemExpense($saleReturn, $item);
            }

            $this->deductReturnFromSale($saleReturn);

            /* ═══ ۴. وضعیت نهایی ═══ */
            $saleReturn->update(['status' => SaleReturn::STATUS_COMPLETED]);
        });

        return back()->with('success', "Return \"{$saleReturn->return_number}\" completed successfully.");
    }

    public function markItemUsed(SaleReturn $saleReturn, SaleReturnItem $item): RedirectResponse
    {
        if ($item->sale_return_id !== $saleReturn->id) {
            abort(404);
        }

        if (! $saleReturn->isCompleted()) {
            return back()->with('error', 'Complete the return before marking an item as used.');
        }

        if ($item->isUsed()) {
            return back()->with('error', 'This item is already marked as used.');
        }

        DB::transaction(function () use ($saleReturn, $item) {
            if ($item->shouldReturnToStock() && $item->batch_id) {
                $batch = ProductBatch::find($item->batch_id);
                if ($batch) {
                    $batch->reduceStock($item->quantity);
                }

                Inventory::decrease(
                    branchId: $saleReturn->branch_id,
                    variantId: $item->variant_id,
                    qty: $item->quantity,
                    type: 'sale_return',
                    batchId: $item->batch_id,
                    referenceType: SaleReturn::class,
                    referenceId: $saleReturn->id,
                    notes: "Used after return {$saleReturn->return_number}"
                );
            }

            $item->update([
                'condition' => SaleReturnItem::CONDITION_USED,
                'expense_amount_afn' => $item->total_afn,
            ]);
            $this->applyReturnTotals($saleReturn->fresh('items'));
            $this->syncItemExpense($saleReturn->fresh('items'), $item->fresh());

            if ($saleReturn->customer_id) {
                $customer = Customer::find($saleReturn->customer_id);
                $saleReturn->refresh();
                if ($customer) {
                    $previousDue = (float) $customer->current_due_afn;
                    $customer->update(['current_due_afn' => $saleReturn->new_due_afn]);
                    $delta = round((float) $saleReturn->new_due_afn - $previousDue, 2);
                    if (abs($delta) >= 0.01) {
                        app(CustomerLedgerService::class)->recordAdjustment(
                            $customer->fresh(),
                            $delta,
                            (float) $saleReturn->new_due_afn,
                            "Used item on return {$saleReturn->return_number}"
                        );
                    }
                }
            }
        });

        return back()->with('success', 'Used item was recorded as an expense and removed from the return amount.');
    }

    /* =========================================================
     |  DESTROY / TRASH / RESTORE / FORCE DELETE
     ========================================================= */

    public function destroy(SaleReturn $saleReturn): RedirectResponse
    {
        if ($saleReturn->isCompleted()) {
            return back()->with('error', 'Completed returns cannot be deleted.');
        }

        $number = $saleReturn->return_number;
        $saleReturn->update(['trash' => true]);

        return redirect()->route('sale-returns.index')
            ->with('success', "Return \"{$number}\" was moved to trash.");
    }

    public function restore(int $id): RedirectResponse
    {
        $saleReturn = SaleReturn::onlyTrashed()->findOrFail($id);
        $saleReturn->update(['trash' => false]);

        return redirect()->route('sale-returns.index')
            ->with('success', "Return \"{$saleReturn->return_number}\" was restored.");
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $saleReturn = SaleReturn::onlyTrashed()->findOrFail($id);
        $number     = $saleReturn->return_number;
        $saleReturn->forceDelete();

        return redirect()->route('sale-returns.index')
            ->with('success', "Return \"{$number}\" was permanently deleted.");
    }

    public function trash(): Response
    {
        $saleReturns = SaleReturn::onlyTrashed()
            ->with(['sale:id,invoice_number', 'branch:id,name', 'customer:id,name', 'creator:id,name'])
            ->orderByDesc('updated_at')
            ->paginate(15);

        return Inertia::render('sale-returns/trash', ['saleReturns' => $saleReturns]);
    }

    private function applyReturnTotals(SaleReturn $saleReturn): void
    {
        $credit = 0.0;

        foreach ($saleReturn->items as $item) {
            $line = (float) $item->total_afn;
            $used = $item->condition === SaleReturnItem::CONDITION_USED
                ? $line
                : min($line, (float) $item->expense_amount_afn);
            $credit += max(0, $line - $used);
        }

        $previousDue = (float) $saleReturn->previous_due_afn;
        $newDue = max(0, $previousDue - $credit);
        $refund = max(0, $credit - $previousDue);

        $saleReturn->update([
            'subtotal_afn' => round($credit, 2),
            'total_afn' => round($credit, 2),
            'new_due_afn' => round($newDue, 2),
            'refund_amount_afn' => round($refund, 2),
            'refund_method' => $refund > 0 ? ($saleReturn->refund_method ?: 'cash') : null,
        ]);
    }

    private function syncItemExpense(SaleReturn $saleReturn, SaleReturnItem $item): void
    {
        if ($item->condition === SaleReturnItem::CONDITION_USED && (float) $item->expense_amount_afn <= 0) {
            $item->update(['expense_amount_afn' => $item->total_afn]);
            $item->refresh();
        }

        $amount = round((float) $item->expense_amount_afn, 2);

        if ($amount <= 0) {
            if ($item->expense_id) {
                Expense::query()->where('id', $item->expense_id)->update(['trash' => true]);
                $item->update(['expense_id' => null]);
            }

            return;
        }

        $this->recordUsedExpense($saleReturn, $item);
        $item->refresh();

        if ($item->expense_id) {
            Expense::query()->where('id', $item->expense_id)->update([
                'amount_afn' => $amount,
                'trash' => false,
            ]);
        }
    }

    private function deductReturnFromSale(SaleReturn $saleReturn): void
    {
        if (! $saleReturn->sale_id) {
            return;
        }

        $sale = Sale::find($saleReturn->sale_id);
        if (! $sale) {
            return;
        }

        $amount = round((float) $saleReturn->items->sum('total_afn'), 2);

        $sale->update([
            'subtotal_afn' => max(0, round((float) $sale->subtotal_afn - $amount, 2)),
            'total_afn' => max(0, round((float) $sale->total_afn - $amount, 2)),
            'new_sale_amount_afn' => max(0, round((float) $sale->new_sale_amount_afn - $amount, 2)),
        ]);
    }

    private function recordUsedExpense(SaleReturn $saleReturn, ?SaleReturnItem $onlyItem = null): void
    {
        $saleReturn->loadMissing('items.variant.product');
        $items = $onlyItem
            ? collect([$onlyItem->loadMissing('variant.product')])
            : $saleReturn->items->filter(fn ($item) => (float) $item->expense_amount_afn > 0);

        foreach ($items as $item) {
            if ($item->expense_id) {
                continue;
            }

            $marker = "sale_return_item:{$item->id}";
            $existing = Expense::query()->where('description', 'like', "%{$marker}%")->first();
            if ($existing) {
                $item->update(['expense_id' => $existing->id]);
                continue;
            }

            $name = $item->variant?->product?->name ?? 'Returned item';
            $expense = Expense::create([
                'branch_id' => $saleReturn->branch_id,
                'expense_number' => Expense::generateExpenseNumber(),
                'title' => "Used return {$saleReturn->return_number}",
                'category' => 'other',
                'amount_afn' => $item->expense_amount_afn,
                'expense_date' => $saleReturn->return_date ?? now()->toDateString(),
                'payment_method' => 'cash',
                'description' => "Consumed {$name} on {$saleReturn->return_number}. {$marker}",
                'created_by' => Auth::id(),
                'trash' => false,
            ]);

            $item->update(['expense_id' => $expense->id]);
        }
    }

    /* =========================================================
     |  PRIVATE HELPERS
     ========================================================= */

    /**
     * ساختن لیست اقلام قابل برگشت برای یک فروش
     */
    protected function buildSaleItemsForReturn(Sale $sale): array
    {
        $sale->load(['items.variant.product:id,name', 'items.variant.product.brand:id,name', 'items.batch:id,batch_code']);

        /* ═══ محاسبه مقادیر قبلاً برگشت‌شده ═══ */
        $returnedPerItem = SaleReturnItem::query()
            ->whereIn('sale_item_id', $sale->items->pluck('id'))
            ->whereHas('saleReturn', fn ($q) => $q->where('status', '!=', SaleReturn::STATUS_CANCELLED))
            ->selectRaw('sale_item_id, SUM(quantity) as total_returned')
            ->groupBy('sale_item_id')
            ->pluck('total_returned', 'sale_item_id');

        $result = [];

        foreach ($sale->items as $item) {
            $alreadyReturned = (int) ($returnedPerItem[$item->id] ?? 0);
            $maxReturnable   = max(0, $item->quantity - $alreadyReturned);

            $variant = $item->variant;
            $product = $variant?->product;

            $result[] = [
                'id'             => $item->id,
                'variant_id'     => $item->variant_id,
                'variant_name'   => $variant?->name,
                'brand_name'     => $product?->brand?->name,
                'product_name'   => $product?->name,
                'volume_ml'      => $variant?->volume_ml,
                'concentration'  => $variant?->concentration,
                'batch_code'     => $item->batch?->batch_code,
                'batch_id'       => $item->batch_id,
                'barcode_snapshot' => $item->barcode_snapshot,
                'quantity_sold'  => $item->quantity,
                'already_returned' => $alreadyReturned,
                'max_returnable' => $maxReturnable,
                'sale_price_afn' => (float) $item->sale_price_afn,
            ];
        }

        return $result;
    }
}