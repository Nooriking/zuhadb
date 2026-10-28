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
    public function index(Request $request): Response
    {
        $money = fn (float $amount): float => app(CurrencyService::class)->reportAmount($amount);

        $sales = Sale::notTrashed()
            ->where('status', 'completed')
            ->whereMonth('sale_date', now()->month)
            ->whereYear('sale_date', now()->year);

        $monthSales = (float) (clone $sales)->sum('total_afn');
        $monthCollected = (float) SalePayment::query()
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount_afn');
        $monthExpenses = (float) Expense::notTrashed()
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount_afn');

        $recentExpenses = Expense::notTrashed()
            ->latest('id')
            ->limit(8)
            ->get(['id', 'title', 'amount_afn', 'expense_date'])
            ->map(fn (Expense $expense) => [
                'id' => $expense->id,
                'title' => $expense->title,
                'amount_afn' => $money((float) $expense->amount_afn),
                'expense_date' => $expense->expense_date,
            ]);

        $reporting = app(CurrencyService::class)->reportingContext();

        return Inertia::render('dashboards/accountant', [
            'kpis' => [
                'month_sales' => $money($monthSales),
                'month_collected' => $money($monthCollected),
                'month_expenses' => $money($monthExpenses),
                'customer_due' => $money((float) Customer::notTrashed()->sum('current_due_afn')),
                'pending_investor_profit' => $money((float) InvestorProfitEntry::where('status', 'pending')->sum('profit_amount_afn')),
            ],
            'recentExpenses' => $recentExpenses,
            'reporting' => $reporting,
        ]);
    }
}
