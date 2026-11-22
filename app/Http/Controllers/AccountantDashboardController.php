<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\InvestorTransaction;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\CurrencyService;
use App\Services\StoreAssetsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountantDashboardController extends Controller
{
    public function __construct(private readonly CurrencyService $currencies) {}

    public function index(Request $request): Response
    {
        $reporting = $this->currencies->reportingContext();
        $fxBoard = $this->currencies->dashboardFxBoard();
        $money = fn (float|string|null $amount): float => $this->currencies->reportAmount((float) ($amount ?? 0));
        $ledgerCode = $reporting['ledger']['code'] ?? (string) config('business.default_currency', 'AFN');

        $sales = Sale::notTrashed()
            ->where('status', 'completed')
            ->whereBetween('sale_date', [now()->startOfMonth(), now()->endOfMonth()]);

        $monthSalesLedger = (float) (clone $sales)->sum('total_afn');
        $monthStoreProfitLedger = (float) (clone $sales)->sum('store_profit_afn');
        $monthInvestorProfitLedger = (float) (clone $sales)->sum('total_investor_profit_afn');
        $monthGrossProfitLedger = $monthStoreProfitLedger + $monthInvestorProfitLedger;
        $monthCollectedLedger = (float) SalePayment::query()
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount_afn');
        $monthExpensesLedger = (float) Expense::notTrashed()
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount_afn');
        $customerDueLedger = (float) Customer::notTrashed()->sum('current_due_afn');

        // Profit is posted to investor_transactions at sale time (wallet credited immediately).
        $paidProfitLedger = (float) InvestorTransaction::query()
            ->where('type', 'profit_earned')
            ->sum('amount_afn');
        $pendingProfitLedger = 0.0;
        $monthNetLedger = $monthStoreProfitLedger - $monthExpensesLedger;

        $inventory = StoreAssetsService::summarize(null);
        $inventoryValueLedger = (float) ($inventory['summary']['remaining_cost_afn'] ?? 0);

        $kpis3c = $this->currencies->mapThreeCurrency([
            'month_sales' => $monthSalesLedger,
            'month_collected' => $monthCollectedLedger,
            'month_expenses' => $monthExpensesLedger,
            'month_gross_profit' => $monthGrossProfitLedger,
            'month_store_profit' => $monthStoreProfitLedger,
            'month_investor_profit' => $monthInvestorProfitLedger,
            'month_net' => $monthNetLedger,
            'customer_due' => $customerDueLedger,
            'pending_investor_profit' => $pendingProfitLedger,
            'paid_investor_profit' => $paidProfitLedger,
            'inventory_value' => $inventoryValueLedger,
        ]);

        $recentExpenseModels = Expense::notTrashed()
            ->latest('id')
            ->limit(8)
            ->get(['id', 'title', 'amount_afn', 'expense_date']);

        $expense3c = $this->currencies->mapThreeCurrency(
            $recentExpenseModels->mapWithKeys(fn (Expense $e) => ["e{$e->id}" => (float) $e->amount_afn])->all()
        );

        $recentExpenses = $recentExpenseModels->map(function (Expense $expense) use ($money, $ledgerCode, $expense3c) {
            $ledgerAmount = (float) $expense->amount_afn;

            return [
                'id' => $expense->id,
                'title' => $expense->title,
                'amount_afn' => $money($ledgerAmount),
                'amount_ledger' => round($ledgerAmount, 2),
                'amount_3c' => $expense3c["e{$expense->id}"] ?? null,
                'ledger_currency_code' => $ledgerCode,
                'expense_date' => $expense->expense_date,
            ];
        });

        return Inertia::render('dashboards/accountant', [
            'kpis' => [
                'month_sales' => $money($monthSalesLedger),
                'month_collected' => $money($monthCollectedLedger),
                'month_expenses' => $money($monthExpensesLedger),
                'month_gross_profit' => $money($monthGrossProfitLedger),
                'month_store_profit' => $money($monthStoreProfitLedger),
                'month_investor_profit' => $money($monthInvestorProfitLedger),
                'month_net' => $money($monthNetLedger),
                'customer_due' => $money($customerDueLedger),
                'pending_investor_profit' => $money($pendingProfitLedger),
                'paid_investor_profit' => $money($paidProfitLedger),
                'inventory_value' => $money($inventoryValueLedger),
            ],
            'kpis3c' => $kpis3c,
            'recentExpenses' => $recentExpenses,
            'reporting' => $reporting,
            'fxBoard' => $fxBoard,
        ]);
    }
}
