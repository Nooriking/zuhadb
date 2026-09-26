<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\InvestorProfitEntry;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountantDashboardController extends Controller
{
    public function index(Request $request): Response
    {
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
            ->get(['id', 'title', 'amount_afn', 'expense_date']);

        return Inertia::render('dashboards/accountant', [
            'kpis' => [
                'month_sales' => round($monthSales, 2),
                'month_collected' => round($monthCollected, 2),
                'month_expenses' => round($monthExpenses, 2),
                'customer_due' => round((float) Customer::notTrashed()->sum('current_due_afn'), 2),
                'pending_investor_profit' => round((float) InvestorProfitEntry::where('status', 'pending')->sum('profit_amount_afn'), 2),
            ],
            'recentExpenses' => $recentExpenses,
        ]);
    }
}
