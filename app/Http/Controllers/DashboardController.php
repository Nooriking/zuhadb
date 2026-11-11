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
use App\Services\CurrencyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly CurrencyService $currencies) {}

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

        $reporting = $this->currencies->reportingContext();
        $money = fn (float|string|null $amount): float => $this->currencies->reportAmount((float) ($amount ?? 0));

        /* =========================================================
         |  TOP-LEVEL KPIs
         |  Sum ledger (*_afn) amounts that were fixed at write time,
         |  then convert once for reporting display.
         ========================================================= */

        $completedSales = Sale::notTrashed()
            ->where('status', 'completed')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $todayRow = (clone $completedSales)
            ->whereBetween('sale_date', [today()->startOfDay(), today()->endOfDay()])
            ->selectRaw('COALESCE(SUM(total_afn), 0) as sales, COALESCE(SUM(store_profit_afn), 0) as profit, COUNT(*) as orders')
            ->first();
        $todaySalesLedger = (float) $todayRow->sales;
        $todayProfitLedger = (float) $todayRow->profit;
        $todayOrders = (int) $todayRow->orders;
        $todayCollectedLedger = (float) SalePayment::query()
            ->whereBetween('paid_at', [today()->startOfDay(), today()->endOfDay()])
            ->whereHas('sale', function ($q) use ($branchId) {
                $q->notTrashed()->where('status', 'completed')
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
            })
            ->sum('amount_afn');

        $monthSalesLedger = (float) (clone $completedSales)
            ->whereBetween('sale_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total_afn');

        $monthProfitLedger = (float) (clone $completedSales)
            ->whereBetween('sale_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('store_profit_afn');

        $monthExpensesLedger = $branchId
            ? 0.0
            : (float) Expense::notTrashed()
                ->whereBetween('expense_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->sum('amount_afn');

        $previousMonth = now()->subMonth();
        $prevMonthSalesLedger = (float) (clone $completedSales)
            ->whereBetween('sale_date', [$previousMonth->copy()->startOfMonth(), $previousMonth->copy()->endOfMonth()])
            ->sum('total_afn');

        $salesGrowth = $prevMonthSalesLedger > 0
            ? round((($monthSalesLedger - $prevMonthSalesLedger) / $prevMonthSalesLedger) * 100, 1)
            : null;

        $outstandingDueLedger = (float) Customer::notTrashed()->sum('current_due_afn');

        $kpis = [
            'today_sales' => $money($todaySalesLedger),
            'today_collected' => $money($todayCollectedLedger),
            'today_profit' => $money($todayProfitLedger),
            'today_orders' => $todayOrders,
            'month_sales' => $money($monthSalesLedger),
            'month_profit' => $money($monthProfitLedger),
            'month_expenses' => $money($monthExpensesLedger),
            'month_net' => $money($monthProfitLedger - $monthExpensesLedger),
            'sales_growth' => $salesGrowth,
            'outstanding_due' => $money($outstandingDueLedger),
        ];

        /* =========================================================
         |  CHARTS DATA
         ========================================================= */

        $startDate = now()->subDays($days - 1)->startOfDay();

        $trendRows = (clone $completedSales)
            ->select(
                DB::raw('DATE(sale_date) as date'),
                DB::raw('SUM(total_afn) as sales'),
                DB::raw('SUM(store_profit_afn) as profit'),
                DB::raw('COUNT(*) as count')
            )
            ->where('sale_date', '>=', $startDate)
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
                'date' => $cursor->format('M d'),
                'sales' => $row ? $money((float) $row->sales) : 0.0,
                'profit' => $row ? $money((float) $row->profit) : 0.0,
                'count' => $row ? (int) $row->count : 0,
            ];
            $cursor->addDay();
        }

        $brandSales = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_variants', 'sale_items.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->where('sales.sale_date', '>=', $startDate)
            ->select(
                DB::raw("COALESCE(brands.name, 'No Brand') as brand"),
                DB::raw('SUM(sale_items.line_total_afn) as total')
            )
            ->groupBy('brands.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->brand,
                'value' => $money((float) $row->total),
            ]);

        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_variants', 'sale_items.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->where('sales.sale_date', '>=', $startDate)
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
            ->map(function ($row) use ($money) {
                $displayName = trim(implode(' · ', array_filter([
                    $row->product_name,
                    $row->volume_ml ? "{$row->volume_ml}ml" : null,
                    $row->concentration ? strtoupper($row->concentration) : null,
                ])));

                return [
                    'id' => $row->variant_id,
                    'name' => $displayName,
                    'brand' => $row->brand_name,
                    'total_qty' => (int) $row->total_qty,
                    'total_sales' => $money((float) $row->total_sales),
                    'total_profit' => $money((float) $row->total_profit),
                    'total_sales_ledger' => round((float) $row->total_sales, 2),
                    'total_profit_ledger' => round((float) $row->total_profit, 2),
                ];
            });

        $branchStats = DB::table('sales')
            ->join('branches', 'sales.branch_id', '=', 'branches.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->where('sales.sale_date', '>=', $startDate)
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
                'id' => $row->id,
                'name' => $row->name,
                'code' => $row->code,
                'sales' => $money((float) $row->total_sales),
                'profit' => $money((float) $row->total_profit),
                'orders' => (int) $row->orders,
            ]);

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
                'name' => ucfirst((string) $row->payment_method),
                'value' => $money((float) $row->total),
            ]);

        $periodDueLedger = (float) (clone $completedSales)
            ->whereDate('sale_date', '>=', $startDate->toDateString())
            ->sum('remaining_due_afn');

        if ($periodDueLedger > 0) {
            $paymentMethods->push([
                'name' => 'Due',
                'value' => $money($periodDueLedger),
            ]);
        }

        $paymentCurrencyTotals = $this->paymentCurrencyTotals($branchId, $startDate->toDateString());

        /* =========================================================
         |  COUNTERS
         ========================================================= */

        $counters = [
            'products' => Product::notTrashed()->whereNull('variant_id')->count(),
            'variants' => ProductVariant::notTrashed()->count(),
            'customers' => Customer::notTrashed()->count(),
            'suppliers' => Supplier::notTrashed()->count(),
            'investors' => Investor::notTrashed()->count(),
            'users' => User::notTrashed()->count(),
            'total_stock' => (int) Inventory::notTrashed()->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->sum('quantity'),
            'low_stock' => $this->lowStockQuery($branchId)->count(),
            'out_of_stock' => Inventory::notTrashed()->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->where('quantity', '<=', 0)->count(),
        ];

        /* =========================================================
         |  RECENT ACTIVITY
         ========================================================= */

        $ledgerCode = $reporting['ledger']['code'] ?? (string) config('business.default_currency', 'AFN');

        $recentSales = Sale::query()
            ->with(['branch:id,name,code', 'customer:id,name'])
            ->notTrashed()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function ($s) use ($money, $ledgerCode) {
                $ledgerTotal = (float) $s->total_afn;

                return [
                    'id' => $s->id,
                    'invoice_number' => $s->invoice_number,
                    'total_afn' => $money($ledgerTotal),
                    'total_ledger' => round($ledgerTotal, 2),
                    'ledger_currency_code' => $s->ledger_currency_code ?: $ledgerCode,
                    'payment_status' => $s->payment_status,
                    'status' => $s->status,
                    'sale_date' => optional($s->sale_date)->toDateString(),
                    'branch' => $s->branch?->name,
                    'customer' => $s->customer_name_snapshot ?: ($s->customer?->name ?? 'Walk-in'),
                ];
            });

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
                    'id' => $inv->id,
                    'branch' => $inv->branch?->name,
                    'product' => $variantName !== '' ? $variantName : 'Unknown product',
                    'brand' => $inv->variant?->product?->brand?->name,
                    'volume_ml' => $inv->variant?->volume_ml,
                    'quantity' => (int) $inv->quantity,
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

        $dueCustomers = $dueCustomers->map(function (Customer $customer) use ($dueDates, $money) {
            $dueDate = $dueDates->get($customer->id);
            $ledgerDue = (float) $customer->current_due_afn;

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'current_due_afn' => $money($ledgerDue),
                'current_due_ledger' => round($ledgerDue, 2),
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
            'dueCustomers' => $dueCustomers,
            'days' => $days,
            'kpis' => $kpis,
            'salesTrend' => $salesTrend,
            'brandSales' => $brandSales,
            'topProducts' => $topProducts,
            'branchStats' => $branchStats,
            'paymentMethods' => $paymentMethods,
            'paymentCurrencyTotals' => $paymentCurrencyTotals,
            'counters' => $counters,
            'recentSales' => $recentSales,
            'recentActivity' => $recentActivity,
            'lowStockItems' => $lowStockItems,
            'reporting' => $reporting,
        ]);
    }

    /**
     * Original tender totals for payments in the selected period.
     * Uses amount_currency + currency_code snapshots from each payment —
     * never recalculates with today's catalog rate.
     *
     * @return list<array{code: string, symbol: string|null, total: float}>
     */
    private function paymentCurrencyTotals(?int $branchId, string $fromDate): array
    {
        if (! Schema::hasColumn('sale_payments', 'currency_code')
            || ! Schema::hasColumn('sale_payments', 'amount_currency')) {
            return [];
        }

        $ledgerFallback = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) config('business.default_currency', 'AFN'))) ?: 'AFN';

        $rows = DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->leftJoin('currencies', 'currencies.id', '=', 'sale_payments.currency_id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->where('sales.sale_date', '>=', $fromDate)
            ->select(
                'sale_payments.currency_code',
                'sale_payments.ledger_currency_code',
                DB::raw('MAX(currencies.symbol) as currency_symbol'),
                DB::raw('SUM(COALESCE(sale_payments.amount_currency, sale_payments.amount_afn)) as total')
            )
            ->groupBy('sale_payments.currency_code', 'sale_payments.ledger_currency_code')
            ->get();

        $groups = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row->currency_code ?: $row->ledger_currency_code ?: $ledgerFallback));
            if ($code === '') {
                $code = $ledgerFallback;
            }

            if (! isset($groups[$code])) {
                $groups[$code] = [
                    'code' => $code,
                    'symbol' => $row->currency_symbol,
                    'total' => 0.0,
                ];
            }

            $amount = (float) $row->total;
            $groups[$code]['total'] += $amount;

            if ($groups[$code]['symbol'] === null && $row->currency_symbol !== null) {
                $groups[$code]['symbol'] = $row->currency_symbol;
            }
        }

        ksort($groups);

        return array_values(array_map(fn (array $group) => [
            'code' => $group['code'],
            'symbol' => $group['symbol'],
            'total' => round($group['total'], 2),
        ], $groups));
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
