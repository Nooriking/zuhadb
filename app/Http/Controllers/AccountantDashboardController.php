<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\InvestorProfitEntry;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountantDashboardController extends Controller
{
    public function __construct(private readonly CurrencyService $currencies) {}

    public function index(Request $request): Response
    {
        $reporting = $this->currencies->reportingContext();
        $money = fn (float|string|null $amount): float => $this->currencies->reportAmount((float) ($amount ?? 0));
        $ledgerCode = $reporting['ledger']['code'] ?? (string) config('business.default_currency', 'AFN');

        $sales = Sale::notTrashed()
            ->where('status', 'completed')
            ->whereBetween('sale_date', [now()->startOfMonth(), now()->endOfMonth()]);

        $monthSalesLedger = (float) (clone $sales)->sum('total_afn');
        $monthCollectedLedger = (float) SalePayment::query()
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount_afn');
        $monthExpensesLedger = (float) Expense::notTrashed()
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount_afn');
        $customerDueLedger = (float) Customer::notTrashed()->sum('current_due_afn');
        $pendingProfitLedger = (float) InvestorProfitEntry::where('status', 'pending')->sum('profit_amount_afn');

        $recentExpenses = Expense::notTrashed()
            ->latest('id')
            ->limit(8)
            ->get(['id', 'title', 'amount_afn', 'expense_date'])
            ->map(function (Expense $expense) use ($money, $ledgerCode) {
                $ledgerAmount = (float) $expense->amount_afn;

                return [
                    'id' => $expense->id,
                    'title' => $expense->title,
                    'amount_afn' => $money($ledgerAmount),
                    'amount_ledger' => round($ledgerAmount, 2),
                    'ledger_currency_code' => $ledgerCode,
                    'expense_date' => $expense->expense_date,
                ];
            });

        return Inertia::render('dashboards/accountant', [
            'kpis' => [
                'month_sales' => $money($monthSalesLedger),
                'month_collected' => $money($monthCollectedLedger),
                'month_expenses' => $money($monthExpensesLedger),
                'customer_due' => $money($customerDueLedger),
                'pending_investor_profit' => $money($pendingProfitLedger),
            ],
            'recentExpenses' => $recentExpenses,
            'reporting' => $reporting,
        ]);
    }
}
