<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\InvestorTransaction;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Services\CustomerLedgerService;
use App\Services\ExchangeService;
use App\Support\Money;
use App\Support\OriginalCurrencyMoney;
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
        $user      = $request->user();

        if ($user?->isBranchScoped()) {
            $branchId = $user->branch_id;
        }

        $saleReturns = SaleReturn::query()
            ->with([
                'sale:id,invoice_number,sale_currency_code,sale_currency_symbol,sale_amount_currency,usd_to_afn_rate,aed_to_afn_rate,usd_to_ledger_rate,total_afn',
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

        $saleReturns->getCollection()->each(function (SaleReturn $saleReturn) {
            $money = OriginalCurrencyMoney::forSaleReturn($saleReturn);
            $saleReturn->setAttribute('currency_code', $money['code']);
            $saleReturn->setAttribute('currency_symbol', $money['symbol']);
            $saleReturn->setAttribute('currency_known', $money['known']);
            $saleReturn->setAttribute('currency_label', $money['label']);
            $saleReturn->setAttribute('display_amount', $money['amount']);
            $saleReturn->setAttribute(
                'display_refund',
                $saleReturn->sale
                    ? OriginalCurrencyMoney::fromLedgerAfn((float) $saleReturn->refund_amount_afn, $money['code'], $saleReturn->sale)
                    : null
            );
            $saleReturn->setAttribute(
                'display_previous_due',
                $saleReturn->sale
                    ? OriginalCurrencyMoney::fromLedgerAfn((float) $saleReturn->previous_due_afn, $money['code'], $saleReturn->sale)
                    : null
            );
            $saleReturn->setAttribute(
                'display_new_due',
                $saleReturn->sale
                    ? OriginalCurrencyMoney::fromLedgerAfn((float) $saleReturn->new_due_afn, $money['code'], $saleReturn->sale)
                    : null
            );
        });

        $completedReturns = SaleReturn::query()
            ->notTrashed()
            ->completed()
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->with(['sale:id,sale_currency_code,sale_currency_symbol,sale_amount_currency,usd_to_afn_rate,aed_to_afn_rate,usd_to_ledger_rate,total_afn'])
            ->get(['id', 'sale_id', 'total_afn', 'return_date']);

        $totalByCurrency = OriginalCurrencyMoney::sumByCurrency(
            $completedReturns->map(function (SaleReturn $saleReturn) {
                $money = OriginalCurrencyMoney::forSaleReturn($saleReturn);

                return [
                    'code' => $money['code'],
                    'symbol' => $money['symbol'],
                    'amount' => $money['amount'],
                    'ledger_afn' => $money['ledger_afn'],
                ];
            })
        );
        $todayByCurrency = OriginalCurrencyMoney::sumByCurrency(
            $completedReturns
                ->filter(fn (SaleReturn $r) => optional($r->return_date)?->toDateString() === now()->toDateString())
                ->map(function (SaleReturn $saleReturn) {
                    $money = OriginalCurrencyMoney::forSaleReturn($saleReturn);

                    return [
                        'code' => $money['code'],
                        'symbol' => $money['symbol'],
                        'amount' => $money['amount'],
                        'ledger_afn' => $money['ledger_afn'],
                    ];
                })
        );

        /* ═══ Stats ═══ */
        $statsBase = SaleReturn::notTrashed()
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v));

        $stats = [
            'total_returns'   => (clone $statsBase)->count(),
            'today_returns'   => (clone $statsBase)->whereDate('return_date', today())->count(),
            'total_amount'    => (float) (clone $statsBase)->completed()->sum('total_afn'),
            'today_amount'    => (float) (clone $statsBase)->completed()->whereDate('return_date', today())->sum('total_afn'),
            'total_amount_by_currency' => $totalByCurrency['list'],
            'today_amount_by_currency' => $todayByCurrency['list'],
        ];

        return Inertia::render('sale-returns/index', [
            'saleReturns' => $saleReturns,
            'branches'    => Branch::notTrashed()->visibleTo($user)->select('id', 'name', 'code')->get(),
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
        $saleId        = $request->query('sale_id');
        $invoiceQuery  = trim((string) $request->query('invoice', ''));
        $invoiceError  = null;
        $selectedSale  = null;
        $saleItems     = [];

        if (! $saleId && $invoiceQuery !== '') {
            $matched = Sale::query()
                ->notTrashed()
                ->completed()
                ->where('invoice_number', $invoiceQuery)
                ->orderByDesc('id')
                ->first();

            if (! $matched) {
                $matched = Sale::query()
                    ->notTrashed()
                    ->completed()
                    ->where('invoice_number', 'like', $invoiceQuery.'%')
                    ->orderByDesc('id')
                    ->first();
            }

            if ($matched) {
                $saleId = $matched->id;
            } else {
                $invoiceError = "No completed sale found for invoice \"{$invoiceQuery}\".";
            }
        }

        if ($saleId) {
            $selectedSale = Sale::with([
                'customer:id,name,phone,current_due_afn',
                'branch:id,name,code',
            ])->find($saleId);
            if ($selectedSale) {
                $saleItems = $this->buildSaleItemsForReturn($selectedSale);
            } elseif (! $invoiceError) {
                $invoiceError = 'That sale was not found or is not available for return.';
            }
        }

        $sales = Sale::query()
            ->notTrashed()
            ->completed()
            ->with('customer:id,name,phone')
            ->orderByDesc('id')
            ->limit(100)
            ->get([
                'id', 'invoice_number', 'total_afn', 'branch_id', 'customer_id', 'sale_date',
                'sale_currency_code', 'sale_currency_symbol', 'sale_amount_currency',
                'usd_to_afn_rate', 'aed_to_afn_rate', 'usd_to_ledger_rate',
            ])
            ->map(function (Sale $sale) {
                $money = OriginalCurrencyMoney::forSale($sale);

                return [
                    'id'              => $sale->id,
                    'invoice_number'  => $sale->invoice_number,
                    'total_afn'       => $sale->total_afn,
                    'sale_date'       => $sale->sale_date,
                    'customer_id'     => $sale->customer_id,
                    'branch_id'       => $sale->branch_id,
                    'customer_name'   => $sale->customer?->name,
                    'customer_phone'  => $sale->customer?->phone,
                    'currency_code'   => $money['code'],
                    'currency_label'  => $money['label'],
                    'currency_known'  => $money['known'],
                    'display_amount'  => $money['amount'],
                ];
            });

        $selectedMoney = $selectedSale ? OriginalCurrencyMoney::forSale($selectedSale) : null;

        return Inertia::render('sale-returns/create', [
            'sales'        => $sales,
            'selectedSale' => $selectedSale ? [
                'id'                => $selectedSale->id,
                'invoice_number'    => $selectedSale->invoice_number,
                'customer'          => $selectedSale->customer,
                'branch'            => $selectedSale->branch,
                'total_afn'         => $selectedSale->total_afn,
                'remaining_due_afn' => $selectedSale->remaining_due_afn,
                'sale_currency_code' => $selectedMoney['code'],
                'sale_currency_symbol' => $selectedMoney['symbol'],
                'sale_currency_label' => $selectedMoney['label'],
                'sale_currency_known' => $selectedMoney['known'],
                'sale_amount_currency' => $selectedMoney['amount'],
                'usd_to_afn_rate' => $selectedSale->usd_to_afn_rate,
                'aed_to_afn_rate' => $selectedSale->aed_to_afn_rate,
            ] : null,
            'saleItems'     => $saleItems,
            'invoiceQuery'  => $invoiceQuery !== '' ? $invoiceQuery : null,
            'invoiceError'  => $invoiceError,
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
            'items.*.expense_bearer'     => ['required', 'in:shop,customer'],
        ]);

        $saleReturn = DB::transaction(function () use ($validated) {
            $sale = Sale::with('customer')->findOrFail($validated['sale_id']);
            $exchange = app(ExchangeService::class);

            $currencyCode = strtoupper((string) ($sale->sale_currency_code ?: 'AFN'));
            $currency = Currency::query()->notTrashed()->where('code', $currencyCode)->first()
                ?? Currency::ledger();
            $snap = $exchange->snapshot($currencyCode, 'AFN');
            $rate = (float) $snap['rate'];

            $customer       = $sale->customer;
            $previousDueAfn = $customer ? (float) $customer->current_due_afn : 0;

            $saleReturn = SaleReturn::create([
                'sale_id'                 => $sale->id,
                'branch_id'               => $sale->branch_id,
                'customer_id'             => $sale->customer_id,
                'return_number'           => SaleReturn::generateReturnNumber(),
                'return_currency_id'      => $currency?->id,
                'return_currency_code'    => $currencyCode,
                'return_currency_symbol'  => $currency?->symbol ?? $sale->sale_currency_symbol,
                'return_amount_currency'  => 0,
                'exchange_rate_to_afn'    => Money::round((string) $snap['rate'], 8),
                'rate_effective_at'       => $snap['effective_at'],
                'rate_source'             => $snap['source'],
                'subtotal_afn'            => 0,
                'total_afn'               => 0,
                'previous_due_afn'        => $previousDueAfn,
                'new_due_afn'             => $previousDueAfn,
                'refund_amount_afn'       => 0,
                'refund_method'           => null,
                'reason'                  => $validated['reason'] ?? null,
                'return_date'             => $validated['return_date'],
                'status'                  => SaleReturn::STATUS_DRAFT,
                'created_by'              => Auth::id(),
            ]);

            foreach ($validated['items'] as $itemData) {
                $saleItem = SaleItem::findOrFail($itemData['sale_item_id']);

                $qty = (int) $itemData['quantity'];
                $unitPriceCurrency = $rate > 0
                    ? round((float) $saleItem->sale_price_afn / $rate, 4)
                    : (float) $saleItem->sale_price_afn;
                $totalCurrency = round($unitPriceCurrency * $qty, 2);
                $unitPriceAfn = (float) Money::round(Money::mul((string) $unitPriceCurrency, (string) $snap['rate'], 12), 2);
                $lineTotalAfn = (float) Money::round(Money::mul((string) $totalCurrency, (string) $snap['rate'], 12), 2);

                $returnItem = new SaleReturnItem([
                    'sale_return_id'         => $saleReturn->id,
                    'sale_item_id'           => $saleItem->id,
                    'batch_id'               => $saleItem->batch_id,
                    'variant_id'             => $saleItem->variant_id,
                    'quantity'               => $qty,
                    'return_currency_code'   => $currencyCode,
                    'unit_price_currency'    => $unitPriceCurrency,
                    'total_currency'         => $totalCurrency,
                    'unit_price_afn'         => $unitPriceAfn,
                    'total_afn'              => $lineTotalAfn,
                    'exchange_rate_to_afn'   => Money::round((string) $snap['rate'], 8),
                    'condition'              => $itemData['condition'],
                    'reason'                 => $itemData['reason'] ?? null,
                    'expense_amount_afn'     => round((float) ($itemData['expense_amount_afn'] ?? 0), 2),
                    'expense_bearer'         => $itemData['expense_bearer'],
                ]);
                $returnItem->calculateFromOriginalSaleItem();
                $returnItem->save();
            }

            $saleReturn = $saleReturn->fresh('items');
            $this->applyReturnTotals($saleReturn);

            if ($validated['status'] === SaleReturn::STATUS_COMPLETED) {
                $this->finalizeReturn($saleReturn->fresh(['items', 'customer', 'sale']));
            } elseif ($validated['status'] === SaleReturn::STATUS_CANCELLED) {
                $saleReturn->update(['status' => SaleReturn::STATUS_CANCELLED]);
            }

            return $saleReturn->fresh();
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
            'sale:id,invoice_number,total_afn,remaining_due_afn,sale_date,sale_currency_code,sale_currency_symbol,sale_amount_currency,usd_to_afn_rate,aed_to_afn_rate,usd_to_ledger_rate',
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

        $money = OriginalCurrencyMoney::forSaleReturn($saleReturn);
        $saleReturn->setAttribute('currency_code', $money['code']);
        $saleReturn->setAttribute('currency_symbol', $money['symbol']);
        $saleReturn->setAttribute('currency_known', $money['known']);
        $saleReturn->setAttribute('currency_label', $money['label']);
        $saleReturn->setAttribute('display_amount', $money['amount']);
        if ($saleReturn->sale) {
            $saleReturn->setAttribute(
                'display_refund',
                OriginalCurrencyMoney::fromLedgerAfn((float) $saleReturn->refund_amount_afn, $money['code'], $saleReturn->sale)
            );
            $saleReturn->setAttribute(
                'display_previous_due',
                OriginalCurrencyMoney::fromLedgerAfn((float) $saleReturn->previous_due_afn, $money['code'], $saleReturn->sale)
            );
            $saleReturn->setAttribute(
                'display_new_due',
                OriginalCurrencyMoney::fromLedgerAfn((float) $saleReturn->new_due_afn, $money['code'], $saleReturn->sale)
            );

            $saleReturn->items->each(function (SaleReturnItem $item) use ($money, $saleReturn) {
                $item->setAttribute(
                    'display_total',
                    OriginalCurrencyMoney::fromLedgerAfn((float) $item->total_afn, $money['code'], $saleReturn->sale)
                );
                $item->setAttribute(
                    'display_unit_price',
                    OriginalCurrencyMoney::fromLedgerAfn((float) $item->unit_price_afn, $money['code'], $saleReturn->sale)
                );
                $item->setAttribute(
                    'display_expense_amount',
                    OriginalCurrencyMoney::fromLedgerAfn((float) ($item->expense_amount_afn ?? 0), $money['code'], $saleReturn->sale)
                );
            });
        }

        return Inertia::render('sale-returns/show', [
            'saleReturn' => $saleReturn,
            'expenses' => $expenses,
        ]);
    }

    /* =========================================================
     |  EDIT
     ========================================================= */

    public function edit(SaleReturn $saleReturn): Response|RedirectResponse
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
            'items.*.expense_bearer'     => ['required', 'in:shop,customer'],
        ]);

        DB::transaction(function () use ($validated, $saleReturn) {
            $saleReturn->update([
                'return_date' => $validated['return_date'],
                'reason'      => $validated['reason'] ?? null,
                'status'      => SaleReturn::STATUS_DRAFT,
            ]);

            foreach ($validated['items'] as $itemData) {
                $item = $saleReturn->items()->findOrFail($itemData['id']);
                $qty = (int) $itemData['quantity'];
                $rate = (float) ($item->exchange_rate_to_afn ?: $saleReturn->exchange_rate_to_afn ?: 1);
                $unitPriceCurrency = $item->unit_price_currency !== null
                    ? (float) $item->unit_price_currency
                    : ($rate > 0 ? round((float) $item->unit_price_afn / $rate, 4) : (float) $item->unit_price_afn);
                $totalCurrency = round($unitPriceCurrency * $qty, 2);
                $unitPriceAfn = round($unitPriceCurrency * $rate, 2);
                $lineTotalAfn = round($totalCurrency * $rate, 2);

                $item->fill([
                    'quantity'             => $qty,
                    'unit_price_currency'  => $unitPriceCurrency,
                    'total_currency'       => $totalCurrency,
                    'unit_price_afn'       => $unitPriceAfn,
                    'total_afn'            => $lineTotalAfn,
                    'exchange_rate_to_afn' => $rate,
                    'condition'            => $itemData['condition'],
                    'reason'               => $itemData['reason'] ?? null,
                    'expense_amount_afn'   => round((float) ($itemData['expense_amount_afn'] ?? 0), 2),
                    'expense_bearer'       => $itemData['expense_bearer'],
                ]);
                $item->calculateFromOriginalSaleItem();
                $item->save();
            }

            $saleReturn = $saleReturn->fresh('items');
            $this->applyReturnTotals($saleReturn);

            foreach ($saleReturn->items as $item) {
                $this->syncItemExpense($saleReturn, $item);
            }

            if ($validated['status'] === SaleReturn::STATUS_COMPLETED) {
                $this->finalizeReturn($saleReturn->fresh(['items', 'customer']));
            } elseif ($validated['status'] === SaleReturn::STATUS_CANCELLED) {
                $saleReturn->update(['status' => SaleReturn::STATUS_CANCELLED]);
            }
        });

        return redirect()
            ->route('sale-returns.show', $saleReturn->id)
            ->with('success', "Return \"{$saleReturn->return_number}\" updated successfully.");
    }

    /* =========================================================
     |  MARK AS COMPLETED
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
            $this->finalizeReturn($saleReturn->fresh(['items', 'customer']));
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
                    type: 'return',    // ✅ اصلاح شد (قبلاً 'sale_return' بود)
                    batchId: $item->batch_id,
                    referenceType: SaleReturn::class,
                    referenceId: $saleReturn->id,
                    notes: "Used after return {$saleReturn->return_number}"
                );
            }

            $creditBefore = (float) $saleReturn->total_afn;

            $item->update([
                'condition' => SaleReturnItem::CONDITION_USED,
                'expense_amount_afn' => $item->total_afn,
            ]);

            $saleReturn = $saleReturn->fresh('items');
            $this->applyReturnTotals($saleReturn);
            $saleReturn->refresh();
            $this->syncItemExpense($saleReturn, $item->fresh());

            // Customer-borne expense lowers the credit already given — charge the difference back.
            $delta = round($creditBefore - (float) $saleReturn->total_afn, 2);

            if ($delta >= 0.01) {
                $this->adjustSaleTotals($saleReturn, $delta);

                $customer = $saleReturn->customer_id ? Customer::find($saleReturn->customer_id) : null;
                if ($customer) {
                    $newDue = round((float) $customer->current_due_afn + $delta, 2);
                    $rate = (float) ($saleReturn->exchange_rate_to_afn ?: 1);
                    $currencyCode = strtoupper((string) ($saleReturn->return_currency_code ?: 'AFN'));
                    $deltaCurrency = $rate > 0 ? round($delta / $rate, 2) : $delta;

                    app(CustomerLedgerService::class)->recordAdjustment(
                        $customer,
                        $delta,
                        $newDue,
                        "Used item on return {$saleReturn->return_number}",
                        $currencyCode,
                        $deltaCurrency,
                        $rate
                    );
                }
            }
        });

        return back()->with('success', $item->fresh()->isShopExpense()
            ? 'Used item was recorded as a shop expense.'
            : 'Used item amount was deducted from the customer.');
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
        $credit = round((float) $saleReturn->items->sum(fn (SaleReturnItem $item) => $item->customerCreditAmount()), 2);
        $returnAmountCurrency = round((float) $saleReturn->items->sum(fn (SaleReturnItem $item) => (float) ($item->total_currency ?? 0)), 2);
        $previousDue = (float) $saleReturn->previous_due_afn;

        $newDue = max(0, $previousDue - $credit);
        $refund = max(0, $credit - $previousDue);

        $saleReturn->update([
            'subtotal_afn'           => $credit,
            'total_afn'              => $credit,
            'return_amount_currency' => $returnAmountCurrency,
            'new_due_afn'            => round($newDue, 2),
            'refund_amount_afn'      => round($refund, 2),
            'refund_method'          => $refund > 0 ? ($saleReturn->refund_method ?: 'cash') : null,
        ]);
    }

    /**
     * Complete a return: restock, reverse investor profit/allocations, ledger, adjust sale totals.
     */
    private function finalizeReturn(SaleReturn $saleReturn): void
    {
        if ($saleReturn->isCompleted()) {
            return;
        }

        $saleReturn->loadMissing(['items.saleItem', 'customer']);

        if ($saleReturn->customer) {
            $saleReturn->update(['previous_due_afn' => (float) $saleReturn->customer->current_due_afn]);
        }

        $this->applyReturnTotals($saleReturn);
        $saleReturn->refresh();

        $reversedInvestorProfit = 0.0;
        $reversedCogs = 0.0;

        foreach ($saleReturn->items as $item) {
            $saleItem = $item->saleItem ?? ($item->sale_item_id ? SaleItem::find($item->sale_item_id) : null);
            $restockParts = $this->resolveRestockParts($item, $saleItem);

            if ($item->shouldReturnToStock()) {
                $lineCogsFromParts = 0.0;

                foreach ($restockParts as $part) {
                    $batch = ProductBatch::query()->lockForUpdate()->find($part['batch_id']);
                    if (! $batch) {
                        continue;
                    }

                    $qty = (int) $part['qty'];
                    $batch->restoreStock($qty);

                    Inventory::increase(
                        branchId: $saleReturn->branch_id,
                        variantId: $item->variant_id,
                        qty: $qty,
                        type: 'return',
                        batchId: $batch->id,
                        referenceType: SaleReturn::class,
                        referenceId: $saleReturn->id,
                        notes: "Returned via {$saleReturn->return_number}"
                    );

                    $lineCogsFromParts = round(
                        $lineCogsFromParts + ((float) ($part['cost_per_unit_afn'] ?? $batch->unit_cost_afn) * $qty),
                        2
                    );
                }

                // Prefer snapshotted original-sale COGS reversal over live/part recomputation.
                $lineCogs = (float) ($item->cogs_reversal_afn ?? 0) > 0
                    ? (float) $item->cogs_reversal_afn
                    : $lineCogsFromParts;
                $reversedCogs = round($reversedCogs + $lineCogs, 2);
            }

            if ($saleItem) {
                $reversedInvestorProfit = round(
                    $reversedInvestorProfit + $this->reverseInvestorProfitForReturn($saleItem, (int) $item->quantity),
                    2
                );
            }
        }

        $customer = $saleReturn->customer_id ? Customer::find($saleReturn->customer_id) : null;
        $sale = $saleReturn->sale_id ? Sale::find($saleReturn->sale_id) : null;

        if ($customer) {
            app(CustomerLedgerService::class)->recordReturn($saleReturn);
            $customer->refresh();

            if ($sale) {
                $sale->update([
                    'remaining_due_afn' => $customer->current_due_afn,
                    'due_afn' => $customer->current_due_afn,
                    'payment_status' => $customer->current_due_afn <= 0.01 ? 'paid' : 'partial',
                ]);
            }
        }

        $this->adjustSaleTotals(
            $saleReturn,
            -1 * (float) $saleReturn->total_afn,
            $reversedCogs,
            $reversedInvestorProfit
        );

        $saleReturn->update(['status' => SaleReturn::STATUS_COMPLETED]);

        foreach ($saleReturn->items as $item) {
            $this->syncItemExpense($saleReturn, $item);
        }
    }

    /**
     * @return list<array{batch_id:int, qty:int, cost_per_unit_afn?:float|null}>
     */
    private function resolveRestockParts(SaleReturnItem $item, ?SaleItem $saleItem): array
    {
        $qty = (int) $item->quantity;
        if ($qty <= 0) {
            return [];
        }

        $breakdown = $saleItem?->cost_breakdown ?? [];
        if (is_array($breakdown) && $breakdown !== []) {
            // Prefer original FIFO parts (correct unit costs) even when a single batch_id is set.
            $remaining = $qty;
            $parts = [];
            foreach (array_reverse($breakdown) as $part) {
                if ($remaining <= 0) {
                    break;
                }
                $partBatchId = (int) ($part['batch_id'] ?? 0);
                if ($partBatchId <= 0) {
                    continue;
                }
                if ($item->batch_id && $partBatchId !== (int) $item->batch_id) {
                    continue;
                }
                $take = min($remaining, (int) ($part['qty'] ?? 0));
                if ($take <= 0) {
                    continue;
                }
                $parts[] = [
                    'batch_id' => $partBatchId,
                    'qty' => $take,
                    'cost_per_unit_afn' => isset($part['cost_per_unit_afn']) ? (float) $part['cost_per_unit_afn'] : null,
                ];
                $remaining -= $take;
            }
            if ($parts !== []) {
                if ($remaining > 0 && $item->batch_id) {
                    $parts[] = [
                        'batch_id' => (int) $item->batch_id,
                        'qty' => $remaining,
                        'cost_per_unit_afn' => isset($parts[0]['cost_per_unit_afn']) ? $parts[0]['cost_per_unit_afn'] : ($saleItem ? (float) $saleItem->cost_price_afn : null),
                    ];
                }

                return $parts;
            }
        }

        if ($item->batch_id) {
            $unitCost = null;
            if (is_array($breakdown)) {
                foreach ($breakdown as $part) {
                    if ((int) ($part['batch_id'] ?? 0) === (int) $item->batch_id && isset($part['cost_per_unit_afn'])) {
                        $unitCost = (float) $part['cost_per_unit_afn'];
                        break;
                    }
                }
            }

            return [[
                'batch_id' => (int) $item->batch_id,
                'qty' => $qty,
                'cost_per_unit_afn' => $unitCost ?? ($saleItem ? (float) $saleItem->cost_price_afn : null),
            ]];
        }

        return [];
    }

    private function reverseInvestorProfitForReturn(SaleItem $saleItem, int $returnedQty): float
    {
        if ($returnedQty <= 0) {
            return 0.0;
        }

        $originalQty = max(1, (int) $saleItem->quantity);
        $ratio = min(1, $returnedQty / $originalQty);
        $lineProfit = (float) $saleItem->investor_profit_afn;
        $reversed = round($lineProfit * $ratio, 2);

        if ($reversed > 0.009) {
            $profitTx = InvestorTransaction::query()
                ->where('type', 'profit_earned')
                ->where('reference_type', SaleItem::class)
                ->where('reference_id', $saleItem->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($profitTx) {
                $investorId = (int) $profitTx->investor_id;
                $investor = \App\Models\Investor::query()->find($investorId);
                if ($investor) {
                    $wallet = $investor->getOrCreateWallet();
                    $wallet->adjustProfit('AFN', -1 * $reversed, -1 * $reversed, 1.0);

                    InvestorTransaction::record(
                        investorId: $investorId,
                        type: 'profit_withdrawn',
                        amountAfn: $reversed,
                        balanceType: 'profit',
                        options: [
                            'investment_id' => $profitTx->investment_id,
                            'currency_code' => 'AFN',
                            'amount_currency' => $reversed,
                            'exchange_rate' => 1,
                            'reference_type' => SaleReturnItem::class,
                            'reference_id' => null,
                            'description' => "Profit reversal for sale return · sale item #{$saleItem->id} · qty {$returnedQty}",
                            'transaction_date' => now()->toDateString(),
                        ]
                    );
                }
            }
        }

        $remainingQty = max(0, $originalQty - $returnedQty);
        $remainRatio = $originalQty > 0 ? ($remainingQty / $originalQty) : 0;
        $newInvestor = round(max(0, $lineProfit - $reversed), 2);
        $gross = (float) ($saleItem->gross_profit_afn ?? ((float) $saleItem->store_profit_afn + $lineProfit));
        $newGross = round($gross * $remainRatio, 2);

        $saleItem->update([
            'investor_profit_afn' => $newInvestor,
            'gross_profit_afn' => $newGross,
            'store_profit_afn' => round($newGross - $newInvestor, 2),
        ]);

        return $reversed;
    }

    /**
     * Only shop-borne expenses of completed returns go to office expenses;
     * customer-borne expenses are already deducted from the customer's credit.
     */
    private function syncItemExpense(SaleReturn $saleReturn, SaleReturnItem $item): void
    {
        if ($item->condition === SaleReturnItem::CONDITION_USED && (float) $item->expense_amount_afn <= 0) {
            $item->update(['expense_amount_afn' => $item->total_afn]);
            $item->refresh();
        }

        $amount = $saleReturn->isCompleted() && $item->isShopExpense()
            ? round($item->effectiveExpenseAmount(), 2)
            : 0.0;

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

    private function adjustSaleTotals(
        SaleReturn $saleReturn,
        float $delta,
        float $reversedCogs = 0.0,
        float $reversedInvestorProfit = 0.0,
    ): void {
        $delta = round($delta, 2);
        if ((abs($delta) < 0.01 && $reversedCogs < 0.01 && $reversedInvestorProfit < 0.01) || ! $saleReturn->sale_id) {
            return;
        }

        $sale = Sale::find($saleReturn->sale_id);
        if (! $sale) {
            return;
        }

        $newTotal = max(0, round((float) $sale->total_afn + $delta, 2));
        $newCost = max(0, round((float) $sale->total_cost_afn - $reversedCogs, 2));
        $newInvestorProfit = max(0, round((float) $sale->total_investor_profit_afn - $reversedInvestorProfit, 2));
        $newGross = round($newTotal - $newCost, 2);
        $newStoreProfit = round($newGross - $newInvestorProfit, 2);

        $sale->update([
            'subtotal_afn' => max(0, round((float) $sale->subtotal_afn + $delta, 2)),
            'total_afn' => $newTotal,
            'new_sale_amount_afn' => max(0, round((float) $sale->new_sale_amount_afn + $delta, 2)),
            'total_cost_afn' => $newCost,
            'total_profit_afn' => $newGross,
            'total_investor_profit_afn' => $newInvestorProfit,
            'store_profit_afn' => $newStoreProfit,
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
                'title' => "Sale return expense {$saleReturn->return_number}",
                'category' => 'other',
                'amount_afn' => $item->effectiveExpenseAmount(),
                'expense_date' => $saleReturn->return_date ?? now()->toDateString(),
                'payment_method' => 'cash',
                'description' => "Shop-borne expense for returned {$name} on {$saleReturn->return_number}. {$marker}",
                'created_by' => Auth::id(),
                'trash' => false,
            ]);

            $item->update(['expense_id' => $expense->id]);
        }
    }

    /* =========================================================
     |  PRIVATE HELPERS
     ========================================================= */

    protected function buildSaleItemsForReturn(Sale $sale): array
    {
        $sale->load(['items.variant.product:id,name', 'items.variant.product.brand:id,name', 'items.batch:id,batch_code']);

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
                'sale_unit_amount' => null,
                'sale_currency_code' => $sale->sale_currency_code,
            ];
        }

        return $result;
    }
}