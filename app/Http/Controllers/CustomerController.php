<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Receipt;
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
            ->with('creator:id,name')
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
            ->withQueryString();

        $stats = [
            'total_customers'  => Customer::notTrashed()->count(),
            'active_customers' => Customer::notTrashed()->where('status', 'active')->count(),
            'total_due_afn'    => Customer::notTrashed()->sum('current_due_afn'),
            'debtors_count'    => Customer::notTrashed()->where('current_due_afn', '>', 0)->count(),
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
        return Inertia::render('customers/create');
    }

    /* =========================================================
     |  STORE
     ========================================================= */

    public function store(StoreCustomerRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Customer::class);

        $data = $request->validated();
        $data['created_by'] = Auth::id();

        $data['current_due_afn'] = $data['current_due_afn'] ?? 0;

        $customer = Customer::create($data);

        ActivityLogger::log(
            action: 'create',
            section: 'customers',
            description: "Customer {$customer->name} created",
            subject: $customer,
            properties: ['phone' => $customer->phone],
        );

        if ($request->expectsJson()) {
            return response()->json([
                'customer' => $customer->only([
                    'id', 'name', 'phone', 'address', 'current_due_afn', 'credit_limit_afn',
                ]),
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
            'sales' => fn ($q) => $q->notTrashed()->limit(10)->latest(),
        ]);

        $transactions = $customer->transactions()
            ->reorder()
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'type', 'amount_afn', 'balance_after', 'notes', 'created_at']);

        $lastPaymentAt = $customer->transactions()
            ->reorder()
            ->where('type', 'payment')
            ->latest('id')
            ->value('created_at');

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
                'remaining_due_afn' => $receipt->remaining_due_afn,
                'previous_due_afn' => round((float) $receipt->amount_afn + (float) $receipt->remaining_due_afn, 2),
                'invoice_number' => $receipt->sale?->invoice_number,
                'created_at' => $receipt->created_at,
            ]);

        return Inertia::render('customers/show', [
            'customer' => $customer,
            'transactions' => $transactions,
            'receipts' => $receipts,
            'stats'    => [
                'total_sales'      => $customer->sales()->notTrashed()->count(),
                'total_amount'     => $customer->sales()->notTrashed()->sum('total_afn') ?? 0,
                'total_paid'       => $customer->payments()->sum('amount_afn') ?? 0,
                'current_due_afn'  => $customer->current_due_afn,
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
            return response()->json(['customer' => $customer->only(['id', 'name', 'phone', 'address', 'notes', 'status'])]);
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
     |  RECEIVE PAYMENT — پرداخت قرض بدون خرید جدید
     ========================================================= */

    public function receivePayment(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'amount_afn'       => ['required', 'numeric', 'min:0.01'],
            'payment_method'   => ['required', 'in:cash,card,bank,mobile,credit,other'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes'            => ['nullable', 'string'],
        ]);

        $receipt = DB::transaction(function () use ($validated, $customer) {
            $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $amount = round((float) $validated['amount_afn'], 2);
            $previous = round((float) $customer->current_due_afn, 2);

            if ($amount > $previous + 0.01) {
                throw ValidationException::withMessages([
                    'amount_afn' => 'The payment is larger than the customer current due.',
                ]);
            }

            $remaining = round(max(0, $previous - $amount), 2);
            $openSales = $customer->sales()
                ->notTrashed()
                ->where('remaining_due_afn', '>', 0)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();
            $sale = $openSales->first();

            $payment = SalePayment::create([
                'sale_id'           => $sale?->id,
                'customer_id'       => $customer->id,
                'amount_afn'        => $amount,
                'remaining_due_afn' => $remaining,
                'payment_method'    => $validated['payment_method'],
                'reference_number'  => $validated['reference_number'] ?? null,
                'notes'             => $validated['notes'] ?? null,
                'paid_at'           => now(),
                'received_by'       => Auth::id(),
            ]);

            $left = $amount;
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

            $customer->update([
                'current_due_afn' => $remaining,
            ]);

            return Receipt::create([
                'customer_id'       => $customer->id,
                'sale_id'           => $sale?->id,
                'sale_payment_id'   => $payment->id,
                'receipt_number'    => Receipt::generateReceiptNumber(),
                'amount_afn'        => $amount,
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