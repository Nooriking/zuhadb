<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Receipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    /* =========================================================
     |  INDEX
     ========================================================= */

    public function index(Request $request): Response
    {
        $search      = $request->query('search');
        $type        = $request->query('receipt_type');
        $customerId  = $request->query('customer_id');
        $fromDate    = $request->query('from_date');
        $toDate      = $request->query('to_date');

        $receipts = Receipt::query()
            ->with([
                'customer:id,name,phone',
                'sale:id,invoice_number',
                'salePayment:id,amount_afn',
                'creator:id,name',
            ])
            ->when($search, function ($q, $s) {
                $q->where(function ($q) use ($s) {
                    $q->where('receipt_number', 'like', "%{$s}%")
                      ->orWhereHas('customer', function ($q) use ($s) {
                          $q->where('name', 'like', "%{$s}%")
                            ->orWhere('phone', 'like', "%{$s}%");
                      });
                });
            })
            ->when($type, fn ($q, $v) => $q->where('receipt_type', $v))
            ->when($customerId, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($fromDate, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($toDate, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total_receipts'    => Receipt::count(),
            'total_invoices'    => Receipt::saleInvoices()->count(),
            'total_payments'    => Receipt::paymentReceipts()->count(),
            'today_receipts'    => Receipt::whereDate('created_at', today())->count(),
        ];

        return Inertia::render('receipts/index', [
            'receipts'  => $receipts,
            'customers' => Customer::notTrashed()
                ->select('id', 'name', 'phone')
                ->orderBy('name')
                ->get(),
            'filters' => [
                'search'       => $search,
                'receipt_type' => $type,
                'customer_id'  => $customerId,
                'from_date'    => $fromDate,
                'to_date'      => $toDate,
            ],
            'stats' => $stats,
        ]);
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(Receipt $receipt): Response
    {
        $receipt->load([
            'customer:id,name,phone,address,current_due_afn,credit_limit_afn',
            'sale:id,invoice_number,sale_date,total_afn,paid_afn,remaining_due_afn,payment_status',
            'sale.items',
            'salePayment:id,sale_id,amount_afn,remaining_due_afn,payment_method,paid_at',
            'creator:id,name,email',
        ]);

        return Inertia::render('receipts/show', [
            'receipt' => $receipt,
        ]);
    }

    /* =========================================================
     |  DESTROY
     ========================================================= */

    public function destroy(Receipt $receipt): RedirectResponse
    {
        $number = $receipt->receipt_number;
        $receipt->delete();

        return redirect()
            ->route('receipts.index')
            ->with('success', "Receipt \"{$number}\" was deleted.");
    }

    /* =========================================================
     |  PRINT
     ========================================================= */

    public function print(Receipt $receipt): Response
    {
        $receipt->load([
            'customer:id,name,phone,current_due_afn',
            'sale:id,invoice_number,sale_date,total_afn,paid_afn,remaining_due_afn,payment_status,previous_due_afn,grand_total_due_afn',
            'sale.branch:id,name,code,address,phone',
            'sale.creator:id,name',
            'sale.items.variant.product:id,name',
            'sale.items.variant.product.brand:id,name',
            'salePayment:id,amount_afn,remaining_due_afn,payment_method,paid_at',
            'creator:id,name',
        ]);

        return Inertia::render('receipts/print', [
            'receipt' => $receipt,
            'company' => \App\Models\AppSetting::bill(),
            'printedAt' => now()->format('Y-m-d H:i:s'),
        ]);
    }
}