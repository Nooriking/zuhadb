<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Expense;
use App\Models\Investor;
use App\Models\SaleReturn;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfitReportController extends Controller
{
    private const SECTIONS = ['overview', 'sales', 'products', 'investors'];

    private const GROUPS = ['daily', 'monthly', 'yearly'];

    /* ═══════════════════════════════════════════════════════
     |  MAIN REPORT
     ═══════════════════════════════════════════════════════ */

    public function index(Request $request): Response
    {
        $f = $this->resolveFilters($request);

        return Inertia::render('reports/profit', [
            ...$this->buildReport($f),
            'branches' => $this->branchOptions(),
            'filters' => $this->filtersPayload($f),
            'tab' => in_array($request->query('tab'), self::SECTIONS, true) ? $request->query('tab') : 'overview',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $f = $this->resolveFilters($request);

        $section = $request->query('section', 'all');
        $sections = in_array($section, self::SECTIONS, true) ? [$section] : self::SECTIONS;

        $titles = [
            'overview' => 'Profit Overview Report',
            'sales' => 'Sales Report',
            'products' => 'Product Report',
            'investors' => 'Investor Report',
        ];

        $data = [
            ...$this->buildReport($f),
            ...$this->pdfMeta($f, count($sections) === 1 ? $titles[$sections[0]] : 'Complete Business Report'),
            'sections' => $sections,
        ];

        $suffix = count($sections) === 1 ? $sections[0] : 'full';

        return $this->renderPdf('reports.profit-pdf', $data, "report-{$suffix}-{$f['from']}-to-{$f['to']}.pdf");
    }

    /* ═══════════════════════════════════════════════════════
     |  INVESTOR DETAIL
     ═══════════════════════════════════════════════════════ */

    public function investorDetail(Request $request, int $investorId): Response
    {
        $f = $this->resolveFilters($request, 'year');

        return Inertia::render('reports/investor-detail', [
            ...$this->buildInvestorDetail($investorId, $f),
            'branches' => $this->branchOptions(),
            'filters' => $this->filtersPayload($f),
        ]);
    }

    public function investorPdf(Request $request, int $investorId)
    {
        $f = $this->resolveFilters($request, 'year');
        $detail = $this->buildInvestorDetail($investorId, $f);
        $slug = str($detail['investor']['full_name'])->slug();

        return $this->renderPdf(
            'reports.investor-pdf',
            [...$detail, ...$this->pdfMeta($f, 'Investor Statement')],
            "investor-{$slug}-{$f['from']}-to-{$f['to']}.pdf",
        );
    }

    /* ═══════════════════════════════════════════════════════
     |  STORE DETAIL
     ═══════════════════════════════════════════════════════ */

    public function storeDetail(Request $request): Response
    {
        $f = $this->resolveFilters($request, 'year');

        return Inertia::render('reports/store-detail', [
            ...$this->buildStoreDetail($f),
            'branches' => $this->branchOptions(),
            'filters' => $this->filtersPayload($f),
        ]);
    }

    public function storePdf(Request $request)
    {
        $f = $this->resolveFilters($request, 'year');

        return $this->renderPdf(
            'reports.store-pdf',
            [...$this->buildStoreDetail($f), ...$this->pdfMeta($f, 'Store-Owned Goods Report')],
            "store-report-{$f['from']}-to-{$f['to']}.pdf",
        );
    }

    /* ═══════════════════════════════════════════════════════
     |  CSV EXPORTS
     ═══════════════════════════════════════════════════════ */

    public function export(Request $request): StreamedResponse
    {
        return $this->exportCsv($request, 'full');
    }

    public function exportSummary(Request $request): StreamedResponse
    {
        return $this->exportCsv($request, 'summary');
    }

    public function exportBreakdown(Request $request): StreamedResponse
    {
        return $this->exportCsv($request, 'breakdown');
    }

    public function exportPerInvestor(Request $request): StreamedResponse
    {
        return $this->exportCsv($request, 'investors');
    }

    public function exportExchangeRates(Request $request): StreamedResponse
    {
        return $this->exportCsv($request, 'exchange-rates');
    }

    public function exportSales(Request $request): StreamedResponse
    {
        return $this->exportCsv($request, 'sales');
    }

    public function exportProducts(Request $request): StreamedResponse
    {
        return $this->exportCsv($request, 'products');
    }

    private function exportCsv(Request $request, string $type): StreamedResponse
    {
        $f = $this->resolveFilters($request);
        $report = $this->buildReport($f);
        $filename = "profit-report-{$type}-{$f['from']}-to-{$f['to']}.csv";
        $n = fn ($v) => number_format((float) $v, 2, '.', '');
        $has = fn (string ...$types) => $type === 'full' || in_array($type, $types, true);

        return response()->streamDownload(function () use ($report, $f, $type, $n, $has) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            $title = function (string $text) use ($out) {
                fputcsv($out, []);
                fputcsv($out, [strtoupper($text)]);
            };

            $s = $report['summary'];

            fputcsv($out, ['PROFIT REPORT']);
            fputcsv($out, ['Period', $f['label']]);
            fputcsv($out, ['From', $f['from']]);
            fputcsv($out, ['To', $f['to']]);
            fputcsv($out, ['Branch', $f['branch_name'] ?? 'All branches']);
            fputcsv($out, ['Type', ucfirst($type)]);
            fputcsv($out, ['Generated At', now()->format('Y-m-d H:i:s')]);

            $title('Summary');
            fputcsv($out, ['Metric', 'Amount']);
            fputcsv($out, ['Total Sales (AFN)', $n($s['total_sales'])]);
            fputcsv($out, ['Returns (AFN)', $n($s['total_returns'])]);
            fputcsv($out, ['Discounts (AFN)', $n($s['total_discount'])]);
            fputcsv($out, ['Total Cost (AFN)', $n($s['total_cost'])]);
            fputcsv($out, ['Gross Profit (AFN)', $n($s['total_profit'])]);
            fputcsv($out, ['Investor Profit (AFN)', $n($s['investor_profit'])]);
            fputcsv($out, ['Store Profit (AFN)', $n($s['store_profit'])]);
            fputcsv($out, ['Total Expenses (AFN)', $n($s['total_expenses'])]);
            fputcsv($out, ['Store Net Profit (AFN)', $n($s['store_net_profit'])]);
            fputcsv($out, ['Profit Margin (%)', $n($s['margin'])]);
            fputcsv($out, ['Orders', $s['total_orders']]);
            fputcsv($out, ['Units Sold', $s['total_quantity']]);

            if ($has('breakdown')) {
                $title(ucfirst($f['group_by']).' breakdown');
                fputcsv($out, ['Period', 'Orders', 'Qty', 'Sales', 'Cost', 'Investor Profit', 'Store Profit', 'Gross Profit', 'Expenses', 'Store Net']);
                foreach ($report['breakdown'] as $r) {
                    fputcsv($out, [$r['period'], $r['orders'], $r['quantity'], $n($r['sales']), $n($r['cost']), $n($r['investor_profit']), $n($r['store_profit']), $n($r['total_profit']), $n($r['expenses']), $n($r['store_net'])]);
                }
            }

            if ($has('summary')) {
                $title('Expenses by category');
                fputcsv($out, ['Category', 'Count', 'Total (AFN)']);
                foreach ($report['expenses']['by_category'] as $r) {
                    fputcsv($out, [$r['category'], $r['count'], $n($r['total'])]);
                }
            }

            if ($has('sales')) {
                foreach (['by_branch' => 'Sales by branch', 'by_seller' => 'Sales by seller', 'by_payment' => 'Sales by payment method', 'by_customer' => 'Top customers'] as $key => $label) {
                    $title($label);
                    fputcsv($out, ['Name', 'Orders', 'Qty', 'Sales', 'Cost', 'Gross Profit', 'Margin %']);
                    foreach ($report['sales'][$key] as $r) {
                        fputcsv($out, [$r['name'], $r['orders'], $r['quantity'] ?? '', $n($r['sales']), $n($r['cost']), $n($r['total_profit']), $n($r['margin'])]);
                    }
                }
            }

            if ($has('products')) {
                $title('Products');
                fputcsv($out, ['Product', 'Brand', 'Orders', 'Qty', 'Sales', 'Cost', 'Investor Profit', 'Store Profit', 'Gross Profit', 'Margin %']);
                foreach ($report['products']['by_product'] as $r) {
                    fputcsv($out, [$r['name'], $r['brand'], $r['orders'], $r['quantity'], $n($r['sales']), $n($r['cost']), $n($r['investor_profit']), $n($r['store_profit']), $n($r['total_profit']), $n($r['margin'])]);
                }
                $title('Brands');
                fputcsv($out, ['Brand', 'Products', 'Qty', 'Sales', 'Cost', 'Gross Profit', 'Margin %']);
                foreach ($report['products']['by_brand'] as $r) {
                    fputcsv($out, [$r['name'], $r['products'], $r['quantity'], $n($r['sales']), $n($r['cost']), $n($r['total_profit']), $n($r['margin'])]);
                }
            }

            if ($has('investors')) {
                $title('Investors');
                fputcsv($out, ['Investor', 'Orders', 'Qty', 'Sales', 'Investor Profit', 'Store Share', 'Profit Paid', 'Profit Pending', 'Capital Balance', 'Profit Balance']);
                foreach ($report['investors']['rows'] as $r) {
                    fputcsv($out, [$r['name'], $r['orders'], $r['quantity'], $n($r['sales']), $n($r['investor_profit']), $n($r['store_profit']), $n($r['entries_paid']), $n($r['entries_pending']), $n($r['capital_balance']), $n($r['profit_balance'])]);
                }
                $store = $report['investors']['store'];
                fputcsv($out, ['Store (own goods)', $store['orders'], $store['quantity'], $n($store['sales']), '', $n($store['store_profit']), '', '', '', '']);
            }

            if ($has('exchange-rates')) {
                $title('Exchange rates by month');
                fputcsv($out, ['Month', 'Avg Rate (AFN/USD)', 'Min', 'Max', 'Days Recorded']);
                foreach ($report['exchangeRates'] as $r) {
                    fputcsv($out, [$r['month'], $n($r['avg_rate']), $n($r['min_rate']), $n($r['max_rate']), $r['days']]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /* ═══════════════════════════════════════════════════════
     |  FILTERS
     ═══════════════════════════════════════════════════════ */

    /**
     * period=month&month=YYYY-MM | period=year&year=YYYY | period=custom&from=&to=
     */
    private function resolveFilters(Request $request, string $defaultPeriod = 'month'): array
    {
        $period = $request->query('period');
        if (! in_array($period, ['month', 'year', 'custom'], true)) {
            $period = ($request->filled('from') || $request->filled('to')) ? 'custom' : $defaultPeriod;
        }

        $month = (string) $request->query('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = now()->format('Y-m');
        }

        $year = (int) $request->query('year', now()->year);
        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        switch ($period) {
            case 'month':
                $start = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfDay();
                $from = $start->toDateString();
                $to = $start->copy()->endOfMonth()->toDateString();
                $defaultGroup = 'daily';
                $label = $start->format('F Y');
                break;

            case 'year':
                $from = "{$year}-01-01";
                $to = "{$year}-12-31";
                $defaultGroup = 'monthly';
                $label = "Year {$year}";
                break;

            default:
                $from = $this->safeDate($request->query('from'), now()->subDays(30)->toDateString());
                $to = $this->safeDate($request->query('to'), now()->toDateString());
                if ($from > $to) {
                    [$from, $to] = [$to, $from];
                }
                $defaultGroup = Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 92 ? 'monthly' : 'daily';
                $label = "{$from} to {$to}";
        }

        $groupBy = in_array($request->query('group_by'), self::GROUPS, true) ? $request->query('group_by') : $defaultGroup;

        $branchId = $request->integer('branch_id') ?: null;
        $user = $request->user();
        if ($user?->isBranchScoped()) {
            $branchId = (int) $user->branch_id;
        }

        return [
            'period' => $period,
            'month' => $month,
            'year' => $year,
            'from' => $from,
            'to' => $to,
            'group_by' => $groupBy,
            'branch_id' => $branchId,
            'branch_name' => $branchId ? DB::table('branches')->where('id', $branchId)->value('name') : null,
            'label' => $label,
            // Matrix tables compare people across months (or years) side by side.
            'matrix_by' => $groupBy === 'yearly' ? 'yearly' : 'monthly',
        ];
    }

    private function filtersPayload(array $f): array
    {
        return [
            'period' => $f['period'],
            'month' => $f['month'],
            'year' => $f['year'],
            'from' => $f['from'],
            'to' => $f['to'],
            'group_by' => $f['group_by'],
            'branch_id' => $f['branch_id'],
            'branch_name' => $f['branch_name'],
            'label' => $f['label'],
            'locked_branch' => (bool) request()->user()?->isBranchScoped(),
        ];
    }

    private function safeDate(mixed $value, string $fallback): string
    {
        if (is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return $value;
        }

        return $fallback;
    }

    private function branchOptions(): array
    {
        return DB::table('branches')
            ->where('trash', false)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])
            ->all();
    }

    /* ═══════════════════════════════════════════════════════
     |  BASE QUERIES
     ═══════════════════════════════════════════════════════ */

    private function scopeSales(Builder $query, array $f): Builder
    {
        return $query
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$f['from'], $f['to']])
            ->when($f['branch_id'], fn ($q, $id) => $q->where('sales.branch_id', $id));
    }

    private function salesBase(array $f): Builder
    {
        return $this->scopeSales(DB::table('sales'), $f);
    }

    private function itemsBase(array $f): Builder
    {
        return $this->scopeSales(
            DB::table('sale_items')->join('sales', 'sale_items.sale_id', '=', 'sales.id'),
            $f,
        );
    }

    private function expensesBase(array $f): Builder
    {
        return Expense::query()
            ->notTrashed()
            ->whereBetween('expense_date', [$f['from'], $f['to']])
            ->when($f['branch_id'], fn ($q, $id) => $q->where('branch_id', $id))
            ->toBase();
    }

    private function returnsBase(array $f): Builder
    {
        return SaleReturn::query()
            ->notTrashed()
            ->completed()
            ->whereBetween('return_date', [$f['from'], $f['to']])
            ->when($f['branch_id'], fn ($q, $id) => $q->where('branch_id', $id))
            ->toBase();
    }

    private function periodExpr(string $groupBy, string $column): string
    {
        return match ($groupBy) {
            'yearly' => "CAST(YEAR({$column}) AS CHAR)",
            'monthly' => "DATE_FORMAT({$column}, '%Y-%m')",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d')",
        };
    }

    private const SALE_TOTALS = '
        COUNT(*) as orders,
        COALESCE(SUM(sales.total_afn), 0) as sales,
        COALESCE(SUM(sales.discount_afn), 0) as discount,
        COALESCE(SUM(sales.total_cost_afn), 0) as cost,
        COALESCE(SUM(sales.total_investor_profit_afn), 0) as investor_profit,
        COALESCE(SUM(sales.store_profit_afn), 0) as store_profit
    ';

    // sale_items.store_profit_afn holds the whole line profit; the investor share is carved out of it.
    private const ITEM_TOTALS = '
        COUNT(DISTINCT sales.id) as orders,
        COALESCE(SUM(sale_items.quantity), 0) as quantity,
        COALESCE(SUM(sale_items.line_total_afn), 0) as sales,
        COALESCE(SUM(sale_items.cost_price_afn * sale_items.quantity), 0) as cost,
        COALESCE(SUM(sale_items.investor_profit_afn), 0) as investor_profit,
        COALESCE(SUM(sale_items.store_profit_afn - sale_items.investor_profit_afn), 0) as store_profit
    ';

    /** Normalises an aggregated row into the shape every report table uses. */
    private function metrics(object $row, array $extra = []): array
    {
        $sales = round((float) ($row->sales ?? 0), 2);
        $investor = round((float) ($row->investor_profit ?? 0), 2);
        $store = round((float) ($row->store_profit ?? 0), 2);
        $profit = round($investor + $store, 2);
        $orders = (int) ($row->orders ?? 0);

        return [
            ...$extra,
            'orders' => $orders,
            'quantity' => isset($row->quantity) ? (int) $row->quantity : null,
            'sales' => $sales,
            'discount' => round((float) ($row->discount ?? 0), 2),
            'cost' => round((float) ($row->cost ?? 0), 2),
            'investor_profit' => $investor,
            'store_profit' => $store,
            'total_profit' => $profit,
            'margin' => $sales > 0 ? round($profit / $sales * 100, 1) : 0,
            'avg_order' => $orders > 0 ? round($sales / $orders, 2) : 0,
        ];
    }

    /* ═══════════════════════════════════════════════════════
     |  REPORT BUILDERS
     ═══════════════════════════════════════════════════════ */

    private function buildReport(array $f): array
    {
        return [
            'summary' => $this->buildSummary($f),
            'breakdown' => $this->buildBreakdown($f),
            'expenses' => $this->buildExpenses($f),
            'exchangeRates' => $this->buildExchangeRates($f),
            'sales' => $this->buildSalesSection($f),
            'products' => $this->buildProductsSection($f),
            'investors' => $this->buildInvestorsSection($f),
            'reporting' => $this->reportingMeta(),
            'purchases_by_currency' => $this->purchasesByCurrency($f),
        ];
    }

    private function buildSummary(array $f): array
    {
        $row = $this->salesBase($f)
            ->selectRaw(self::SALE_TOTALS.', COUNT(DISTINCT sales.customer_id) as customers')
            ->first();

        $quantity = (int) $this->itemsBase($f)->sum('sale_items.quantity');
        $expenses = round((float) $this->expensesBase($f)->sum('amount_afn'), 2);
        $returns = $this->returnsBase($f)->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_afn), 0) as total')->first();

        $m = $this->metrics($row);

        return [
            'from' => $f['from'],
            'to' => $f['to'],
            'total_sales' => $m['sales'],
            'total_discount' => $m['discount'],
            'total_returns' => round((float) ($returns->total ?? 0), 2),
            'returns_count' => (int) ($returns->cnt ?? 0),
            'total_cost' => $m['cost'],
            'total_profit' => $m['total_profit'],
            'investor_profit' => $m['investor_profit'],
            'store_profit' => $m['store_profit'],
            'total_expenses' => $expenses,
            'net_profit' => round($m['total_profit'] - $expenses, 2),
            'store_net_profit' => round($m['store_profit'] - $expenses, 2),
            'margin' => $m['margin'],
            'avg_order' => $m['avg_order'],
            'total_quantity' => $quantity,
            'total_orders' => $m['orders'],
            'customers' => (int) ($row->customers ?? 0),
            'currency_code' => (string) config('business.default_currency', 'AFN'),
            'currency_note' => 'Totals are ledger AFN. USD equivalents are conversions, not extra revenue. Original purchase currencies are listed separately and are never added together.',
        ];
    }

    /**
     * @return array{ledger_code: string, default_code: string|null, rate_direction: string}
     */
    private function reportingMeta(): array
    {
        $ledger = \App\Models\Currency::ledger();
        $default = \App\Models\Currency::getDefault();

        return [
            'ledger_code' => $ledger?->code ?? (string) config('business.default_currency', 'AFN'),
            'default_code' => $default?->code,
            'rate_direction' => 'Catalog: 1 unit of a currency = rate_to_default units of the default currency. Purchase snapshots: 1 unit of the purchase currency = rate_to_ledger AFN. Completed sales store a catalog USD snapshot and do not change when later rates change.',
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return list<array{code: string, total: string, kind: string}>
     */
    private function purchasesByCurrency(array $f): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('products', 'purchase_line_amount')) {
            return [];
        }

        return \App\Models\Product::query()
            ->whereNotNull('variant_id')
            ->where('trash', false)
            ->whereBetween('purchase_date', [$f['from'], $f['to']])
            ->when($f['branch_id'], function ($query) {
                // Purchase rows are not branch-scoped; skip mixing branch filters into original-currency totals.
            })
            ->selectRaw("COALESCE(purchase_currency_code, 'AFN') as code, SUM(COALESCE(purchase_line_amount, purchase_price_afn)) as total")
            ->groupBy('code')
            ->get()
            ->map(fn ($row) => [
                'code' => (string) $row->code,
                'total' => number_format((float) $row->total, 2, '.', ''),
                'kind' => 'original',
            ])
            ->values()
            ->all();
    }

    private function buildBreakdown(array $f): array
    {
        $saleExpr = $this->periodExpr($f['group_by'], 'sales.sale_date');

        $sales = $this->salesBase($f)
            ->selectRaw("{$saleExpr} as period, ".self::SALE_TOTALS)
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $qty = $this->itemsBase($f)
            ->selectRaw("{$saleExpr} as period, SUM(sale_items.quantity) as qty")
            ->groupBy('period')
            ->pluck('qty', 'period');

        $expenses = $this->expensesBase($f)
            ->selectRaw($this->periodExpr($f['group_by'], 'expense_date').' as period, SUM(amount_afn) as total')
            ->groupBy('period')
            ->pluck('total', 'period');

        $returns = $this->returnsBase($f)
            ->selectRaw($this->periodExpr($f['group_by'], 'return_date').' as period, SUM(total_afn) as total')
            ->groupBy('period')
            ->pluck('total', 'period');

        return $sales->keys()
            ->merge($expenses->keys())
            ->merge($returns->keys())
            ->unique()
            ->sortDesc()
            ->values()
            ->map(function ($period) use ($sales, $qty, $expenses, $returns) {
                $m = $this->metrics($sales->get($period) ?? (object) []);
                $expense = round((float) ($expenses->get($period) ?? 0), 2);

                return [
                    ...$m,
                    'period' => (string) $period,
                    'quantity' => (int) ($qty->get($period) ?? 0),
                    'returns' => round((float) ($returns->get($period) ?? 0), 2),
                    'expenses' => $expense,
                    'net' => round($m['total_profit'] - $expense, 2),
                    'store_net' => round($m['store_profit'] - $expense, 2),
                ];
            })
            ->all();
    }

    private function buildExpenses(array $f): array
    {
        $byCategory = $this->expensesBase($f)
            ->selectRaw('category, COUNT(*) as count, SUM(amount_afn) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'category' => ucfirst(str_replace('_', ' ', $r->category ?? 'other')),
                'count' => (int) $r->count,
                'total' => round((float) $r->total, 2),
            ])
            ->all();

        return [
            'total' => round((float) $this->expensesBase($f)->sum('amount_afn'), 2),
            'by_category' => $byCategory,
        ];
    }

    private function buildExchangeRates(array $f): array
    {
        return DB::table('exchange_rate_history')
            ->whereBetween('rate_date', [$f['from'], $f['to']])
            ->selectRaw("
                DATE_FORMAT(rate_date, '%Y-%m') as month,
                AVG(usd_to_afn) as avg_rate,
                MIN(usd_to_afn) as min_rate,
                MAX(usd_to_afn) as max_rate,
                COUNT(*) as days
            ")
            ->groupBy('month')
            ->orderByDesc('month')
            ->get()
            ->map(fn ($r) => [
                'month' => $r->month,
                'avg_rate' => round((float) $r->avg_rate, 2),
                'min_rate' => round((float) $r->min_rate, 2),
                'max_rate' => round((float) $r->max_rate, 2),
                'days' => (int) $r->days,
            ])
            ->all();
    }

    /* ── Sales section ─────────────────────────────────────── */

    private function buildSalesSection(array $f): array
    {
        $qtyBy = fn (string $column) => $this->itemsBase($f)
            ->selectRaw("{$column} as k, SUM(sale_items.quantity) as qty")
            ->groupBy('k')
            ->pluck('qty', 'k');

        $branchQty = $qtyBy('sales.branch_id');
        $byBranch = $this->salesBase($f)
            ->leftJoin('branches', 'branches.id', '=', 'sales.branch_id')
            ->selectRaw('sales.branch_id as id, branches.name as name, '.self::SALE_TOTALS)
            ->groupBy('sales.branch_id', 'branches.name')
            ->orderByDesc('sales')
            ->get()
            ->map(fn ($r) => [
                ...$this->metrics($r, ['id' => $r->id, 'name' => $r->name ?? 'Unknown branch']),
                'quantity' => (int) ($branchQty->get($r->id) ?? 0),
            ])
            ->all();

        $sellerQty = $qtyBy('COALESCE(sales.created_by, 0)');
        $bySeller = $this->salesBase($f)
            ->leftJoin('users', 'users.id', '=', 'sales.created_by')
            ->selectRaw('sales.created_by as id, users.name as name, users.role as role, '.self::SALE_TOTALS.', COUNT(DISTINCT sales.customer_id) as customers')
            ->groupBy('sales.created_by', 'users.name', 'users.role')
            ->orderByDesc('sales')
            ->get()
            ->map(fn ($r) => [
                ...$this->metrics($r, [
                    'id' => $r->id,
                    'name' => $r->name ?? 'Unknown user',
                    'role' => $r->role ? ucwords(str_replace('_', ' ', $r->role)) : null,
                ]),
                'quantity' => (int) ($sellerQty->get($r->id ?? 0) ?? 0),
                'customers' => (int) $r->customers,
            ])
            ->all();

        $byPayment = $this->salesBase($f)
            ->selectRaw('sales.payment_method as name, '.self::SALE_TOTALS)
            ->groupBy('sales.payment_method')
            ->orderByDesc('sales')
            ->get()
            ->map(fn ($r) => $this->metrics($r, ['name' => ucfirst($r->name ?? 'other')]))
            ->all();

        $byCustomer = $this->salesBase($f)
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->selectRaw('sales.customer_id as id, customers.name as name, customers.phone as phone, '.self::SALE_TOTALS.', MAX(sales.sale_date) as last_sale')
            ->groupBy('sales.customer_id', 'customers.name', 'customers.phone')
            ->orderByDesc('sales')
            ->limit(30)
            ->get()
            ->map(fn ($r) => $this->metrics($r, [
                'id' => $r->id,
                'name' => $r->id ? ($r->name ?? 'Customer #'.$r->id) : 'Walk-in customers',
                'phone' => $r->phone,
                'last_sale' => $r->last_sale,
            ]))
            ->all();

        $sellerMatrix = $this->buildMatrix(
            $this->salesBase($f)->leftJoin('users', 'users.id', '=', 'sales.created_by'),
            'sales.created_by',
            'users.name',
            'SUM(sales.total_afn)',
            $this->periodExpr($f['matrix_by'], 'sales.sale_date'),
            'Unknown user',
        );

        return [
            'by_branch' => $byBranch,
            'by_seller' => $bySeller,
            'by_payment' => $byPayment,
            'by_customer' => $byCustomer,
            'seller_matrix' => $sellerMatrix,
        ];
    }

    /* ── Products section ──────────────────────────────────── */

    private function buildProductsSection(array $f): array
    {
        $base = fn () => $this->itemsBase($f)
            ->join('product_variants', 'sale_items.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id');

        $byProduct = $base()
            ->selectRaw('
                product_variants.id as variant_id,
                products.name as product_name,
                brands.name as brand,
                product_variants.volume_ml,
                product_variants.concentration,
                '.self::ITEM_TOTALS)
            ->groupBy('product_variants.id', 'products.name', 'brands.name', 'product_variants.volume_ml', 'product_variants.concentration')
            ->orderByDesc('sales')
            ->get()
            ->map(fn ($r) => $this->metrics($r, [
                'id' => $r->variant_id,
                'name' => trim(implode(' · ', array_filter([
                    $r->product_name,
                    $r->volume_ml ? "{$r->volume_ml}ml" : null,
                    $r->concentration ? strtoupper($r->concentration) : null,
                ]))),
                'brand' => $r->brand ?? '—',
            ]))
            ->all();

        $byBrand = $base()
            ->selectRaw('products.brand_id as brand_id, brands.name as brand, COUNT(DISTINCT product_variants.id) as products, '.self::ITEM_TOTALS)
            ->groupBy('products.brand_id', 'brands.name')
            ->orderByDesc('sales')
            ->get()
            ->map(fn ($r) => [
                ...$this->metrics($r, ['id' => $r->brand_id, 'name' => $r->brand ?? 'No brand']),
                'products' => (int) $r->products,
            ])
            ->all();

        return [
            'by_product' => $byProduct,
            'by_brand' => $byBrand,
            'stats' => [
                'variants_sold' => count($byProduct),
                'brands_sold' => count($byBrand),
                'top_product' => $byProduct[0]['name'] ?? null,
                'top_brand' => $byBrand[0]['name'] ?? null,
            ],
        ];
    }

    /* ── Investors section ─────────────────────────────────── */

    private function buildInvestorsSection(array $f): array
    {
        $batchItems = fn () => $this->itemsBase($f)
            ->join('product_batches', 'sale_items.batch_id', '=', 'product_batches.id');

        $activity = $batchItems()
            ->whereNotNull('product_batches.investor_id')
            ->selectRaw('product_batches.investor_id as investor_id, '.self::ITEM_TOTALS)
            ->groupBy('product_batches.investor_id')
            ->get()
            ->keyBy('investor_id');

        $storeRow = $this->itemsBase($f)
            ->leftJoin('product_batches', 'sale_items.batch_id', '=', 'product_batches.id')
            ->whereNull('product_batches.investor_id')
            ->selectRaw(self::ITEM_TOTALS)
            ->first();

        $entries = $this->profitEntriesBase($f)
            ->selectRaw('investor_profit_entries.investor_id, investor_profit_entries.status, SUM(investor_profit_entries.profit_amount_afn) as total')
            ->groupBy('investor_profit_entries.investor_id', 'investor_profit_entries.status')
            ->get()
            ->groupBy('investor_id');

        $totalInvestorProfit = (float) $activity->sum('investor_profit');

        $rows = Investor::query()
            ->notTrashed()
            ->with('wallet')
            ->orderBy('full_name')
            ->get()
            ->map(function (Investor $inv) use ($activity, $entries, $totalInvestorProfit) {
                $m = $this->metrics($activity->get($inv->id) ?? (object) ['quantity' => 0]);
                $status = collect($entries->get($inv->id, []))->pluck('total', 'status');
                $wallet = $inv->wallet;

                return [
                    ...$m,
                    'id' => $inv->id,
                    'name' => $inv->full_name,
                    'phone' => $inv->phone,
                    'status' => $inv->status,
                    'share' => $totalInvestorProfit > 0 ? round($m['investor_profit'] / $totalInvestorProfit * 100, 1) : 0,
                    'entries_paid' => round((float) ($status['paid'] ?? 0), 2),
                    'entries_pending' => round((float) ($status['pending'] ?? 0), 2),
                    'total_invested' => round((float) ($wallet?->total_invested_afn ?? 0), 2),
                    'capital_balance' => round((float) ($wallet?->capital_balance_afn ?? 0), 2),
                    'profit_balance' => round((float) ($wallet?->profit_balance_afn ?? 0), 2),
                    'total_withdrawn' => round((float) ($wallet?->total_withdrawn_afn ?? 0), 2),
                    'lifetime_profit' => round((float) ($wallet?->total_profit_earned_afn ?? 0), 2),
                ];
            })
            ->sortByDesc('investor_profit')
            ->values();

        $matrix = $this->buildMatrix(
            $batchItems()
                ->join('investors', 'investors.id', '=', 'product_batches.investor_id'),
            'product_batches.investor_id',
            'investors.full_name',
            'SUM(sale_items.investor_profit_afn)',
            $this->periodExpr($f['matrix_by'], 'sales.sale_date'),
            'Unknown investor',
        );

        return [
            'rows' => $rows->all(),
            'store' => $this->metrics($storeRow ?? (object) []),
            'matrix' => $matrix,
            'stats' => [
                'total_investors' => $rows->count(),
                'active_investors' => $activity->count(),
                'investor_profit' => round($totalInvestorProfit, 2),
                'total_invested' => round((float) $rows->sum('total_invested'), 2),
                'capital_balance' => round((float) $rows->sum('capital_balance'), 2),
                'profit_balance' => round((float) $rows->sum('profit_balance'), 2),
                'entries_paid' => round((float) $rows->sum('entries_paid'), 2),
                'entries_pending' => round((float) $rows->sum('entries_pending'), 2),
            ],
        ];
    }

    private function profitEntriesBase(array $f, ?int $investorId = null): Builder
    {
        return DB::table('investor_profit_entries')
            ->whereBetween('investor_profit_entries.earned_at', ["{$f['from']} 00:00:00", "{$f['to']} 23:59:59"])
            ->when($investorId, fn ($q, $id) => $q->where('investor_profit_entries.investor_id', $id))
            ->when($f['branch_id'], fn ($q, $id) => $q
                ->join('sales', 'sales.id', '=', 'investor_profit_entries.sale_id')
                ->where('sales.branch_id', $id));
    }

    /**
     * Pivot of person × period, e.g. each investor's profit per month.
     *
     * @return array{periods: string[], rows: array<int, array{id: mixed, name: string, values: array<string, float>, total: float}>, totals: array<string, float>}
     */
    private function buildMatrix(Builder $query, string $keyColumn, string $labelColumn, string $valueExpr, string $periodExpr, string $fallbackLabel): array
    {
        $raw = $query
            ->selectRaw("{$keyColumn} as k, {$labelColumn} as label, {$periodExpr} as period, {$valueExpr} as value")
            ->groupBy('k', 'label', 'period')
            ->get();

        $periods = $raw->pluck('period')->map(fn ($p) => (string) $p)->unique()->sort()->values();

        $rows = $raw->groupBy(fn ($r) => (string) ($r->k ?? 'none'))
            ->map(function (Collection $group) use ($periods, $fallbackLabel) {
                $values = $periods->mapWithKeys(fn ($p) => [$p => 0.0])->all();
                foreach ($group as $r) {
                    $values[(string) $r->period] = round((float) $r->value, 2);
                }

                return [
                    'id' => $group->first()->k,
                    'name' => $group->first()->label ?? $fallbackLabel,
                    'values' => $values,
                    'total' => round(array_sum($values), 2),
                ];
            })
            ->sortByDesc('total')
            ->values();

        return [
            'periods' => $periods->all(),
            'rows' => $rows->all(),
            'totals' => $periods->mapWithKeys(fn ($p) => [$p => round((float) $rows->sum(fn ($r) => $r['values'][$p]), 2)])->all(),
        ];
    }

    /* ── Detail builders ───────────────────────────────────── */

    private function trendFor(Builder $base, string $groupBy): array
    {
        $expr = $this->periodExpr($groupBy, 'sales.sale_date');

        return (clone $base)
            ->selectRaw("{$expr} as period, ".self::ITEM_TOTALS)
            ->groupBy('period')
            ->orderByDesc('period')
            ->get()
            ->map(fn ($r) => $this->metrics($r, ['period' => (string) $r->period]))
            ->all();
    }

    private function productsFor(Builder $base, bool $withBatch): array
    {
        $groups = ['products.name', 'product_variants.volume_ml', 'product_variants.concentration'];
        if ($withBatch) {
            $groups[] = 'product_batches.batch_code';
        }

        return (clone $base)
            ->join('product_variants', 'sale_items.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->selectRaw(implode(', ', $groups).', '.self::ITEM_TOTALS)
            ->groupBy($groups)
            ->orderByDesc('sales')
            ->get()
            ->map(fn ($r) => $this->metrics($r, [
                'product_name' => trim(implode(' · ', array_filter([
                    $r->name,
                    $r->volume_ml ? "{$r->volume_ml}ml" : null,
                    $r->concentration ? strtoupper($r->concentration) : null,
                ]))),
                'batch_code' => $withBatch ? $r->batch_code : null,
            ]))
            ->all();
    }

    private function buildInvestorDetail(int $investorId, array $f): array
    {
        $investor = Investor::query()->notTrashed()->with('wallet')->findOrFail($investorId);
        $wallet = $investor->wallet;

        $base = $this->itemsBase($f)
            ->join('product_batches', 'sale_items.batch_id', '=', 'product_batches.id')
            ->where('product_batches.investor_id', $investorId);

        // Year-over-year view always covers the last 5 years up to the selected end date.
        $yearlyFilters = [...$f, 'from' => Carbon::parse($f['to'])->subYears(4)->startOfYear()->toDateString()];
        $yearlyBase = $this->itemsBase($yearlyFilters)
            ->join('product_batches', 'sale_items.batch_id', '=', 'product_batches.id')
            ->where('product_batches.investor_id', $investorId);

        $entries = $this->profitEntriesBase($f, $investorId)
            ->selectRaw('investor_profit_entries.status, COUNT(*) as cnt, SUM(investor_profit_entries.profit_amount_afn) as total')
            ->groupBy('investor_profit_entries.status')
            ->get()
            ->keyBy('status');

        $entry = fn (string $status) => [
            'count' => (int) ($entries->get($status)->cnt ?? 0),
            'total' => round((float) ($entries->get($status)->total ?? 0), 2),
        ];

        return [
            'investor' => [
                'id' => $investor->id,
                'full_name' => $investor->full_name,
                'phone' => $investor->phone,
                'email' => $investor->email,
                'status' => $investor->status,
            ],
            'capitalInfo' => [
                'total_invested' => (float) ($wallet?->total_invested_afn ?? 0),
                'capital_balance' => (float) ($wallet?->capital_balance_afn ?? 0),
                'profit_balance' => (float) ($wallet?->profit_balance_afn ?? 0),
                'total_profit' => (float) ($wallet?->total_profit_earned_afn ?? 0),
                'total_withdrawn' => (float) ($wallet?->total_withdrawn_afn ?? 0),
            ],
            'entries' => [
                'paid' => $entry('paid'),
                'pending' => $entry('pending'),
                'cancelled' => $entry('cancelled'),
            ],
            'purchases' => $this->productsFor($base, true),
            'dailyTrend' => $this->trendFor($base, 'daily'),
            'monthlyTrend' => $this->trendFor($base, 'monthly'),
            'yearlyTrend' => $this->trendFor($yearlyBase, 'yearly'),
            'totals' => $this->metrics((clone $base)->selectRaw(self::ITEM_TOTALS)->first() ?? (object) []),
        ];
    }

    private function buildStoreDetail(array $f): array
    {
        $base = $this->itemsBase($f)
            ->leftJoin('product_batches', 'sale_items.batch_id', '=', 'product_batches.id')
            ->whereNull('product_batches.investor_id');

        $yearlyFilters = [...$f, 'from' => Carbon::parse($f['to'])->subYears(4)->startOfYear()->toDateString()];
        $yearlyBase = $this->itemsBase($yearlyFilters)
            ->leftJoin('product_batches', 'sale_items.batch_id', '=', 'product_batches.id')
            ->whereNull('product_batches.investor_id');

        return [
            'purchases' => $this->productsFor($base, false),
            'dailyTrend' => $this->trendFor($base, 'daily'),
            'monthlyTrend' => $this->trendFor($base, 'monthly'),
            'yearlyTrend' => $this->trendFor($yearlyBase, 'yearly'),
            'totals' => $this->metrics((clone $base)->selectRaw(self::ITEM_TOTALS)->first() ?? (object) []),
            'totalExpenses' => round((float) $this->expensesBase($f)->sum('amount_afn'), 2),
        ];
    }

    /* ═══════════════════════════════════════════════════════
     |  PDF
     ═══════════════════════════════════════════════════════ */

    private function pdfMeta(array $f, string $title): array
    {
        $company = AppSetting::bill();

        return [
            'title' => $title,
            'company' => $company['name'],
            'companyInfo' => trim(implode(' · ', array_filter([$company['address'], $company['phone']]))),
            'filters' => $f,
            'generatedAt' => now()->format('Y-m-d H:i'),
            'generatedBy' => request()->user()?->name,
        ];
    }

    private function renderPdf(string $view, array $data, string $filename)
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper('A4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans');

        // CSS counter(pages) is unsupported by DomPDF, so the page total is stamped after layout.
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 98, $canvas->get_height() - 37, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7, [0.58, 0.64, 0.72]);

        return $pdf->download($filename);
    }
}
