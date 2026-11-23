<?php

namespace App\Http\Controllers;

use App\Models\ActivityNotification;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Investor;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\StoreAssetsService;
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
        $branchId = $branchId ?? ($request->filled('branch_id') ? (int) $request->input('branch_id') : null);

        /* =========================================================
         |  PERIOD — Today | 7 Days | 30 Days | 90 Days
         |  Accepts: today/7/30/90, daily/weekly/monthly/quarterly, or ?days=
         ========================================================= */
        $rawPeriod = $request->input('period');
        if ($rawPeriod === null && $request->has('days')) {
            $d = (int) $request->query('days', 30);
            $rawPeriod = match (true) {
                $d <= 1 => 'today',
                $d <= 7 => '7',
                $d <= 30 => '30',
                default => '90',
            };
        }

        $period = match ((string) ($rawPeriod ?? '30')) {
            'daily', 'today', '1' => 'today',
            'weekly', '7' => '7',
            'quarterly', '90' => '90',
            'monthly', '30' => '30',
            default => in_array((string) $rawPeriod, ['today', '7', '30', '90'], true)
                ? (string) $rawPeriod
                : '30',
        };

        $now = now();
        [$from, $to] = match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            '7' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '90' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            default => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
        };

        $days = max(1, (int) $from->diffInDays($to) + 1);

        $reporting = $this->currencies->reportingContext();
        $fxBoard = $this->currencies->dashboardFxBoard();
        $money = fn (float|string|null $amount): float => $this->currencies->reportAmount((float) ($amount ?? 0));

        /* =========================================================
         |  TOP-LEVEL KPIs (ledger AFN)
         |  Frontend converts via shared Inertia `exchange` (useExchange).
         ========================================================= */

        $completedSales = Sale::notTrashed()
            ->where('status', 'completed')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $todayRow = (clone $completedSales)
            ->whereBetween('sale_date', [today()->startOfDay(), today()->endOfDay()])
            ->selectRaw('COALESCE(SUM(total_afn), 0) as sales, COALESCE(SUM(store_profit_afn), 0) as profit, COUNT(*) as orders')
            ->first();
        $todaySalesAfn = (float) $todayRow->sales;
        $todayProfitAfn = (float) $todayRow->profit;
        $todayOrders = (int) $todayRow->orders;

        $collectedTodayAfn = (float) SalePayment::query()
            ->whereBetween('paid_at', [today()->startOfDay(), today()->endOfDay()])
            ->whereHas('sale', function ($q) use ($branchId) {
                $q->notTrashed()->where('status', 'completed')
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
            })
            ->sum('amount_afn');

        $periodSalesAfn = (float) (clone $completedSales)
            ->whereBetween('sale_date', [$from, $to])
            ->sum('total_afn');

        $periodProfitAfn = (float) (clone $completedSales)
            ->whereBetween('sale_date', [$from, $to])
            ->sum('store_profit_afn');

        $periodExpensesAfn = $branchId
            ? 0.0
            : (float) Expense::notTrashed()
                ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
                ->sum('amount_afn');

        $prevFrom = $from->copy()->subDays($days);
        $prevTo = $from->copy()->subSecond();
        $prevPeriodSalesAfn = (float) (clone $completedSales)
            ->whereBetween('sale_date', [$prevFrom, $prevTo])
            ->sum('total_afn');

        $salesGrowth = $prevPeriodSalesAfn > 0
            ? round((($periodSalesAfn - $prevPeriodSalesAfn) / $prevPeriodSalesAfn) * 100, 1)
            : null;

        $customerDueAfn = (float) Customer::notTrashed()->sum('current_due_afn');
        $periodNetAfn = $periodProfitAfn - $periodExpensesAfn;

        // Authoritative valuation: remaining batch qty × historical unit_cost_afn (FIFO snapshots).
        $stockValueAfn = ProductBatch::valuationSummary($branchId)['total_afn'];

        // AFN ledger amounts — frontend shows AFN | USD | AED via useExchange()
        $kpis = [
            'today_sales_afn' => round($todaySalesAfn, 2),
            'period_sales_afn' => round($periodSalesAfn, 2),
            'today_orders' => $todayOrders,
            'collected_today_afn' => round($collectedTodayAfn, 2),
            'today_profit_afn' => round($todayProfitAfn, 2),
            'period_profit_afn' => round($periodProfitAfn, 2),
            'customer_due_afn' => round($customerDueAfn, 2),
            'period_expenses_afn' => round($periodExpensesAfn, 2),
            'period_net_afn' => round($periodNetAfn, 2),
            'stock_value_afn' => round($stockValueAfn, 2),
            'sales_growth' => $salesGrowth,

            // Backward-compatible aliases (reporting currency) until frontend Stage 2
            'today_sales' => $money($todaySalesAfn),
            'today_collected' => $money($collectedTodayAfn),
            'today_profit' => $money($todayProfitAfn),
            'month_sales' => $money($periodSalesAfn),
            'month_profit' => $money($periodProfitAfn),
            'month_expenses' => $money($periodExpensesAfn),
            'month_net' => $money($periodNetAfn),
            'outstanding_due' => $money($customerDueAfn),
        ];

        $kpis3c = $this->currencies->mapThreeCurrency([
            'today_sales' => $todaySalesAfn,
            'today_collected' => $collectedTodayAfn,
            'today_profit' => $todayProfitAfn,
            'month_sales' => $periodSalesAfn,
            'month_profit' => $periodProfitAfn,
            'month_expenses' => $periodExpensesAfn,
            'month_net' => $periodNetAfn,
            'outstanding_due' => $customerDueAfn,
            'period_sales' => $periodSalesAfn,
            'period_profit' => $periodProfitAfn,
            'period_expenses' => $periodExpensesAfn,
            'period_net' => $periodNetAfn,
            'stock_value' => $stockValueAfn,
        ]);

        /* =========================================================
         |  CHARTS DATA
         ========================================================= */

        $startDate = $from->copy()->startOfDay();

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

        $trendLedgerPairs = [];
        while ($cursor->lte($end)) {
            $row = $trendRows->get($cursor->toDateString());
            $salesAfn = $row ? (float) $row->sales : 0.0;
            $profitAfn = $row ? (float) $row->profit : 0.0;
            $key = $cursor->toDateString();
            $trendLedgerPairs["{$key}|sales"] = $salesAfn;
            $trendLedgerPairs["{$key}|profit"] = $profitAfn;
            $salesTrend[] = [
                'date' => $cursor->format('M d'),
                'date_key' => $key,
                'sales' => $money($salesAfn),
                'profit' => $money($profitAfn),
                'sales_afn' => round($salesAfn, 2),
                'profit_afn' => round($profitAfn, 2),
                'count' => $row ? (int) $row->count : 0,
            ];
            $cursor->addDay();
        }

        $trend3c = $this->currencies->mapThreeCurrency($trendLedgerPairs);
        $salesTrend = array_map(function (array $point) use ($trend3c) {
            $point['sales_3c'] = $trend3c[$point['date_key'].'|sales'] ?? null;
            $point['profit_3c'] = $trend3c[$point['date_key'].'|profit'] ?? null;
            unset($point['date_key']);

            return $point;
        }, $salesTrend);

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
            ->get();

        $brand3c = $this->currencies->mapThreeCurrency(
            $brandSales->mapWithKeys(fn ($row, $i) => ["b{$i}" => (float) $row->total])->all()
        );
        $brandSales = $brandSales->values()->map(fn ($row, $i) => [
            'name' => $row->brand,
            'value' => $money((float) $row->total),
            'value_afn' => round((float) $row->total, 2),
            'value_3c' => $brand3c["b{$i}"] ?? null,
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
            ->get();

        $topProduct3c = $this->currencies->mapThreeCurrency(
            $topProducts->flatMap(fn ($row, $i) => [
                "s{$i}" => (float) $row->total_sales,
                "p{$i}" => (float) $row->total_profit,
            ])->all()
        );

        $topProducts = $topProducts->values()->map(function ($row, $i) use ($money, $topProduct3c) {
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
                'total_sales_3c' => $topProduct3c["s{$i}"] ?? null,
                'total_profit_3c' => $topProduct3c["p{$i}"] ?? null,
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
            ->get();

        $branch3c = $this->currencies->mapThreeCurrency(
            $branchStats->flatMap(fn ($row, $i) => [
                "s{$i}" => (float) $row->total_sales,
                "p{$i}" => (float) $row->total_profit,
            ])->all()
        );

        $branchStats = $branchStats->values()->map(fn ($row, $i) => [
            'id' => $row->id,
            'name' => $row->name,
            'code' => $row->code,
            'sales' => $money((float) $row->total_sales),
            'profit' => $money((float) $row->total_profit),
            'sales_afn' => round((float) $row->total_sales, 2),
            'profit_afn' => round((float) $row->total_profit, 2),
            'sales_3c' => $branch3c["s{$i}"] ?? null,
            'profit_3c' => $branch3c["p{$i}"] ?? null,
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
            ->get();

        $periodDueLedger = (float) (clone $completedSales)
            ->whereDate('sale_date', '>=', $startDate->toDateString())
            ->sum('remaining_due_afn');

        $paymentLedger = $paymentMethods->mapWithKeys(
            fn ($row, $i) => ["pm{$i}" => (float) $row->total]
        )->all();
        if ($periodDueLedger > 0) {
            $paymentLedger['due'] = $periodDueLedger;
        }
        $payment3c = $this->currencies->mapThreeCurrency($paymentLedger);

        $paymentMethods = $paymentMethods->values()->map(fn ($row, $i) => [
            'name' => ucfirst((string) $row->payment_method),
            'value' => $money((float) $row->total),
            'value_afn' => round((float) $row->total, 2),
            'value_3c' => $payment3c["pm{$i}"] ?? null,
        ]);

        if ($periodDueLedger > 0) {
            $paymentMethods->push([
                'name' => 'Due',
                'value' => $money($periodDueLedger),
                'value_afn' => round($periodDueLedger, 2),
                'value_3c' => $payment3c['due'] ?? null,
            ]);
        }

        $paymentCurrencyTotals = $this->paymentCurrencyTotals($branchId, $startDate->toDateString());

        /* =========================================================
         |  COUNTERS
         ========================================================= */

        $counters = [
            'products' => Product::notTrashed()->count(),
            'variants' => ProductVariant::notTrashed()->count(),
            'customers' => Customer::notTrashed()->count(),
            'suppliers' => Supplier::notTrashed()->count(),
            'investors' => Investor::notTrashed()->count(),
            'users' => User::notTrashed()->count(),
            'total_stock' => (int) Inventory::notTrashed()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->sum('available_quantity'),
            'low_stock' => $this->lowStockQuery($branchId)->count(),
            'out_of_stock' => Inventory::notTrashed()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where('available_quantity', '<=', 0)
                ->count(),
        ];

        /* =========================================================
         |  RECENT ACTIVITY
         ========================================================= */

        $ledgerCode = $reporting['ledger']['code'] ?? (string) config('business.default_currency', 'AFN');

        $recentSalesModels = Sale::query()
            ->with(['branch:id,name,code', 'customer:id,name'])
            ->notTrashed()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $recentSales3c = $this->currencies->mapThreeCurrency(
            $recentSalesModels->mapWithKeys(fn ($s) => ["sale{$s->id}" => (float) $s->total_afn])->all()
        );

        $recentSales = $recentSalesModels->map(function ($s) use ($money, $ledgerCode, $recentSales3c) {
            $ledgerTotal = (float) $s->total_afn;

            return [
                'id' => $s->id,
                'invoice_number' => $s->invoice_number,
                'total_afn' => $money($ledgerTotal),
                'total_ledger' => round($ledgerTotal, 2),
                'total_3c' => $recentSales3c["sale{$s->id}"] ?? null,
                'original_amount' => $s->sale_amount_currency !== null ? (float) $s->sale_amount_currency : round($ledgerTotal, 2),
                'original_currency' => $s->sale_currency_code ?: ($s->ledger_currency_code ?: $ledgerCode),
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
                'variant:id,product_id,volume_ml,concentration',
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
                    'min_stock' => Inventory::LOW_STOCK_THRESHOLD,
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

        $due3c = $this->currencies->mapThreeCurrency(
            $dueCustomers->mapWithKeys(fn (Customer $c) => ["due{$c->id}" => (float) $c->current_due_afn])->all()
        );

        $dueCustomers = $dueCustomers->map(function (Customer $customer) use ($dueDates, $money, $due3c) {
            $dueDate = $dueDates->get($customer->id);
            $ledgerDue = (float) $customer->current_due_afn;

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'current_due_afn' => $money($ledgerDue),
                'current_due_ledger' => round($ledgerDue, 2),
                'current_due_3c' => $due3c["due{$customer->id}"] ?? null,
                'due_date' => $dueDate ? Carbon::parse($dueDate)->toDateString() : null,
            ];
        });

        $storeAssetsRaw = StoreAssetsService::summarize($branchId);
        $storeAssets3c = $this->currencies->mapThreeCurrency([
            'remaining_cost' => (float) ($storeAssetsRaw['summary']['remaining_cost_afn'] ?? 0),
            'total_cost' => (float) ($storeAssetsRaw['summary']['total_cost_afn'] ?? 0),
            'expected_profit' => (float) ($storeAssetsRaw['summary']['expected_profit_afn'] ?? 0),
            'realized_sales' => (float) ($storeAssetsRaw['summary']['realized_sales_afn'] ?? 0),
            'realized_profit' => (float) ($storeAssetsRaw['summary']['realized_profit_afn'] ?? 0),
        ]);
        $storeAssets = [
            'summary' => array_merge($storeAssetsRaw['summary'], [
                'remaining_cost' => $money((float) ($storeAssetsRaw['summary']['remaining_cost_afn'] ?? 0)),
                'total_cost' => $money((float) ($storeAssetsRaw['summary']['total_cost_afn'] ?? 0)),
                'expected_profit' => $money((float) ($storeAssetsRaw['summary']['expected_profit_afn'] ?? 0)),
                'realized_sales' => $money((float) ($storeAssetsRaw['summary']['realized_sales_afn'] ?? 0)),
                'realized_profit' => $money((float) ($storeAssetsRaw['summary']['realized_profit_afn'] ?? 0)),
                'remaining_cost_3c' => $storeAssets3c['remaining_cost'] ?? null,
                'total_cost_3c' => $storeAssets3c['total_cost'] ?? null,
                'expected_profit_3c' => $storeAssets3c['expected_profit'] ?? null,
                'realized_sales_3c' => $storeAssets3c['realized_sales'] ?? null,
                'realized_profit_3c' => $storeAssets3c['realized_profit'] ?? null,
            ]),
            'items' => $storeAssetsRaw['items'],
        ];

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

        return Inertia::render('dashboards/overview', [
            'dueCustomers' => $dueCustomers,
            'period' => $period,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => $days,
            'kpis' => $kpis,
            'kpis3c' => $kpis3c,
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
            'storeAssets' => $storeAssets,
            'reporting' => $reporting,
            'fxBoard' => $fxBoard,
            // Rates: use shared Inertia `exchange` (ExchangeRateService::flatBoard) via useExchange()
            'filters' => [
                'branch_id' => $branchId,
            ],
        ]);
    }

    /**
     * Original tender totals for payments in the selected period.
     * Uses amount_currency + currency_code snapshots from each payment --
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
     * Same rule as the inventory low-stock page: quantity at or below the fixed threshold.
     */
    private function lowStockQuery(?int $branchId = null)
    {
        return Inventory::notTrashed()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->lowStock();
    }
}
