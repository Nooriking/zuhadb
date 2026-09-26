<?php

namespace App\Http\Controllers;

use App\Models\ActivityNotification;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Investor;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return match ($user?->role) {
            User::ROLE_INVESTOR => app(InvestorDashboardController::class)->index($request),
            User::ROLE_CASHIER, User::ROLE_SALESPERSON => app(CashierDashboardController::class)->index($request),
            User::ROLE_STOCK_KEEPER => app(StockDashboardController::class)->index($request),
            User::ROLE_ACCOUNTANT => app(AccountantDashboardController::class)->index($request),
            User::ROLE_BRANCH_MANAGER => app(BranchDashboardController::class)->index($request),
            default => $this->render($request, null),
        };
    }

    public function render(Request $request, ?int $branchId = null): Response
    {
        $days = (int) $request->query('days', 30);
        if ($days < 1 || $days > 365) {
            $days = 30;
        }

        /* =========================================================
         |  TOP-LEVEL KPIs
         ========================================================= */

        $completedSales = Sale::notTrashed()
            ->where('status', 'completed')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $todayRow = (clone $completedSales)
            ->whereDate('sale_date', today())
            ->selectRaw('COALESCE(SUM(total_afn), 0) as sales, COALESCE(SUM(store_profit_afn), 0) as profit, COUNT(*) as orders')
            ->first();
        $todaySales  = (float) $todayRow->sales;
        $todayProfit = (float) $todayRow->profit;
        $todayOrders = (int) $todayRow->orders;
        $todayCollected = (float) SalePayment::query()
            ->whereDate('paid_at', today())
            ->whereHas('sale', function ($q) use ($branchId) {
                $q->notTrashed()->where('status', 'completed')
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
            })
            ->sum('amount_afn');

        $monthSales = (float) (clone $completedSales)
            ->whereMonth('sale_date', now()->month)
            ->whereYear('sale_date', now()->year)
            ->sum('total_afn');

        $monthProfit = (float) (clone $completedSales)
            ->whereMonth('sale_date', now()->month)
            ->whereYear('sale_date', now()->year)
            ->sum('store_profit_afn');

        $monthExpenses = $branchId
            ? 0.0
            : (float) Expense::notTrashed()
                ->whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->sum('amount_afn');

        $previousMonth = now()->subMonth();
        $prevMonthSales = (float) (clone $completedSales)
            ->whereMonth('sale_date', $previousMonth->month)
            ->whereYear('sale_date', $previousMonth->year)
            ->sum('total_afn');

        $salesGrowth = $prevMonthSales > 0
            ? round((($monthSales - $prevMonthSales) / $prevMonthSales) * 100, 1)
            : null;

        $outstandingDue = (float) Customer::notTrashed()->sum('current_due_afn');

        $kpis = [
            'today_sales'      => round($todaySales, 2),
            'today_collected'  => round($todayCollected, 2),
            'today_profit'     => round($todayProfit, 2),
            'today_orders'     => $todayOrders,
            'month_sales'      => round($monthSales, 2),
            'month_profit'     => round($monthProfit, 2),
            'month_expenses'   => round($monthExpenses, 2),
            'month_net'        => round($monthProfit - $monthExpenses, 2),
            'sales_growth'     => $salesGrowth,
            'outstanding_due'  => round($outstandingDue, 2),
        ];

        /* =========================================================
         |  CHARTS DATA
         ========================================================= */

        $startDate = now()->subDays($days - 1)->startOfDay();

        // 1. Sales trend — one point per day, including days with no sales
        $trendRows = (clone $completedSales)
            ->select(
                DB::raw('DATE(sale_date) as date'),
                DB::raw('SUM(total_afn) as sales'),
                DB::raw('SUM(store_profit_afn) as profit'),
                DB::raw('COUNT(*) as count')
            )
            ->whereDate('sale_date', '>=', $startDate->toDateString())
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $salesTrend = [];
        $cursor = $startDate->copy();
        $end = now()->startOfDay();

        while ($cursor->lte($end)) {
            $row = $trendRows->get($cursor->toDateString());
            $salesTrend[] = [
                'date'   => $cursor->format('M d'),
                'sales'  => $row ? round((float) $row->sales, 2) : 0,
                'profit' => $row ? round((float) $row->profit, 2) : 0,
                'count'  => $row ? (int) $row->count : 0,
            ];
            $cursor->addDay();
        }

        // 2. Brand distribution
        $brandSales = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_variants', 'sale_items.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->whereDate('sales.sale_date', '>=', $startDate->toDateString())
            ->select(
                DB::raw("COALESCE(brands.name, 'No Brand') as brand"),
                DB::raw('SUM(sale_items.line_total_afn) as total')
            )
            ->groupBy('brands.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'name'  => $row->brand,
                'value' => (float) $row->total,
            ]);

        // 3. Top selling products
        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_variants', 'sale_items.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->whereDate('sales.sale_date', '>=', $startDate->toDateString())
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'product_variants.id as variant_id',
                'product_variants.volume_ml',
                'product_variants.concentration',
                'brands.name as brand_name',
                DB::raw('SUM(sale_items.quantity) as total_qty'),
                DB::raw('SUM(sale_items.line_total_afn) as total_sales'),
                DB::raw('SUM(sale_items.store_profit_afn) as total_profit')
            )
            ->groupBy(
                'products.id',
                'products.name',
                'product_variants.id',
                'product_variants.volume_ml',
                'product_variants.concentration',
                'brands.name'
            )
            ->orderByDesc('total_sales')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $displayName = trim(implode(' · ', array_filter([
                    $row->product_name,
                    $row->volume_ml ? "{$row->volume_ml}ml" : null,
                    $row->concentration ? strtoupper($row->concentration) : null,
                ])));

                return [
                    'id'          => $row->variant_id,
                    'name'        => $displayName,
                    'brand'       => $row->brand_name,
                    'total_qty'    => (int) $row->total_qty,
                    'total_sales'  => round((float) $row->total_sales, 2),
                    'total_profit' => round((float) $row->total_profit, 2),
                ];
            });

        // 4. Branch comparison
        $branchStats = DB::table('sales')
            ->join('branches', 'sales.branch_id', '=', 'branches.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->whereDate('sales.sale_date', '>=', $startDate->toDateString())
            ->select(
                'branches.id',
                'branches.name',
                'branches.code',
                DB::raw('SUM(sales.total_afn) as total_sales'),
                DB::raw('SUM(sales.store_profit_afn) as total_profit'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('branches.id', 'branches.name', 'branches.code')
            ->orderByDesc('total_sales')
            ->get()
            ->map(fn ($row) => [
                'id'     => $row->id,
                'name'   => $row->name,
                'code'   => $row->code,
                'sales'  => (float) $row->total_sales,
                'profit' => (float) $row->total_profit,
                'orders' => (int) $row->orders,
            ]);

        // 5. Payment method distribution
        $paymentMethods = DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->whereDate('sales.sale_date', '>=', $startDate->toDateString())
            ->select(
                'sale_payments.payment_method',
                DB::raw('SUM(sale_payments.amount_afn) as total')
            )
            ->groupBy('sale_payments.payment_method')
            ->get()
            ->map(fn ($row) => [
                'name'  => ucfirst((string) $row->payment_method),
                'value' => round((float) $row->total, 2),
            ]);

        $periodDue = (float) (clone $completedSales)
            ->whereDate('sale_date', '>=', $startDate->toDateString())
            ->sum('remaining_due_afn');

        if ($periodDue > 0) {
            $paymentMethods->push([
                'name'  => 'Due',
                'value' => round($periodDue, 2),
            ]);
        }

        /* =========================================================
         |  COUNTERS
         ========================================================= */

        $counters = [
            'products'     => Product::notTrashed()->whereNull('variant_id')->count(),
            'variants'     => ProductVariant::notTrashed()->count(),
            'customers'    => Customer::notTrashed()->count(),
            'suppliers'    => Supplier::notTrashed()->count(),
            'investors'    => Investor::notTrashed()->count(),
            'users'        => User::notTrashed()->count(),
            'total_stock'  => (int) Inventory::notTrashed()->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->sum('quantity'),

            'low_stock'    => $this->lowStockQuery($branchId)->count(),

            'out_of_stock' => Inventory::notTrashed()->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->where('quantity', '<=', 0)->count(),
        ];

        /* =========================================================
         |  RECENT ACTIVITY
         ========================================================= */

        $recentSales = Sale::query()
            ->with(['branch:id,name,code', 'customer:id,name'])
            ->notTrashed()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn ($s) => [
                'id'             => $s->id,
                'invoice_number' => $s->invoice_number,
                'total_afn'      => (float) $s->total_afn,
                'payment_status' => $s->payment_status,
                'status'         => $s->status,
                'sale_date'      => optional($s->sale_date)->toDateString(),
                'branch'         => $s->branch?->name,
                'customer'       => $s->customer_name_snapshot ?: ($s->customer?->name ?? 'Walk-in'),
            ]);

        /* =========================================================
         |  LOW STOCK ITEMS
         ========================================================= */

        $lowStockItems = $this->lowStockQuery($branchId)
            ->with([
                'branch:id,name,code',
                'variant:id,product_id,volume_ml,concentration,minimum_stock',
                'variant.product:id,name,brand_id',
                'variant.product.brand:id,name',
            ])
            ->orderBy('quantity')
            ->limit(6)
            ->get()
            ->map(function ($inv) {
                $variantName = trim(implode(' · ', array_filter([
                    $inv->variant?->product?->name,
                    $inv->variant?->volume_ml ? "{$inv->variant->volume_ml}ml" : null,
                    $inv->variant?->concentration ? strtoupper($inv->variant->concentration) : null,
                ])));

                return [
                    'id'        => $inv->id,
                    'branch'    => $inv->branch?->name,
                    'product'   => $variantName !== '' ? $variantName : 'Unknown product',
                    'brand'     => $inv->variant?->product?->brand?->name,
                    'volume_ml' => $inv->variant?->volume_ml,
                    'quantity'  => (int) $inv->quantity,
                    'min_stock' => (int) ($inv->variant?->minimum_stock ?? 0),
                ];
            });

        /* =========================================================
         |  RENDER
         ========================================================= */

        $dueCustomers = Customer::notTrashed()
            ->where('current_due_afn', '>', 0)
            ->orderByDesc('current_due_afn')
            ->limit(8)
            ->get(['id', 'name', 'phone', 'current_due_afn']);

        $dueDates = $dueCustomers->isEmpty()
            ? collect()
            : Sale::notTrashed()
                ->whereIn('customer_id', $dueCustomers->pluck('id'))
                ->where('remaining_due_afn', '>', 0)
                ->whereNotNull('due_date')
                ->selectRaw('customer_id, MIN(due_date) as due_date')
                ->groupBy('customer_id')
                ->pluck('due_date', 'customer_id');

        $dueCustomers = $dueCustomers->map(function (Customer $customer) use ($dueDates) {
            $dueDate = $dueDates->get($customer->id);

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'current_due_afn' => (float) $customer->current_due_afn,
                'due_date' => $dueDate ? Carbon::parse($dueDate)->toDateString() : null,
            ];
        });

        $recentActivity = ActivityNotification::query()
            ->when($request->user(), fn ($q, $actor) => $q->where('user_id', $actor->id))
            ->latest('id')
            ->limit(8)
            ->get(['id', 'section', 'title', 'body', 'url', 'created_at'])
            ->map(fn (ActivityNotification $item) => [
                'id' => $item->id,
                'section' => $item->section,
                'title' => $item->title,
                'body' => $item->body,
                'url' => $item->url ?: '/notifications',
                'created_at' => optional($item->created_at)->diffForHumans(),
            ]);

        return Inertia::render('dashboard', [
            'dueCustomers'   => $dueCustomers,
            'days'           => $days,
            'kpis'           => $kpis,
            'salesTrend'     => $salesTrend,
            'brandSales'     => $brandSales,
            'topProducts'    => $topProducts,
            'branchStats'    => $branchStats,
            'paymentMethods' => $paymentMethods,
            'counters'       => $counters,
            'recentSales'    => $recentSales,
            'recentActivity' => $recentActivity,
            'lowStockItems'  => $lowStockItems,
        ]);
    }

    /**
     * Same rule as the inventory low-stock page: quantity at or below the variant minimum.
     */
    private function lowStockQuery(?int $branchId = null)
    {
        return Inventory::notTrashed()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereHas('variant', function ($q) {
            $q->where('minimum_stock', '>', 0)
                ->whereColumn('inventories.quantity', '<=', 'product_variants.minimum_stock');
        });
    }
}