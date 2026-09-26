<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\AppSetting;
use App\Models\ExchangeRateHistory;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $suppliers = Supplier::query()
            ->with(['creator:id,name'])
            ->withCount('purchases')
            ->notTrashed()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('contact_person', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $balances = Supplier::balancesFor($suppliers->pluck('id'));
        $suppliers->getCollection()->transform(function (Supplier $supplier) use ($balances) {
            $row = $balances[$supplier->id] ?? ['paid_afn' => 0, 'balance_afn' => 0];
            $supplier->setAttribute('paid_afn', $row['paid_afn']);
            $supplier->setAttribute('balance_afn', $row['balance_afn']);

            return $supplier;
        });

        $openBills = SupplierInvoice::query()
            ->where('due_afn', '>', 0.009)
            ->whereHas('supplier', fn ($query) => $query->notTrashed())
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->get(['id', 'supplier_id', 'invoice_no', 'due_afn', 'purchase_date']);

        $debtorIds = $openBills->pluck('supplier_id')
            ->merge(Supplier::query()->notTrashed()->where('opening_balance', '>', 0.009)->pluck('id'))
            ->unique()
            ->values();

        $debtorBalances = Supplier::balancesFor($debtorIds);
        $debtors = Supplier::query()
            ->notTrashed()
            ->whereIn('id', $debtorIds)
            ->orderBy('name')
            ->get(['id', 'name', 'phone'])
            ->map(function (Supplier $supplier) use ($debtorBalances, $openBills) {
                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'phone' => $supplier->phone,
                    'balance_afn' => $debtorBalances[$supplier->id]['balance_afn'] ?? 0,
                    'bills' => $openBills->where('supplier_id', $supplier->id)->values()->map(fn (SupplierInvoice $bill) => [
                        'id' => $bill->id,
                        'invoice_no' => $bill->invoice_no,
                        'due_afn' => (float) $bill->due_afn,
                        'purchase_date' => optional($bill->purchase_date)->toDateString(),
                    ])->all(),
                ];
            })
            ->filter(fn (array $row) => $row['balance_afn'] > 0.009)
            ->values();

        $exchangeRate = Schema::hasTable('exchange_rate_histories')
            ? (float) (ExchangeRateHistory::latestRate()?->usd_to_afn ?? 0)
            : 0;

        return Inertia::render('suppliers/index', [
            'suppliers' => $suppliers,
            'filters'   => [
                'search' => $search,
                'status' => $status,
            ],
            'debtors' => $debtors,
            'exchangeRate' => $exchangeRate > 0 ? $exchangeRate : null,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('suppliers/create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $bill = $request->file('bill_image');
        unset($data['invoice_no'], $data['purchase_date'], $data['exchange_rate'], $data['total_afn'], $data['paid_now'], $data['bill_image']);
        $data['user_id'] = Auth::id();
        $data['opening_balance'] = 0;

        $supplier = Supplier::create($data);
        $total = round((float) ($request->input('total_afn') ?? 0), 2);

        if ($total > 0) {
            $path = $bill ? $bill->store('bills', 'public') : null;
            $paidNow = round(min($total, (float) ($request->input('paid_now') ?? 0)), 2);
            $rate = round((float) ($request->input('exchange_rate') ?? 0), 2);
            $invoice = SupplierInvoice::create([
                'supplier_id' => $supplier->id,
                'invoice_no' => $request->input('invoice_no') ?: ('OPEN-'.$supplier->id),
                'purchase_date' => $request->input('purchase_date') ?: now()->toDateString(),
                'exchange_rate' => $rate,
                'total_afn' => $total,
                'paid_afn' => $paidNow,
                'due_afn' => round($total - $paidNow, 2),
                'notes' => $data['notes'] ?? null,
                'bill_image' => $path,
                'created_by' => Auth::id(),
            ]);
            if ($paidNow > 0) {
                SupplierPayment::create([
                    'supplier_id' => $supplier->id,
                    'receipt_number' => SupplierPayment::nextReceiptNumber(),
                    'amount_afn' => $paidNow,
                    'balance_after' => $invoice->due_afn,
                    'payment_date' => $invoice->purchase_date,
                    'exchange_rate' => $rate,
                    'payment_method' => 'cash',
                    'notes' => $invoice->invoice_no,
                    'user_id' => Auth::id(),
                ]);
            }
        }

        return redirect()
            ->route('suppliers.transactions', ['supplier_id' => $supplier->id])
            ->with('success', "Supplier \"{$supplier->name}\" was created successfully.");
    }

    public function transactions(Request $request): Response
    {
        $supplierId = $request->query('supplier_id');
        $invoices = SupplierInvoice::query()
            ->with('supplier:id,name')
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $base = SupplierInvoice::query()->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId));

        return Inertia::render('suppliers/transactions', [
            'invoices' => $invoices,
            'suppliers' => Supplier::query()->notTrashed()->orderBy('name')->get(['id', 'name']),
            'filters' => ['supplier_id' => $supplierId],
            'summary' => [
                'total_afn' => round((float) (clone $base)->sum('total_afn'), 2),
                'paid_afn' => round((float) (clone $base)->sum('paid_afn'), 2),
                'due_afn' => round((float) (clone $base)->sum('due_afn'), 2),
                'count' => (clone $base)->count(),
            ],
        ]);
    }

    public function show(Supplier $supplier): Response
    {
        if ($supplier->trash) {
            abort(404);
        }

        $supplier->load(['creator:id,name,email']);
        $account = $supplier->account();
        $payments = $supplier->payments()
            ->with('user:id,name')
            ->latest('payment_date')
            ->latest('id')
            ->limit(30)
            ->get();

        return Inertia::render('suppliers/show', [
            'supplier' => $supplier,
            'account' => [
                'opening_balance' => $account['opening_balance'],
                'opening_remaining' => $account['opening_remaining'],
                'purchase_remaining' => $account['purchase_remaining'],
                'balance' => $account['balance'],
                'total_purchases' => $supplier->purchases()->notTrashed()->count(),
                'total_paid' => round((float) $supplier->payments()->sum('amount_afn'), 2),
            ],
            'debts' => $account['purchases']->map(fn (SupplierInvoice $row) => [
                'id' => $row->id,
                'invoice_no' => $row->invoice_no,
                'purchase_date' => optional($row->purchase_date)->toDateString(),
                'exchange_rate' => (float) $row->exchange_rate,
                'total_afn' => (float) $row->total_afn,
                'paid_afn' => (float) $row->paid_afn,
                'due_afn' => (float) $row->due_afn,
                'notes' => $row->notes,
                'bill_image' => $row->bill_image,
            ]),
            'payments' => $payments->map(fn (SupplierPayment $payment) => [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'amount_afn' => (float) $payment->amount_afn,
                'balance_after' => (float) $payment->balance_after,
                'payment_date' => optional($payment->payment_date)->toDateString(),
                'payment_method' => $payment->payment_method,
                'notes' => $payment->notes,
                'user' => $payment->user?->name,
            ]),
        ]);
    }

    public function createPayment(Supplier $supplier): Response
    {
        abort_if($supplier->trash, 404);
        $account = $supplier->account();

        return Inertia::render('suppliers/payment', [
            'supplier' => $supplier->only('id', 'name', 'phone'),
            'balance' => $account['balance'],
            'debts' => $account['purchases']->map(fn (SupplierInvoice $row) => [
                'id' => $row->id,
                'invoice_no' => $row->invoice_no,
                'due_afn' => (float) $row->due_afn,
            ]),
        ]);
    }

    public function storePayment(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_if($supplier->trash, 404);

        $data = $request->validate([
            'amount_afn' => ['required', 'numeric', 'min:0.01'],
            'invoice_id' => ['nullable', 'integer'],
            'payment_date' => ['required', 'date'],
            'exchange_rate' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,bank,mobile,other'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payment = DB::transaction(function () use ($data, $supplier) {
            $supplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $account = $supplier->account();
            $amount = round((float) $data['amount_afn'], 2);

            if ($amount > $account['balance'] + 0.01) {
                throw ValidationException::withMessages([
                    'amount_afn' => 'The amount is larger than the balance due.',
                ]);
            }

            $targets = $account['purchases'];
            if (! empty($data['invoice_id'])) {
                $targets = $targets->where('id', (int) $data['invoice_id'])->values();
                $cap = round((float) ($targets->first()->due_afn ?? 0), 2);
                if ($targets->isEmpty() || $amount > $cap + 0.01) {
                    throw ValidationException::withMessages([
                        'invoice_id' => 'This bill has no balance, or the amount is larger than what is left.',
                    ]);
                }
            }

            $left = $amount;
            $allocations = [];
            foreach ($targets as $row) {
                if ($left <= 0.009) {
                    break;
                }
                $apply = round(min($left, (float) $row->due_afn), 2);
                $row->update([
                    'paid_afn' => round((float) $row->paid_afn + $apply, 2),
                    'due_afn' => round(max(0, (float) $row->due_afn - $apply), 2),
                ]);
                $allocations[] = ['invoice_no' => $row->invoice_no, 'amount_afn' => $apply];
                $left = round($left - $apply, 2);
            }

            if ($left > 0.009 && $account['opening_remaining'] > 0.009 && empty($data['invoice_id'])) {
                $apply = round(min($left, $account['opening_remaining']), 2);
                $supplier->update([
                    'opening_balance' => round(max(0, $account['opening_remaining'] - $apply), 2),
                ]);
                $allocations[] = ['invoice_no' => 'Opening balance', 'amount_afn' => $apply];
            }

            $payment = SupplierPayment::create([
                'supplier_id' => $supplier->id,
                'receipt_number' => SupplierPayment::nextReceiptNumber(),
                'amount_afn' => $amount,
                'balance_after' => round(max(0, $account['balance'] - $amount), 2),
                'payment_date' => $data['payment_date'],
                'exchange_rate' => round((float) $data['exchange_rate'], 2),
                'payment_method' => $data['payment_method'],
                'notes' => trim(($data['notes'] ?? '').' '.collect($allocations)->map(fn ($row) => $row['invoice_no'].': '.$row['amount_afn'])->implode(' | ')),
                'user_id' => Auth::id(),
            ]);

            foreach ($allocations as $allocation) {
                SupplierPaymentAllocation::create([
                    'supplier_payment_id' => $payment->id,
                    'product_id' => null,
                    'amount_afn' => $allocation['amount_afn'],
                ]);
            }

            return $payment;
        });

        return redirect()
            ->route('suppliers.payments.receipt', [$supplier, $payment])
            ->with('success', 'Payment recorded.');
    }

    public function invoiceReceipt(Supplier $supplier, SupplierInvoice $invoice): Response
    {
        abort_if($supplier->trash || (int) $invoice->supplier_id !== (int) $supplier->id, 404);
        $account = $supplier->account();

        return Inertia::render('suppliers/receipt', [
            'kind' => 'credit',
            'company' => AppSetting::bill(),
            'supplier' => $supplier->only('id', 'name', 'phone'),
            'printedAt' => now()->format('Y-m-d H:i'),
            'receipt' => [
                'number' => $invoice->invoice_no,
                'date' => optional($invoice->purchase_date)->toDateString(),
                'title' => 'Credit bill',
                'lines' => [[
                    'name' => $invoice->invoice_no,
                    'quantity' => null,
                    'amount' => (float) $invoice->total_afn,
                ]],
                'amount' => (float) $invoice->total_afn,
                'paid' => (float) $invoice->paid_afn,
                'remaining' => (float) $invoice->due_afn,
                'balance' => $account['balance'],
                'status' => (float) $invoice->due_afn <= 0.01 ? 'PAID' : 'CREDIT',
                'notes' => $invoice->notes,
            ],
        ]);
    }

    public function storeBill(Request $request, Supplier $supplier, SupplierInvoice $invoice): RedirectResponse
    {
        abort_if($supplier->trash || (int) $invoice->supplier_id !== (int) $supplier->id, 404);

        $data = $request->validate([
            'bill_image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ]);

        if ($invoice->bill_image && Storage::disk('public')->exists($invoice->bill_image)) {
            Storage::disk('public')->delete($invoice->bill_image);
        }

        $invoice->update([
            'bill_image' => $data['bill_image']->store('bills', 'public'),
        ]);

        return back()->with('success', 'Bill image saved.');
    }

    public function purchaseReceipt(Supplier $supplier, Product $product): Response
    {
        abort_if($supplier->trash || (int) $product->supplier_id !== (int) $supplier->id, 404);
        $account = $supplier->account();
        $row = $account['purchases']->firstWhere('id', $product->id);

        return Inertia::render('suppliers/receipt', [
            'kind' => 'credit',
            'company' => AppSetting::bill(),
            'supplier' => $supplier->only('id', 'name', 'phone'),
            'printedAt' => now()->format('Y-m-d H:i'),
            'receipt' => [
                'number' => $product->invoice_number ?: 'CR-'.$product->id,
                'date' => optional($product->purchase_date)->toDateString(),
                'title' => 'Credit bill',
                'lines' => [[
                    'name' => $product->name,
                    'quantity' => (float) $product->quantity,
                    'amount' => round((float) $product->line_total, 2),
                ]],
                'amount' => round((float) $product->line_total, 2),
                'paid' => $row ? (float) $row->paid_afn : round((float) $product->line_total, 2),
                'remaining' => $row ? (float) $row->remaining_afn : 0,
                'balance' => $account['balance'],
                'status' => $row ? 'OPEN' : 'PAID',
            ],
        ]);
    }

    public function paymentReceipt(Supplier $supplier, SupplierPayment $payment): Response
    {
        abort_if($supplier->trash || (int) $payment->supplier_id !== (int) $supplier->id, 404);
        $payment->load('allocations.product:id,name');

        return Inertia::render('suppliers/receipt', [
            'kind' => 'payment',
            'company' => AppSetting::bill(),
            'supplier' => $supplier->only('id', 'name', 'phone'),
            'printedAt' => now()->format('Y-m-d H:i'),
            'receipt' => [
                'number' => $payment->receipt_number,
                'date' => optional($payment->payment_date)->toDateString(),
                'title' => 'Payment receipt',
                'method' => $payment->payment_method,
                'lines' => $payment->allocations->map(fn ($row) => [
                    'name' => $row->product?->name ?? 'Opening balance',
                    'quantity' => null,
                    'amount' => (float) $row->amount_afn,
                ])->values(),
                'amount' => (float) $payment->amount_afn,
                'paid' => (float) $payment->amount_afn,
                'remaining' => (float) $payment->balance_after,
                'balance' => (float) $payment->balance_after,
                'status' => (float) $payment->balance_after <= 0.01 ? 'SETTLED' : 'BALANCE LEFT',
                'notes' => $payment->notes,
            ],
        ]);
    }

    private function refreshPurchaseStatus(int $productId): void
    {
        $product = Product::query()->whereKey($productId)->lockForUpdate()->first();
        if (! $product) {
            return;
        }

        $paid = round((float) SupplierPaymentAllocation::query()
            ->where('product_id', $productId)
            ->sum('amount_afn'), 2);
        $total = round((float) $product->line_total, 2);
        $product->update([
            'payment_status' => $paid + 0.009 >= $total ? 'paid' : ($paid > 0.009 ? 'partial' : 'credit'),
        ]);
    }

    public function edit(Supplier $supplier): Response
    {
        if ($supplier->trash) {
            abort(404);
        }

        return Inertia::render('suppliers/edit', [
            'supplier' => $supplier,
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();
        $data['opening_balance'] = $data['opening_balance'] ?? 0;

        $supplier->update($data);

        return redirect()
            ->route('suppliers.index')
            ->with('success', "Supplier \"{$supplier->name}\" was updated successfully.");
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $name = $supplier->name;

        $supplier->update([
            'trash'   => true,
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('suppliers.index')
            ->with('success', "Supplier \"{$name}\" was moved to trash.");
    }

    public function restore(int $id): RedirectResponse
    {
        $supplier = Supplier::onlyTrashed()->findOrFail($id);

        $supplier->update([
            'trash'   => false,
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('suppliers.index')
            ->with('success', "Supplier \"{$supplier->name}\" was restored.");
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $supplier = Supplier::onlyTrashed()->findOrFail($id);
        $name = $supplier->name;
        $supplier->forceDelete();

        return redirect()
            ->route('suppliers.index')
            ->with('success', "Supplier \"{$name}\" was permanently deleted.");
    }

    public function trash(): Response
    {
        $suppliers = Supplier::onlyTrashed()
            ->with(['creator:id,name'])
            ->orderByDesc('updated_at')
            ->paginate(15);

        return Inertia::render('suppliers/trash', [
            'suppliers' => $suppliers,
        ]);
    }

    public function toggleStatus(Supplier $supplier): RedirectResponse
    {
        $supplier->update([
            'status'  => $supplier->status === 'active' ? 'inactive' : 'active',
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', "Supplier status changed to {$supplier->status}.");
    }
}