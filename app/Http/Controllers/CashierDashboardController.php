<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashierDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $branchId = $request->user()?->branch_id;

        $sales = Sale::notTrashed()
            ->where('status', 'completed')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $todaySales = (float) (clone $sales)->whereDate('sale_date', today())->sum('total_afn');
        $todayOrders = (clone $sales)->whereDate('sale_date', today())->count();
        $todayCollected = (float) SalePayment::query()
            ->whereDate('paid_at', today())
            ->whereHas('sale', function ($q) use ($branchId) {
                $q->notTrashed()->where('status', 'completed')
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
            })
            ->sum('amount_afn');

        $recent = (clone $sales)
            ->with('customer:id,name')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'customer' => $sale->customer_name_snapshot ?: ($sale->customer?->name ?? 'Walk-in'),
                'total_afn' => (float) $sale->total_afn,
                'remaining_due_afn' => (float) $sale->remaining_due_afn,
                'sale_date' => optional($sale->sale_date)->toDateString(),
            ]);

        return Inertia::render('dashboards/cashier', [
            'kpis' => [
                'today_sales' => round($todaySales, 2),
                'today_orders' => $todayOrders,
                'today_collected' => round($todayCollected, 2),
                'customer_due' => round((float) Customer::notTrashed()->sum('current_due_afn'), 2),
            ],
            'recentSales' => $recent,
        ]);
    }
}
