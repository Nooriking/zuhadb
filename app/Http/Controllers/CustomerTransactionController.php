<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Services\CustomerLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerTransactionController extends Controller
{
    /* =========================================================
     |  INDEX — لیست همه تراکنش‌ها با فیلتر
     ========================================================= */

    public function index(Request $request): Response
    {
        $search   = $request->query('search');
        $type     = $request->query('type');
        $customer = $request->query('customer_id');
        $fromDate = $request->query('from_date');
        $toDate   = $request->query('to_date');

        $transactions = CustomerTransaction::query()
            ->with([
                'customer:id,name,phone,current_due_afn',
                'creator:id,name',
            ])
            ->when($search, function ($q, $s) {
                $q->where(function ($q) use ($s) {
                    $q->where('notes', 'like', "%{$s}%")
                      ->orWhereHas('customer', function ($q) use ($s) {
                          $q->where('name', 'like', "%{$s}%")
                            ->orWhere('phone', 'like', "%{$s}%");
                      });
                });
            })
            ->when($type, fn ($q, $v) => $q->where('type', $v))
            ->when($customer, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($fromDate, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($toDate, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        /* ═══ Stats ═══ */
        $stats = [
            'total_transactions' => CustomerTransaction::count(),
            'total_sales_afn'    => CustomerTransaction::sales()->sum('amount_afn'),
            'total_payments_afn' => abs(CustomerTransaction::payments()->sum('amount_afn')),
            'total_returns_afn'  => abs(CustomerTransaction::returns()->sum('amount_afn')),
            'current_total_due'  => Customer::notTrashed()->sum('current_due_afn'),
        ];

        return Inertia::render('customer-transactions/index', [
            'transactions' => $transactions,
            'customers'    => Customer::notTrashed()
                ->select('id', 'name', 'phone')
                ->orderBy('name')
                ->get(),
            'filters' => [
                'search'      => $search,
                'type'        => $type,
                'customer_id' => $customer,
                'from_date'   => $fromDate,
                'to_date'     => $toDate,
            ],
            'stats' => $stats,
        ]);
    }

    /* =========================================================
     |  SHOW — جزئیات یک تراکنش
     ========================================================= */

    public function show(CustomerTransaction $transaction): Response
    {
        $transaction->load([
            'customer:id,name,phone,address,current_due_afn,credit_limit_afn',
            'creator:id,name,email',
            'reference',
        ]);

        /* ═══ تراکنش‌های قبل و بعد مشتری (برای context) ═══ */
        $beforeTransactions = CustomerTransaction::query()
            ->where('customer_id', $transaction->customer_id)
            ->where('id', '<', $transaction->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $afterTransactions = CustomerTransaction::query()
            ->where('customer_id', $transaction->customer_id)
            ->where('id', '>', $transaction->id)
            ->orderBy('id')
            ->limit(5)
            ->get();

        return Inertia::render('customer-transactions/show', [
            'transaction'        => $transaction,
            'beforeTransactions' => $beforeTransactions,
            'afterTransactions'  => $afterTransactions,
        ]);
    }

    /* =========================================================
     |  BY CUSTOMER — همه تراکنش‌های یک مشتری
     ========================================================= */

    public function byCustomer(Request $request, Customer $customer): Response
    {
        if ($customer->trash) {
            abort(404);
        }

        $type = $request->query('type');

        $transactions = CustomerTransaction::query()
            ->where('customer_id', $customer->id)
            ->when($type, fn ($q, $v) => $q->where('type', $v))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('customer-transactions/by-customer', [
            'customer'     => $customer->load('creator:id,name'),
            'transactions' => $transactions,
            'filters'      => ['type' => $type],
            'stats'        => [
                'current_due_afn'  => $customer->current_due_afn,
                'credit_limit_afn' => $customer->credit_limit_afn,
                'available_credit' => $customer->availableCreditAfn(),
                'total_sales'      => CustomerTransaction::where('customer_id', $customer->id)->sales()->sum('amount_afn'),
                'total_payments'   => abs(CustomerTransaction::where('customer_id', $customer->id)->payments()->sum('amount_afn')),
                'total_returns'    => abs(CustomerTransaction::where('customer_id', $customer->id)->returns()->sum('amount_afn')),
                'total_adjustments'=> CustomerTransaction::where('customer_id', $customer->id)->adjustments()->sum('amount_afn'),
                'transaction_count'=> CustomerTransaction::where('customer_id', $customer->id)->count(),
            ],
        ]);
    }

    /* =========================================================
     |  STORE ADJUSTMENT — ثبت تعدیل دستی
     ========================================================= */

    public function storeAdjustment(Request $request, Customer $customer): RedirectResponse
    {
        if ($customer->trash) {
            abort(404);
        }

        $validated = $request->validate([
            'amount_afn' => ['required', 'numeric', 'not_in:0'],
            'notes'      => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($validated, $customer) {
            $amountAfn = (float) $validated['amount_afn'];

            /* ═══ محاسبه قرض جدید ═══ */
            $newBalance = (float) $customer->current_due_afn + $amountAfn;

            /* ═══ جلوگیری از قرض منفی ═══ */
            if ($newBalance < 0) {
                throw new \Exception('Customer due cannot be negative.');
            }

            $ledger = app(CustomerLedgerService::class);
            $ledger->recordAdjustment(
                customer:     $customer,
                amountAfn:    $amountAfn,
                balanceAfter: $newBalance,
                notes:        $validated['notes'],
            );

            /* ═══ آپدیت customers ═══ */
            $customer->update([
                'current_due_afn' => $newBalance,
            ]);
        });

        return back()->with('success', 'Adjustment saved.');
    }

    /* =========================================================
     |  DESTROY — حذف تعدیل دستی (فقط adjustment)
     ========================================================= */

    public function destroy(CustomerTransaction $transaction): RedirectResponse
    {
        /* ═══ فقط adjustment قابل حذف است ═══ */
        if ($transaction->type !== CustomerTransaction::TYPE_ADJUSTMENT) {
            return back()->with('error', 'Only manual adjustments can be deleted.');
        }

        DB::transaction(function () use ($transaction) {
            $customer = $transaction->customer;

            if ($customer) {
                /* ═══ برگرداندن اثر تعدیل ═══ */
                $revertAmount = -1 * (float) $transaction->amount_afn;
                $newBalance   = (float) $customer->current_due_afn + $revertAmount;

                $customer->update([
                    'current_due_afn' => max(0, $newBalance),
                ]);
            }

            $transaction->delete();
        });

        return back()->with('success', 'Adjustment deleted.');
    }
}