<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\Receipt;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\ActivityLogger;
use App\Services\CustomerLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /* =========================================================
     |  INDEX
     ========================================================= */

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $onlyDue = $request->boolean('only_due');

        $customers = Customer::query()
            ->with([
                'creator:id,name',
                'balances' => fn ($q) => $q->orderBy('currency_code'),
            ])
            ->withCount('sales')
            ->notTrashed()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->when($onlyDue, fn ($q) => $q->where('current_due_afn', '>', 0))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Customer $customer) {
                $breakdown = $customer->dueBreakdown();
                $customer->setAttribute('due_breakdown', $breakdown);
                $customer->setAttribute('due_by_currency', $breakdown['by_currency']);

                return $customer;
            });

        $dueTotals = CustomerBalance::query()
            ->selectRaw('currency_code, SUM(balance_currency) as balance_currency, SUM(balance_afn) as balance_afn')
            ->whereHas('customer', fn ($q) => $q->notTrashed())
            ->groupBy('currency_code')
            ->orderBy('currency_code')
            ->get()
            ->filter(fn ($row) => abs((float) $row->balance_currency) > 0.009 || abs((float) $row->balance_afn) > 0.009)
            ->map(fn ($row) => [
                'currency_code' => strtoupper((string) $row->currency_code),
                'balance_currency' => round((float) $row->balance_currency, 2),
                'balance_afn' => round((float) $row->balance_afn, 2),
            ])
            ->values()
            ->all();

        $stats = [
            'total_customers'  => Customer::notTrashed()->count(),
            'active_customers' => Customer::notTrashed()->where('status', 'active')->count(),
            'total_due_afn'    => Customer::notTrashed()->sum('current_due_afn'),
            'debtors_count'    => Customer::notTrashed()->where('current_due_afn', '>', 0)->count(),
            'due_by_currency'  => $dueTotals,
        ];

        return Inertia::render('customers/index', [
            'customers' => $customers,
            'filters'   => [
                'search'   => $search,
                'status'   => $status,
                'only_due' => $onlyDue,
            ],
            'stats' => $stats,
        ]);
    }

    /* =========================================================
     |  CREATE
     ========================================================= */

    public function create(): Response
    {
        return Inertia::render('customers/create', [
            'currencies' => Currency::formOptions(),
        ]);
    }

    /* =========================================================
     |  STORE
     ========================================================= */

    public function store(StoreCustomerRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Customer::class);

        $data = $request->validated();
        $data['created_by'] = Auth::id();

        $openingDueCurrency = round((float) ($data['current_due_currency'] ?? $data['current_due_afn'] ?? 0), 2);
        $openingCurrencyId = ! empty($data['due_currency_id']) ? (int) $data['due_currency_id'] : null;
        unset($data['current_due_currency'], $data['due_currency_id'], $data['current_due_afn']);

        // Opening due is applied via customer_balances below — start denormalized at 0.
        $data['current_due_afn'] = 0;
        $data['credit_limit_afn'] = $data['credit_limit_afn'] ?? 0;

        $customer = DB::transaction(function () use ($data, $openingDueCurrency, $openingCurrencyId) {
            $customer = Customer::create($data);

            if ($openingDueCurrency > 0.009) {
                $currency = $openingCurrencyId
                    ? Currency::query()->notTrashed()->find((int) $openingCurrencyId)
                    : Currency::ledger();
                $currency = $currency ?: Currency::ledger();
                $code = strtoupper((string) ($currency?->code ?? 'AFN'));

                $rate = 1.0;
                $amountAfn = $openingDueCurrency;
                if ($code !== 'AFN' && $currency) {
                    $snap = app(\App\Services\ExchangeService::class)->snapshot($code, 'AFN');
                    $rate = (float) ($snap['rate'] ?? 0);
                    if ($rate <= 0) {
                        throw ValidationException::withMessages([
                            'due_currency_id' => "No exchange rate found for {$code} → AFN. Add it in Exchange Rates first.",
                        ]);
                    }
                    $amountAfn = round($openingDueCurrency * $rate, 2);
                }

                $customer->adjustBalance($code, $openingDueCurrency, $amountAfn, $rate);
            }

            return $customer->fresh();
        });

        ActivityLogger::log(
            action: 'create',
            section: 'customers',
            description: "Customer {$customer->name} created",
            subject: $customer,
            properties: ['phone' => $customer->phone],
        );

        if ($request->expectsJson()) {
            $breakdown = $customer->dueBreakdown();

            return response()->json([
                'customer' => array_merge(
                    $customer->only([
                        'id', 'name', 'phone', 'address', 'current_due_afn', 'credit_limit_afn',
                    ]),
                    [
                        'due_breakdown' => $breakdown,
                        'due_by_currency' => $breakdown['by_currency'],
                    ]
                ),
            ]);
        }

        return redirect()
            ->route('customers.index')
            ->with('success', "Customer \"{$customer->name}\" was created successfully.");
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(Customer $customer): Response
    {
        if ($customer->trash) {
            abort(404);
        }

        $customer->load([
            'creator:id,name,email',
            'balances',
            'sales' => fn ($q) => $q->notTrashed()->limit(10)->latest(),
        ]);

        $dueBreakdown = $customer->dueBreakdown();

        // Rebuild a simple ledger from sales + payments (with original currency when known).
        $ledgerEvents = collect();

        Sale::query()
            ->where('customer_id', $customer->id)
            ->notTrashed()
            ->orderBy('id')
            ->limit(200)
            ->get([
                'id', 'invoice_number', 'total_afn', 'new_sale_amount_afn',
                'sale_currency_code', 'sale_amount_currency', 'created_at',
            ])
            ->each(function (Sale $sale) use ($ledgerEvents) {
                $amountAfn = round((float) ($sale->new_sale_amount_afn ?? $sale->total_afn), 2);
                $code = strtoupper((string) ($sale->sale_currency_code ?: 'AFN'));
                $amountCurrency = $sale->sale_amount_currency !== null
                    ? round((float) $sale->sale_amount_currency, 2)
                    : $amountAfn;
                $ledgerEvents->push([
                    'sort_id' => (int) $sale->id,
                    'sort_at' => optional($sale->created_at)?->timestamp ?? 0,
                    'type' => 'sale',
                    'amount_afn' => $amountAfn,
                    'amount_currency' => $amountCurrency,
                    'currency_code' => $code,
                    'notes' => $sale->invoice_number ? "Sale {$sale->invoice_number}" : 'Sale',
                    'created_at' => $sale->created_at,
                    'signed' => $amountAfn,
                ]);
            });

        SalePayment::query()
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->limit(200)
            ->get([
                'id', 'amount_afn', 'amount_currency', 'currency_code',
                'notes', 'reference_number', 'paid_at', 'created_at',
            ])
            ->each(function (SalePayment $payment) use ($ledgerEvents) {
                $amountAfn = round((float) $payment->amount_afn, 2);
                $code = strtoupper((string) ($payment->currency_code ?: 'AFN'));
                $amountCurrency = $payment->amount_currency !== null
                    ? round((float) $payment->amount_currency, 2)
                    : $amountAfn;
                $ledgerEvents->push([
                    'sort_id' => (int) $payment->id,
                    'sort_at' => optional($payment->paid_at ?? $payment->created_at)?->timestamp ?? 0,
                    'type' => 'payment',
                    'amount_afn' => $amountAfn,
                    'amount_currency' => $amountCurrency,
                    'currency_code' => $code,
                    'notes' => $payment->notes
                        ?: ($payment->reference_number ? "Ref {$payment->reference_number}" : 'Payment'),
                    'created_at' => $payment->paid_at ?? $payment->created_at,
                    'signed' => -1 * $amountAfn,
                ]);
            });

        $running = 0.0;
        $transactions = $ledgerEvents
            ->sortBy([
                ['sort_at', 'asc'],
                ['sort_id', 'asc'],
            ])
            ->values()
            ->take(200)
            ->map(function (array $row, int $index) use (&$running) {
                $running = round($running + (float) $row['signed'], 2);

                return [
                    'id' => $index + 1,
                    'type' => $row['type'],
                    'amount_afn' => number_format((float) $row['amount_afn'], 2, '.', ''),
                    'amount_currency' => number_format((float) $row['amount_currency'], 2, '.', ''),
                    'currency_code' => $row['currency_code'],
                    'balance_after' => number_format($running, 2, '.', ''),
                    'notes' => $row['notes'],
                    'created_at' => $row['created_at'],
                ];
            })
            ->values();

        $lastPayment = $customer->payments()
            ->reorder()
            ->latest('id')
            ->first(['paid_at', 'created_at']);
        $lastPaymentAt = $lastPayment?->paid_at ?? $lastPayment?->created_at;

        $receipts = Receipt::query()
            ->where('customer_id', $customer->id)
            ->with('sale:id,invoice_number')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (Receipt $receipt) => [
                'id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'receipt_type' => $receipt->receipt_type,
                'amount_afn' => $receipt->amount_afn,
                'amount_currency' => $receipt->amount_currency,
                'currency_code' => $receipt->currency_code ?: 'AFN',
                'remaining_due_afn' => $receipt->remaining_due_afn,
                'previous_due_afn' => round((float) $receipt->amount_afn + (float) $receipt->remaining_due_afn, 2),
                'invoice_number' => $receipt->sale?->invoice_number,
                'created_at' => $receipt->created_at,
            ]);

        return Inertia::render('customers/show', [
            'customer' => $customer,
            'transactions' => $transactions,
            'receipts' => $receipts,
            'due_breakdown' => $dueBreakdown,
            'currencies' => Currency::formOptions(),
            'stats'    => [
                'total_sales'      => $customer->sales()->notTrashed()->count(),
                'total_amount'     => $customer->sales()->notTrashed()->sum('total_afn') ?? 0,
                'total_paid'       => $customer->payments()->sum('amount_afn') ?? 0,
                'current_due_afn'  => $dueBreakdown['total_afn'],
                'due_by_currency'  => $dueBreakdown['by_currency'],
                'credit_limit_afn' => $customer->credit_limit_afn,
                'available_credit' => $customer->availableCreditAfn(),
                'last_sale_at'     => $customer->last_sale_at,
                'last_payment_at'  => $lastPaymentAt,
            ],
        ]);
    }

    /* =========================================================
     |  EDIT
     ========================================================= */

    public function edit(Customer $customer): Response
    {
        if ($customer->trash) {
            abort(404);
        }

        return Inertia::render('customers/edit', [
            'customer' => $customer,
        ]);
    }

    /* =========================================================
     |  UPDATE
     ========================================================= */

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $customer);

        $data = $request->validated();

        $customer->update($data);

        ActivityLogger::log(
            action: 'update',
            section: 'customers',
            description: "Customer {$customer->name} updated",
            subject: $customer,
            properties: ['phone' => $customer->phone],
        );

        if ($request->expectsJson()) {
            $customer->load('balances');
            $breakdown = $customer->dueBreakdown();

            return response()->json([
                'customer' => array_merge(
                    $customer->only(['id', 'name', 'phone', 'address', 'notes', 'status', 'current_due_afn', 'credit_limit_afn']),
                    [
                        'due_breakdown' => $breakdown,
                        'due_by_currency' => $breakdown['by_currency'],
                    ]
                ),
            ]);
        }

        return redirect()
            ->route('customers.index')
            ->with('success', "Customer \"{$customer->name}\" was updated successfully.");
    }

    /* =========================================================
     |  DESTROY / RESTORE / FORCE DELETE / TRASH
     ========================================================= */

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $name = $customer->name;

        $customer->update(['trash' => true]);

        ActivityLogger::log(
            action: 'soft_delete',
            section: 'customers',
            description: "Customer {$name} moved to trash",
            subject: $customer,
        );

        return redirect()
            ->route('customers.index')
            ->with('success', "Customer \"{$name}\" was moved to trash.");
    }

    public function restore(int $id): RedirectResponse
    {
        $customer = Customer::query()
            ->withoutGlobalScopes()
            ->where('trash', true)
            ->findOrFail($id);

        $customer->update(['trash' => false]);

        return redirect()
            ->route('customers.index')
            ->with('success', "Customer \"{$customer->name}\" was restored.");
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $customer = Customer::query()
            ->withoutGlobalScopes()
            ->where('trash', true)
            ->findOrFail($id);
        $name     = $customer->name;
        $customer->forceDelete();

        return redirect()
            ->route('customers.index')
            ->with('success', "Customer \"{$name}\" was permanently deleted.");
    }

    public function trash(): Response
    {
        $customers = Customer::query()
            ->withoutGlobalScopes()
            ->where('trash', true)
            ->with('creator:id,name')
            ->orderByDesc('updated_at')
            ->paginate(15);

        return Inertia::render('customers/trash', [
            'customers' => $customers,
        ]);
    }

    /* =========================================================
     |  TOGGLE STATUS
     ========================================================= */

    public function toggleStatus(Customer $customer): RedirectResponse
    {
        $customer->update([
            'status' => $customer->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', "Customer status changed to {$customer->status}.");
    }

    /* =========================================================
     |  RECEIVE PAYMENT — collect debt without a new purchase
     ========================================================= */

    public function receivePayment(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'amount_currency'  => ['required', 'numeric', 'min:0.01'],
            'currency_id'      => ['nullable', 'integer', 'exists:currencies,id'],
            // Legacy alias — treat as AFN amount when amount_currency missing (validated above).
            'amount_afn'       => ['nullable', 'numeric', 'min:0.01'],
            'payment_method'   => ['required', 'in:cash,card,bank,mobile,credit,other'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes'            => ['nullable', 'string'],
        ]);

        if (! isset($validated['amount_currency']) && isset($validated['amount_afn'])) {
            $validated['amount_currency'] = $validated['amount_afn'];
            $validated['currency_id'] = $validated['currency_id'] ?? Currency::ledger()?->id;
        }

        $receipt = DB::transaction(function () use ($validated, $customer) {
            $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $breakdown = $customer->dueBreakdown();
            $previousAfn = round((float) $breakdown['total_afn'], 2);

            if ($previousAfn <= 0.009) {
                throw ValidationException::withMessages([
                    'amount_currency' => 'This customer has no outstanding due.',
                ]);
            }

            $payment = SalePayment::createWithSnapshot([
                'sale_id'           => null,
                'customer_id'       => $customer->id,
                'amount_currency'   => $validated['amount_currency'],
                'currency_id'       => $validated['currency_id'] ?? null,
                'remaining_due_afn' => 0,
                'payment_method'    => $validated['payment_method'],
                'reference_number'  => $validated['reference_number'] ?? null,
                'notes'             => $validated['notes'] ?? null,
                'paid_at'           => now(),
                'received_by'       => Auth::id(),
            ]);

            $amountAfn = round((float) $payment->amount_afn, 2);

            if ($amountAfn > $previousAfn + 0.01) {
                throw ValidationException::withMessages([
                    'amount_currency' => 'The payment is larger than the customer current due.',
                ]);
            }

            // Apply to oldest open sales first (AFN remaining on each sale).
            $openSales = $customer->sales()
                ->notTrashed()
                ->where('remaining_due_afn', '>', 0)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $sale = $openSales->first();

            $payment->update(['sale_id' => $sale?->id]);

            $left = $amountAfn;
            foreach ($openSales as $openSale) {
                if ($left <= 0.01) {
                    break;
                }
                $saleRemaining = round((float) $openSale->remaining_due_afn, 2);
                $applied = round(min($left, $saleRemaining), 2);
                $saleLeft = round(max(0, $saleRemaining - $applied), 2);
                $openSale->update([
                    'paid_afn'          => round((float) $openSale->paid_afn + $applied, 2),
                    'due_afn'           => $saleLeft,
                    'remaining_due_afn' => $saleLeft,
                    'payment_status'    => $saleLeft <= 0.01 ? 'paid' : 'partial',
                ]);
                $left = round($left - $applied, 2);
            }

            app(CustomerLedgerService::class)->recordPayment($payment);
            $customer->refresh();
            $customer->load('balances');
            $remainingBreakdown = $customer->dueBreakdown();
            $remaining = round((float) $remainingBreakdown['total_afn'], 2);

            $payment->update(['remaining_due_afn' => $remaining]);

            if ($sale) {
                $sale->update([
                    'remaining_due_by_currency' => $remainingBreakdown['by_currency'],
                ]);
            }

            return Receipt::create([
                'customer_id'       => $customer->id,
                'sale_id'           => $sale?->id,
                'sale_payment_id'   => $payment->id,
                'receipt_number'    => Receipt::generateReceiptNumber(),
                'currency_id'       => $payment->currency_id,
                'currency_code'     => $payment->currency_code,
                'amount_afn'        => $amountAfn,
                'amount_currency'   => $payment->amount_currency ?? $amountAfn,
                'exchange_rate_to_afn' => $payment->rate_to_ledger ?? 1,
                'rate_effective_at' => $payment->rate_effective_at,
                'remaining_due_afn' => $remaining,
                'receipt_type'      => 'payment_receipt',
                'notes'             => $validated['notes'] ?? null,
                'created_by'        => Auth::id(),
            ]);
        });

        return redirect()
            ->route('receipts.print', $receipt)
            ->with('success', 'Payment recorded.');
    }
}
