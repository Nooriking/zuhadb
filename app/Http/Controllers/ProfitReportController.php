<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Investor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfitReportController extends Controller
{
    /* ═══════════════════════════════════════════════════════
     |  INDEX
     ═══════════════════════════════════════════════════════ */

    public function index(Request $request): Response
    {
        $from       = $request->query('from', now()->subDays(30)->toDateString());
        $to         = $request->query('to', now()->toDateString());
        $groupBy    = $request->query('group_by', 'daily');
        $investorId = $request->query('investor_id');

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $summary       = $this->buildSummary($from, $to, $investorId);
        $breakdown     = $groupBy === 'monthly'
            ? $this->buildMonthlyBreakdown($from, $to, $investorId)
            : $this->buildDailyBreakdown($from, $to, $investorId);
        $perInvestor   = $this->buildPerInvestorBreakdown($from, $to);
        $investorsData = $this->buildInvestorsSummary($from, $to);
        $exchangeRates = $this->buildExchangeRateBreakdown($from, $to);
        $expensesData  = $this->buildExpensesBreakdown($from, $to, $groupBy);

        return Inertia::render('reports/profit', [
            'summary'       => $summary,
            'breakdown'     => $breakdown,
            'perInvestor'   => $perInvestor,
            'investors'     => $investorsData['list'],
            'investorStats' => $investorsData['stats'],
            'exchangeRates' => $exchangeRates,
            'expenses'      => $expensesData,
            'filters'       => [
                'from'        => $from,
                'to'          => $to,
                'group_by'    => $groupBy,
                'investor_id' => $investorId,
            ],
        ]);
    }

    /* ═══════════════════════════════════════════════════════
     |  ✅ PDF EXPORT
     ═══════════════════════════════════════════════════════ */

    public function exportPdf(Request $request)
    {
        $from    = $request->query('from', now()->subDays(30)->toDateString());
        $to      = $request->query('to', now()->toDateString());
        $groupBy = $request->query('group_by', 'daily');

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $summary       = $this->buildSummary($from, $to, null);
        $breakdown     = $groupBy === 'monthly'
            ? $this->buildMonthlyBreakdown($from, $to, null)
            : $this->buildDailyBreakdown($from, $to, null);
        $perInvestor   = $this->buildPerInvestorBreakdown($from, $to);
        $investorsData = $this->buildInvestorsSummary($from, $to);
        $exchangeRates = $this->buildExchangeRateBreakdown($from, $to);
        $expensesData  = $this->buildExpensesBreakdown($from, $to, $groupBy);

        $latestRate = $exchangeRates[0] ?? null;

        // ✅ محاسبه‌ی expenses و net برای هر ردیف breakdown
        $breakdownWithExpenses = collect($breakdown)->map(function ($row) use ($expensesData, $groupBy) {
            $expensesForPeriod = 0;

            if ($groupBy === 'monthly') {
                foreach ($expensesData['daily'] as $e) {
                    if (str_starts_with($e['date'], $row['date'])) {
                        $expensesForPeriod += $e['total'];
                    }
                }
            } else {
                foreach ($expensesData['daily'] as $e) {
                    if ($e['date'] === $row['date']) {
                        $expensesForPeriod = $e['total'];
                        break;
                    }
                }
            }

            return array_merge($row, [
                'expenses' => $expensesForPeriod,
                'net'      => $row['total_profit'] - $expensesForPeriod,
            ]);
        })->all();

        $filename = "profit-report-{$from}-to-{$to}.pdf";

        $pdf = Pdf::loadView('reports.profit-pdf', [
            'from'          => $from,
            'to'            => $to,
            'groupBy'       => $groupBy,
            'summary'       => $summary,
            'breakdown'     => $breakdownWithExpenses,
            'perInvestor'   => $perInvestor,
            'investorStats' => $investorsData['stats'],
            'exchangeRates' => $exchangeRates,
            'expenses'      => $expensesData,
            'latestRate'    => $latestRate,
            'generatedAt'   => now()->format('Y-m-d H:i:s'),
        ]);

        $pdf->setPaper('A4', 'landscape');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($filename);
    }

    /* ═══════════════════════════════════════════════════════
     |  ✅ INVESTOR DETAIL
     ═══════════════════════════════════════════════════════ */

    public function investorDetail(Request $request, int $investorId): Response
    {
        $from    = $request->query('from', now()->subMonths(6)->toDateString());
        $to      = $request->query('to', now()->toDateString());
        $groupBy = $request->query('group_by', 'monthly');

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $investor = Investor::query()
            ->notTrashed()
            ->with('wallet')
            ->findOrFail($investorId);

        $wallet = $investor->wallet;

        $capitalInfo = [
            'total_invested'  => (float) ($wallet?->total_invested_afn ?? 0),
            'capital_balance' => (float) ($wallet?->capital_balance_afn ?? 0),
            'profit_balance'  => (float) ($wallet?->profit_balance_afn ?? 0),
            'total_profit'    => (float) ($wallet?->total_profit_earned_afn ?? 0),
            'total_withdrawn' => (float) ($wallet?->total_withdrawn_afn ?? 0),
        ];

        // Products breakdown
        $purchases = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_batches', 'sale_items.batch_id', '=', 'product_batches.id')
            ->join('product_variants', 'sale_items.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->where('product_batches.investor_id', $investorId)
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->whereDate('sales.sale_date', '>=', $from)
            ->whereDate('sales.sale_date', '<=', $to)
            ->selectRaw('
                products.name as product_name,
                product_variants.volume_ml,
                product_variants.concentration,
                product_batches.batch_code,
                COUNT(DISTINCT sale_items.sale_id) as orders,
                SUM(sale_items.quantity) as quantity,
                SUM(sale_items.line_total_afn) as sales,
                SUM(sale_items.cost_price_afn * sale_items.quantity) as cost,
                SUM(sale_items.investor_profit_afn) as investor_profit,
                SUM(sale_items.store_profit_afn) as store_profit
            ')
            ->groupBy('products.name', 'product_variants.volume_ml', 'product_variants.concentration', 'product_batches.batch_code')
            ->orderByDesc('investor_profit')
            ->get()
            ->map(fn ($row) => [
                'product_name'    => trim(implode(' · ', array_filter([
                    $row->product_name,
                    $row->volume_ml ? "{$row->volume_ml}ml" : null,
                    $row->concentration ? strtoupper($row->concentration) : null,
                ]))),
                'batch_code'      => $row->batch_code,
                'orders'          => (int) $row->orders,
                'quantity'        => (int) $row->quantity,
                'sales'           => round((float) $row->sales, 2),
                'cost'            => round((float) $row->cost, 2),
                'investor_profit' => round((float) $row->investor_profit, 2),
                'store_profit'    => round((float) $row->store_profit, 2),
            ])
            ->values()
            ->all();

        // Monthly trend
        $monthlyTrend = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_batches', 'sale_items.batch_id', '=', 'product_batches.id')
            ->where('product_batches.investor_id', $investorId)
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->whereDate('sales.sale_date', '>=', $from)
            ->whereDate('sales.sale_date', '<=', $to)
            ->selectRaw('
                DATE_FORMAT(sales.sale_date, "%Y-%m") as month,
                COUNT(DISTINCT sales.id) as orders,
                SUM(sale_items.quantity) as quantity,
                SUM(sale_items.line_total_afn) as sales,
                SUM(sale_items.investor_profit_afn) as investor_profit,
                SUM(sale_items.store_profit_afn) as store_profit
            ')
            ->groupBy('month')
            ->orderByDesc('month')
            ->get()
            ->map(fn ($row) => [
                'month'           => $row->month,
                'orders'          => (int) $row->orders,
                'quantity'        => (int) $row->quantity,
                'sales'           => round((float) $row->sales, 2),
                'investor_profit' => round((float) $row->investor_profit, 2),
                'store_profit'    => round((float) $row->store_profit, 2),
            ])
            ->values()
            ->all();

        // Daily trend
        $dailyTrend = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_batches', 'sale_items.batch_id', '=', 'product_batches.id')
            ->where('product_batches.investor_id', $investorId)
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->whereDate('sales.sale_date', '>=', $from)
            ->whereDate('sales.sale_date', '<=', $to)
            ->selectRaw('
                DATE(sales.sale_date) as date,
                COUNT(DISTINCT sales.id) as orders,
                SUM(sale_items.quantity) as quantity,
                SUM(sale_items.line_total_afn) as sales,
                SUM(sale_items.investor_profit_afn) as investor_profit,
                SUM(sale_items.store_profit_afn) as store_profit
            ')
            ->groupBy('date')
            ->orderByDesc('date')
            ->limit(60)
            ->get()
            ->map(fn ($row) => [
                'date'            => $row->date,
                'orders'          => (int) $row->orders,
                'quantity'        => (int) $row->quantity,
                'sales'           => round((float) $row->sales, 2),
                'investor_profit' => round((float) $row->investor_profit, 2),
                'store_profit'    => round((float) $row->store_profit, 2),
            ])
            ->values()
            ->all();

        // Yearly trend
        $yearlyTrend = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_batches', 'sale_items.batch_id', '=', 'product_batches.id')
            ->where('product_batches.investor_id', $investorId)
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->whereDate('sales.sale_date', '>=', now()->subYears(3)->startOfYear()->toDateString())
            ->whereDate('sales.sale_date', '<=', $to)
            ->selectRaw('
                YEAR(sales.sale_date) as year,
                COUNT(DISTINCT sales.id) as orders,
                SUM(sale_items.quantity) as quantity,
                SUM(sale_items.line_total_afn) as sales,
                SUM(sale_items.investor_profit_afn) as investor_profit,
                SUM(sale_items.store_profit_afn) as store_profit
            ')
            ->groupBy('year')
            ->orderByDesc('year')
            ->get()
            ->map(fn ($row) => [
                'year'            => (int) $row->year,
                'orders'          => (int) $row->orders,
                'quantity'        => (int) $row->quantity,
                'sales'           => round((float) $row->sales, 2),
                'investor_profit' => round((float) $row->investor_profit, 2),
                'store_profit'    => round((float) $row->store_profit, 2),
            ])
            ->values()
            ->all();

        // Totals
        $totalRow = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_batches', 'sale_items.batch_id', '=', 'product_batches.id')
            ->where('product_batches.investor_id', $investorId)
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->whereDate('sales.sale_date', '>=', $from)
            ->whereDate('sales.sale_date', '<=', $to)
            ->selectRaw('
                COUNT(DISTINCT sales.id) as orders,
                SUM(sale_items.quantity) as quantity,
                SUM(sale_items.line_total_afn) as sales,
                SUM(sale_items.investor_profit_afn) as investor_profit,
                SUM(sale_items.store_profit_afn) as store_profit
            ')
            ->first();

        $totals = [
            'orders'          => (int) ($totalRow->orders ?? 0),
            'quantity'        => (int) ($totalRow->quantity ?? 0),
            'sales'           => round((float) ($totalRow->sales ?? 0), 2),
            'investor_profit' => round((float) ($totalRow->investor_profit ?? 0), 2),
            'store_profit'    => round((float) ($totalRow->store_profit ?? 0), 2),
        ];

        return Inertia::render('reports/investor-detail', [
            'investor'     => [
                'id'        => $investor->id,
                'full_name' => $investor->full_name,
                'phone'     => $investor->phone,
                'email'     => $investor->email,
                'status'    => $investor->status,
            ],
            'capitalInfo'  => $capitalInfo,
            'purchases'    => $purchases,
            'monthlyTrend' => $monthlyTrend,
            'dailyTrend'   => $dailyTrend,
            'yearlyTrend'  => $yearlyTrend,
            'totals'       => $totals,
            'filters'      => [
                'from'     => $from,
                'to'       => $to,
                'group_by' => $groupBy,
            ],
        ]);
    }

    /* ═══════════════════════════════════════════════════════
     |  ✅ STORE DETAIL
     ═══════════════════════════════════════════════════════ */

    public function storeDetail(Request $request): Response
    {
        $from    = $request->query('from', now()->subMonths(6)->toDateString());
        $to      = $request->query('to', now()->toDateString());
        $groupBy = $request->query('group_by', 'monthly');

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $purchases = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_batches', 'sale_items.batch_id', '=', 'product_batches.id')
            ->join('product_variants', 'sale_items.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->whereNull('product_batches.investor_id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->whereDate('sales.sale_date', '>=', $from)
            ->whereDate('sales.sale_date', '<=', $to)
            ->selectRaw('
                products.name as product_name,
                product_variants.volume_ml,
                product_variants.concentration,
                COUNT(DISTINCT sale_items.sale_id) as orders,
                SUM(sale_items.quantity) as quantity,
                SUM(sale_items.line_total_afn) as sales,
                SUM(sale_items.cost_price_afn * sale_items.quantity) as cost,
                SUM(sale_items.store_profit_afn) as store_profit
            ')
            ->groupBy('products.name', 'product_variants.volume_ml', 'product_variants.concentration')
            ->orderByDesc('store_profit')
            ->get()
            ->map(fn ($row) => [
                'product_name' => trim(implode(' · ', array_filter([
                    $row->product_name,
                    $row->volume_ml ? "{$row->volume_ml}ml" : null,
                    $row->concentration ? strtoupper($row->concentration) : null,
                ]))),
                'orders'       => (int) $row->orders,
                'quantity'     => (int) $row->quantity,
                'sales'        => round((float) $row->sales, 2),
                'cost'         => round((float) $row->cost, 2),
                'store_profit' => round((float) $row->store_profit, 2),
            ])
            ->values()
            ->all();

        $monthlyTrend = $this->storeTrendBy('month', $from, $to);
        $dailyTrend   = $this->storeTrendBy('date', $from, $to);
        $yearlyTrend  = $this->storeTrendBy('year', $from, $to);

        $totalRow = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_batches', 'sale_items.batch_id', '=', 'product_batches.id')
            ->whereNull('product_batches.investor_id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->whereDate('sales.sale_date', '>=', $from)
            ->whereDate('sales.sale_date', '<=', $to)
            ->selectRaw('
                COUNT(DISTINCT sales.id) as orders,
                SUM(sale_items.quantity) as quantity,
                SUM(sale_items.line_total_afn) as sales,
                SUM(sale_items.store_profit_afn) as store_profit
            ')
            ->first();

        $totals = [
            'orders'       => (int) ($totalRow->orders ?? 0),
            'quantity'     => (int) ($totalRow->quantity ?? 0),
            'sales'        => round((float) ($totalRow->sales ?? 0), 2),
            'store_profit' => round((float) ($totalRow->store_profit ?? 0), 2),
        ];

        $totalExpenses = (float) Expense::query()
            ->notTrashed()
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->sum('amount_afn');

        return Inertia::render('reports/store-detail', [
            'purchases'     => $purchases,
            'monthlyTrend'  => $monthlyTrend,
            'dailyTrend'    => $dailyTrend,
            'yearlyTrend'   => $yearlyTrend,
            'totals'        => $totals,
            'totalExpenses' => round($totalExpenses, 2),
            'filters'       => [
                'from'     => $from,
                'to'       => $to,
                'group_by' => $groupBy,
            ],
        ]);
    }

    private function storeTrendBy(string $period, string $from, string $to): array
    {
        $select = match ($period) {
            'year'  => 'YEAR(sales.sale_date) as period_key',
            'date'  => 'DATE(sales.sale_date) as period_key',
            default => 'DATE_FORMAT(sales.sale_date, "%Y-%m") as period_key',
        };

        $rows = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_batches', 'sale_items.batch_id', '=', 'product_batches.id')
            ->whereNull('product_batches.investor_id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->whereDate('sales.sale_date', '>=', $from)
            ->whereDate('sales.sale_date', '<=', $to)
            ->selectRaw("
                {$select},
                COUNT(DISTINCT sales.id) as orders,
                SUM(sale_items.quantity) as quantity,
                SUM(sale_items.line_total_afn) as sales,
                SUM(sale_items.store_profit_afn) as store_profit
            ")
            ->groupBy('period_key')
            ->orderByDesc('period_key')
            ->limit(60)
            ->get();

        return collect($rows)->map(fn ($row) => [
            'period'       => (string) $row->period_key,
            'orders'       => (int) $row->orders,
            'quantity'     => (int) $row->quantity,
            'sales'        => round((float) $row->sales, 2),
            'store_profit' => round((float) $row->store_profit, 2),
        ])->values()->all();
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
        return $this->exportCsv($request, 'per-investor');
    }

    public function exportExchangeRates(Request $request): StreamedResponse
    {
        return $this->exportCsv($request, 'exchange-rates');
    }

    private function exportCsv(Request $request, string $type): StreamedResponse
    {
        $from    = $request->query('from', now()->subDays(30)->toDateString());
        $to      = $request->query('to', now()->toDateString());
        $groupBy = $request->query('group_by', 'daily');

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $summary       = $this->buildSummary($from, $to, null);
        $breakdown     = $groupBy === 'monthly'
            ? $this->buildMonthlyBreakdown($from, $to, null)
            : $this->buildDailyBreakdown($from, $to, null);
        $perInvestor   = $this->buildPerInvestorBreakdown($from, $to);
        $investorsData = $this->buildInvestorsSummary($from, $to);
        $exchangeRates = $this->buildExchangeRateBreakdown($from, $to);
        $expensesData  = $this->buildExpensesBreakdown($from, $to, $groupBy);

        $filename = "profit-report-{$type}-{$from}-to-{$to}.csv";

        return response()->streamDownload(function () use (
            $summary, $breakdown, $perInvestor, $investorsData,
            $exchangeRates, $expensesData, $from, $to, $groupBy, $type
        ) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($out, ['PROFIT REPORT']);
            fputcsv($out, ['From', $from]);
            fputcsv($out, ['To', $to]);
            fputcsv($out, ['Type', ucfirst($type)]);
            fputcsv($out, ['Generated At', now()->format('Y-m-d H:i:s')]);
            fputcsv($out, []);

            fputcsv($out, ['═══════════════════════════════════']);
            fputcsv($out, ['SUMMARY']);
            fputcsv($out, ['═══════════════════════════════════']);
            fputcsv($out, ['Metric', 'Amount']);
            fputcsv($out, ['Total Sales (AFN)', number_format($summary['total_sales'], 2, '.', '')]);
            fputcsv($out, ['Total Cost (AFN)', number_format($summary['total_cost'], 2, '.', '')]);
            fputcsv($out, ['Gross Profit (AFN)', number_format($summary['total_profit'], 2, '.', '')]);
            fputcsv($out, ['  Investor Profit (AFN)', number_format($summary['investor_profit'], 2, '.', '')]);
            fputcsv($out, ['  Store Profit (AFN)', number_format($summary['store_profit'], 2, '.', '')]);
            fputcsv($out, ['Total Expenses (AFN)', number_format($summary['total_expenses'], 2, '.', '')]);
            fputcsv($out, ['Net Profit (AFN)', number_format($summary['net_profit'], 2, '.', '')]);
            fputcsv($out, ['Total Quantity Sold', $summary['total_quantity']]);
            fputcsv($out, ['Total Orders', $summary['total_orders']]);
            fputcsv($out, []);
            fputcsv($out, ['INVESTOR STATS']);
            fputcsv($out, ['Total Investors', $investorsData['stats']['total_investors']]);
            fputcsv($out, ['Active Investors', $investorsData['stats']['active_investors']]);
            fputcsv($out, []);

            if ($type === 'full' || $type === 'breakdown') {
                fputcsv($out, ['═══════════════════════════════════']);
                fputcsv($out, [strtoupper($groupBy) . ' BREAKDOWN']);
                fputcsv($out, ['═══════════════════════════════════']);
                fputcsv($out, [
                    $groupBy === 'monthly' ? 'Month' : 'Date',
                    'Orders',
                    'Quantity',
                    'Sales (AFN)',
                    'Cost (AFN)',
                    'Investor Profit (AFN)',
                    'Store Profit (AFN)',
                    'Gross Profit (AFN)',
                ]);
                foreach ($breakdown as $row) {
                    fputcsv($out, [
                        $row['date'],
                        $row['orders'],
                        $row['quantity'],
                        number_format($row['sales'], 2, '.', ''),
                        number_format($row['cost'], 2, '.', ''),
                        number_format($row['investor_profit'], 2, '.', ''),
                        number_format($row['store_profit'], 2, '.', ''),
                        number_format($row['total_profit'], 2, '.', ''),
                    ]);
                }
                fputcsv($out, []);
            }

            if ($type === 'full' || $type === 'per-investor') {
                fputcsv($out, ['═══════════════════════════════════']);
                fputcsv($out, ['PER-INVESTOR BREAKDOWN']);
                fputcsv($out, ['═══════════════════════════════════']);
                fputcsv($out, ['#', 'Investor', 'Orders', 'Quantity', 'Sales (AFN)', 'Investor Profit (AFN)', 'Store Profit (AFN)']);
                foreach ($perInvestor as $i => $row) {
                    fputcsv($out, [
                        $i + 1,
                        $row['investor_name'],
                        $row['orders'],
                        $row['quantity'],
                        number_format($row['sales'], 2, '.', ''),
                        number_format($row['investor_profit'], 2, '.', ''),
                        number_format($row['store_profit'], 2, '.', ''),
                    ]);
                }
                fputcsv($out, []);
            }

            if ($type === 'full' || $type === 'summary') {
                fputcsv($out, ['═══════════════════════════════════']);
                fputcsv($out, ['EXPENSES BY CATEGORY']);
                fputcsv($out, ['═══════════════════════════════════']);
                fputcsv($out, ['Category', 'Count', 'Total (AFN)']);
                foreach ($expensesData['by_category'] as $row) {
                    fputcsv($out, [
                        $row['category'],
                        $row['count'],
                        number_format($row['total'], 2, '.', ''),
                    ]);
                }
                fputcsv($out, []);
            }

            if ($type === 'full' || $type === 'exchange-rates') {
                fputcsv($out, ['═══════════════════════════════════']);
                fputcsv($out, ['EXCHANGE RATES BY MONTH']);
                fputcsv($out, ['═══════════════════════════════════']);
                fputcsv($out, ['Month', 'Avg Rate (AFN/USD)', 'Min', 'Max', 'Days Recorded']);
                foreach ($exchangeRates as $row) {
                    fputcsv($out, [
                        $row['month'],
                        number_format($row['avg_rate'], 2, '.', ''),
                        number_format($row['min_rate'], 2, '.', ''),
                        number_format($row['max_rate'], 2, '.', ''),
                        $row['days'],
                    ]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /* ═══════════════════════════════════════════════════════
     |  PRIVATE HELPERS
     ═══════════════════════════════════════════════════════ */

    private function buildSummary(string $from, string $to, ?int $investorId): array
    {
        $row = DB::table('sales')
            ->where('trash', false)
            ->whereNull('deleted_at')
            ->where('status', 'completed')
            ->whereDate('sale_date', '>=', $from)
            ->whereDate('sale_date', '<=', $to)
            ->selectRaw('
                COALESCE(SUM(total_afn), 0) as total_sales,
                COALESCE(SUM(total_cost_afn), 0) as total_cost,
                COALESCE(SUM(total_profit_afn), 0) as total_profit,
                COALESCE(SUM(total_investor_profit_afn), 0) as investor_profit,
                COALESCE(SUM(store_profit_afn), 0) as store_profit,
                COUNT(*) as total_orders
            ')
            ->first();

        $totalQuantity = (int) DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->whereDate('sales.sale_date', '>=', $from)
            ->whereDate('sales.sale_date', '<=', $to)
            ->sum('sale_items.quantity');

        $totalExpenses = (float) Expense::query()
            ->notTrashed()
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->sum('amount_afn');

        return [
            'from'            => $from,
            'to'              => $to,
            'total_sales'     => round((float) $row->total_sales, 2),
            'total_cost'      => round((float) $row->total_cost, 2),
            'total_profit'    => round((float) $row->total_profit, 2),
            'store_profit'    => round((float) $row->store_profit, 2),
            'investor_profit' => round((float) $row->investor_profit, 2),
            'total_expenses'  => round($totalExpenses, 2),
            'net_profit'      => round((float) $row->total_profit - $totalExpenses, 2),
            'total_quantity'  => $totalQuantity,
            'total_orders'    => (int) $row->total_orders,
        ];
    }

    private function buildDailyBreakdown(string $from, string $to, ?int $investorId): array
    {
        $rows = DB::table('sales')
            ->where('trash', false)
            ->whereNull('deleted_at')
            ->where('status', 'completed')
            ->whereDate('sale_date', '>=', $from)
            ->whereDate('sale_date', '<=', $to)
            ->selectRaw('
                DATE(sale_date) as date,
                COUNT(*) as orders,
                SUM(total_afn) as sales,
                SUM(total_cost_afn) as cost,
                SUM(store_profit_afn) as store_profit,
                SUM(total_investor_profit_afn) as investor_profit
            ')
            ->groupBy('date')
            ->orderByDesc('date')
            ->get();

        $qtys = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->whereDate('sales.sale_date', '>=', $from)
            ->whereDate('sales.sale_date', '<=', $to)
            ->selectRaw('DATE(sales.sale_date) as date, SUM(sale_items.quantity) as qty')
            ->groupBy('date')
            ->pluck('qty', 'date');

        return collect($rows)->map(function ($row) use ($qtys) {
            $qty = (int) ($qtys->get($row->date) ?? 0);
            return [
                'date'            => $row->date,
                'orders'          => (int) $row->orders,
                'quantity'        => $qty,
                'sales'           => round((float) $row->sales, 2),
                'cost'            => round((float) $row->cost, 2),
                'store_profit'    => round((float) $row->store_profit, 2),
                'investor_profit' => round((float) $row->investor_profit, 2),
                'total_profit'    => round((float) $row->store_profit + (float) $row->investor_profit, 2),
            ];
        })->values()->all();
    }

    private function buildMonthlyBreakdown(string $from, string $to, ?int $investorId): array
    {
        $rows = DB::table('sales')
            ->where('trash', false)
            ->whereNull('deleted_at')
            ->where('status', 'completed')
            ->whereDate('sale_date', '>=', $from)
            ->whereDate('sale_date', '<=', $to)
            ->selectRaw('
                DATE_FORMAT(sale_date, "%Y-%m") as month,
                COUNT(*) as orders,
                SUM(total_afn) as sales,
                SUM(total_cost_afn) as cost,
                SUM(store_profit_afn) as store_profit,
                SUM(total_investor_profit_afn) as investor_profit
            ')
            ->groupBy('month')
            ->orderByDesc('month')
            ->get();

        $qtys = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->whereDate('sales.sale_date', '>=', $from)
            ->whereDate('sales.sale_date', '<=', $to)
            ->selectRaw('DATE_FORMAT(sales.sale_date, "%Y-%m") as month, SUM(sale_items.quantity) as qty')
            ->groupBy('month')
            ->pluck('qty', 'month');

        return collect($rows)->map(function ($row) use ($qtys) {
            $qty = (int) ($qtys->get($row->month) ?? 0);
            return [
                'date'            => $row->month,
                'orders'          => (int) $row->orders,
                'quantity'        => $qty,
                'sales'           => round((float) $row->sales, 2),
                'cost'            => round((float) $row->cost, 2),
                'store_profit'    => round((float) $row->store_profit, 2),
                'investor_profit' => round((float) $row->investor_profit, 2),
                'total_profit'    => round((float) $row->store_profit + (float) $row->investor_profit, 2),
            ];
        })->values()->all();
    }

    private function buildPerInvestorBreakdown(string $from, string $to): array
    {
        $rows = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_batches', 'sale_items.batch_id', '=', 'product_batches.id')
            ->leftJoin('investors', 'product_batches.investor_id', '=', 'investors.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->whereDate('sales.sale_date', '>=', $from)
            ->whereDate('sales.sale_date', '<=', $to)
            ->selectRaw('
                product_batches.investor_id as investor_id,
                investors.full_name as investor_name,
                COUNT(DISTINCT sales.id) as orders,
                SUM(sale_items.quantity) as quantity,
                SUM(sale_items.line_total_afn) as sales,
                SUM(sale_items.investor_profit_afn) as investor_profit,
                SUM(sale_items.store_profit_afn) as store_profit
            ')
            ->groupBy('product_batches.investor_id', 'investors.full_name')
            ->orderByDesc('investor_profit')
            ->get();

        return collect($rows)->map(fn ($row) => [
            'investor_id'     => $row->investor_id,
            'investor_name'   => $row->investor_name ?? 'Store (no investor)',
            'orders'          => (int) $row->orders,
            'quantity'        => (int) $row->quantity,
            'sales'           => round((float) $row->sales, 2),
            'investor_profit' => round((float) $row->investor_profit, 2),
            'store_profit'    => round((float) $row->store_profit, 2),
        ])->values()->all();
    }

    private function buildInvestorsSummary(string $from, string $to): array
    {
        $totalInvestors = Investor::query()->notTrashed()->count();

        $activeInvestorIds = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_batches', 'sale_items.batch_id', '=', 'product_batches.id')
            ->where('sales.trash', false)
            ->whereNull('sales.deleted_at')
            ->where('sales.status', 'completed')
            ->whereDate('sales.sale_date', '>=', $from)
            ->whereDate('sales.sale_date', '<=', $to)
            ->whereNotNull('product_batches.investor_id')
            ->distinct()
            ->pluck('product_batches.investor_id');

        $list = Investor::query()
            ->notTrashed()
            ->select('id', 'full_name')
            ->orderBy('full_name')
            ->get()
            ->map(fn ($inv) => ['id' => $inv->id, 'full_name' => $inv->full_name])
            ->values()
            ->all();

        return [
            'stats' => [
                'total_investors'  => $totalInvestors,
                'active_investors' => $activeInvestorIds->count(),
                'period'           => ['from' => $from, 'to' => $to],
            ],
            'list' => $list,
        ];
    }

    private function buildExchangeRateBreakdown(string $from, string $to): array
    {
        $rows = DB::table('exchange_rate_history')
            ->whereDate('rate_date', '>=', $from)
            ->whereDate('rate_date', '<=', $to)
            ->selectRaw('
                DATE_FORMAT(rate_date, "%Y-%m") as month,
                AVG(usd_to_afn) as avg_rate,
                MIN(usd_to_afn) as min_rate,
                MAX(usd_to_afn) as max_rate,
                COUNT(*) as days
            ')
            ->groupBy('month')
            ->orderByDesc('month')
            ->get();

        return collect($rows)->map(fn ($row) => [
            'month'    => $row->month,
            'avg_rate' => round((float) $row->avg_rate, 2),
            'min_rate' => round((float) $row->min_rate, 2),
            'max_rate' => round((float) $row->max_rate, 2),
            'days'     => (int) $row->days,
        ])->values()->all();
    }

    private function buildExpensesBreakdown(string $from, string $to, string $groupBy): array
    {
        $byCategory = Expense::query()
            ->notTrashed()
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->select('category', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount_afn) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'category' => ucfirst(str_replace('_', ' ', $row->category ?? 'other')),
                'count'    => (int) $row->count,
                'total'    => round((float) $row->total, 2),
            ])
            ->values()
            ->all();

        $daily = Expense::query()
            ->notTrashed()
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->select(DB::raw('DATE(expense_date) as date'), DB::raw('SUM(amount_afn) as total'))
            ->groupBy('date')
            ->orderByDesc('date')
            ->get()
            ->map(fn ($row) => ['date' => $row->date, 'total' => round((float) $row->total, 2)])
            ->values()
            ->all();

        $monthly = Expense::query()
            ->notTrashed()
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->select(DB::raw('DATE_FORMAT(expense_date, "%Y-%m") as month'), DB::raw('SUM(amount_afn) as total'))
            ->groupBy('month')
            ->orderByDesc('month')
            ->get()
            ->map(fn ($row) => ['month' => $row->month, 'total' => round((float) $row->total, 2)])
            ->values()
            ->all();

        $total = round((float) Expense::query()
            ->notTrashed()
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->sum('amount_afn'), 2);

        return [
            'total'       => $total,
            'by_category' => $byCategory,
            'daily'       => $daily,
            'monthly'     => $monthly,
        ];
    }

    private function expensesForDate(array $dailyExpenses, string $date, string $groupBy): float
    {
        if ($groupBy === 'monthly') {
            $sum = 0;
            foreach ($dailyExpenses as $e) {
                if (str_starts_with($e['date'], $date)) {
                    $sum += $e['total'];
                }
            }
            return $sum;
        }
        foreach ($dailyExpenses as $e) {
            if ($e['date'] === $date) {
                return $e['total'];
            }
        }
        return 0;
    }
}