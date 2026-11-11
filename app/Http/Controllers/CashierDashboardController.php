<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashierDashboardController extends Controller
{
    public function __construct(private readonly CurrencyService $currencies) {}

    public function index(Request $request): Response
    {
        $branchId = $request->user()?->branch_id;
        $reporting = $this->currencies->reportingContext();
        $money = fn (float|string|null $amount): float => $this->currencies->reportAmount((float) ($amount ?? 0));
        $ledgerCode = $reporting['ledger']['code'] ?? (string) config('business.default_currency', 'AFN');

        $sales = Sale::notTrashed()
            ->where('status', 'completed')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $today = (clone $sales)
            ->whereBetween('sale_date', [today()->startOfDay(), today()->endOfDay()])
            ->selectRaw('COALESCE(SUM(total_afn), 0) as sales, COUNT(*) as orders')
            ->first();
        $todaySalesLedger = (float) $today->sales;
        $todayOrders = (int) $today->orders;
        $todayCollectedLedger = (float) SalePayment::query()
            ->whereBetween('paid_at', [today()->startOfDay(), today()->endOfDay()])
            ->whereHas('sale', function ($q) use ($branchId) {
                $q->notTrashed()->where('status', 'completed')
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
            })
            ->sum('amount_afn');

        $customerDueLedger = (float) Customer::notTrashed()
            ->when($branchId, fn ($q) => $q->whereHas('sales', fn ($sales) => $sales->where('branch_id', $branchId)))
            ->sum('current_due_afn');

        $recent = (clone $sales)
            ->with('customer:id,name')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(function (Sale $sale) use ($money, $ledgerCode) {
                $ledgerTotal = (float) $sale->total_afn;
                $ledgerDue = (float) $sale->remaining_due_afn;

                return [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'customer' => $sale->customer_name_snapshot ?: ($sale->customer?->name ?? 'Walk-in'),
                    'total_afn' => $money($ledgerTotal),
                    'total_ledger' => round($ledgerTotal, 2),
                    'remaining_due_afn' => $money($ledgerDue),
                    'remaining_due_ledger' => round($ledgerDue, 2),
                    'ledger_currency_code' => $sale->ledger_currency_code ?: $ledgerCode,
                    'sale_date' => optional($sale->sale_date)->toDateString(),
                ];
            });

        return Inertia::render('dashboards/cashier', [
            'kpis' => [
                'today_sales' => $money($todaySalesLedger),
                'today_orders' => $todayOrders,
                'today_collected' => $money($todayCollectedLedger),
                'customer_due' => $money($customerDueLedger),
            ],
            'recentSales' => $recent,
            'reporting' => $reporting,
        ]);
    }
}
