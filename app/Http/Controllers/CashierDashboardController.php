<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CashierDashboardController extends Controller
{
    public function __construct(private readonly CurrencyService $currencies) {}

    public function index(Request $request): Response
    {
        $branchId = $request->user()?->branch_id;
        $reporting = $this->currencies->reportingContext();
        $fxBoard = $this->currencies->dashboardFxBoard();
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

        $cashCollectedLedger = (float) SalePayment::query()
            ->whereBetween('paid_at', [today()->startOfDay(), today()->endOfDay()])
            ->where('payment_method', 'cash')
            ->whereHas('sale', function ($q) use ($branchId) {
                $q->notTrashed()->where('status', 'completed')
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
            })
            ->sum('amount_afn');

        $cardCollectedLedger = (float) SalePayment::query()
            ->whereBetween('paid_at', [today()->startOfDay(), today()->endOfDay()])
            ->whereIn('payment_method', ['card', 'visa', 'mastercard', 'pos'])
            ->whereHas('sale', function ($q) use ($branchId) {
                $q->notTrashed()->where('status', 'completed')
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
            })
            ->sum('amount_afn');

        $customerDueLedger = (float) Customer::notTrashed()
            ->when($branchId, fn ($q) => $q->whereHas('sales', fn ($sales) => $sales->where('branch_id', $branchId)))
            ->sum('current_due_afn');

        $kpis3c = $this->currencies->mapThreeCurrency([
            'today_sales' => $todaySalesLedger,
            'today_collected' => $todayCollectedLedger,
            'cash_collected' => $cashCollectedLedger,
            'card_collected' => $cardCollectedLedger,
            'customer_due' => $customerDueLedger,
        ]);

        $paymentBuckets = DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->whereBetween('sale_payments.paid_at', [today()->startOfDay(), today()->endOfDay()])
            ->select(
                'sale_payments.currency_code',
                'sale_payments.ledger_currency_code',
                DB::raw('SUM(COALESCE(sale_payments.amount_currency, sale_payments.amount_afn)) as total')
            )
            ->groupBy('sale_payments.currency_code', 'sale_payments.ledger_currency_code')
            ->get()
            ->map(function ($row) use ($ledgerCode) {
                $code = trim((string) ($row->currency_code ?: $row->ledger_currency_code ?: $ledgerCode)) ?: $ledgerCode;

                return [
                    'code' => $code,
                    'total' => round((float) $row->total, 2),
                ];
            })
            ->values()
            ->all();

        $recentModels = (clone $sales)
            ->with('customer:id,name')
            ->latest('id')
            ->limit(8)
            ->get();

        $recent3c = $this->currencies->mapThreeCurrency(
            $recentModels->flatMap(fn (Sale $sale) => [
                "t{$sale->id}" => (float) $sale->total_afn,
                "d{$sale->id}" => (float) $sale->remaining_due_afn,
            ])->all()
        );

        $recent = $recentModels->map(function (Sale $sale) use ($money, $ledgerCode, $recent3c) {
            $ledgerTotal = (float) $sale->total_afn;
            $ledgerDue = (float) $sale->remaining_due_afn;

            return [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'customer' => $sale->customer_name_snapshot ?: ($sale->customer?->name ?? 'Walk-in'),
                'total_afn' => $money($ledgerTotal),
                'total_ledger' => round($ledgerTotal, 2),
                'total_3c' => $recent3c["t{$sale->id}"] ?? null,
                'remaining_due_afn' => $money($ledgerDue),
                'remaining_due_ledger' => round($ledgerDue, 2),
                'remaining_due_3c' => $recent3c["d{$sale->id}"] ?? null,
                'original_amount' => $sale->sale_amount_currency !== null ? (float) $sale->sale_amount_currency : round($ledgerTotal, 2),
                'original_currency' => $sale->sale_currency_code ?: ($sale->ledger_currency_code ?: $ledgerCode),
                'ledger_currency_code' => $sale->ledger_currency_code ?: $ledgerCode,
                'sale_date' => optional($sale->sale_date)->toDateString(),
            ];
        });

        return Inertia::render('dashboards/cashier', [
            'kpis' => [
                'today_sales' => $money($todaySalesLedger),
                'today_orders' => $todayOrders,
                'today_collected' => $money($todayCollectedLedger),
                'cash_collected' => $money($cashCollectedLedger),
                'card_collected' => $money($cardCollectedLedger),
                'customer_due' => $money($customerDueLedger),
            ],
            'kpis3c' => $kpis3c,
            'paymentCurrencyTotals' => $paymentBuckets,
            'recentSales' => $recent,
            'reporting' => $reporting,
            'fxBoard' => $fxBoard,
        ]);
    }
}
