<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use App\Models\InvestmentProductAllocation;
use App\Models\Investor;
use App\Models\ProductBatch;
use App\Models\InvestorProfitEntry;
use App\Models\InvestorTransaction;
use App\Models\InvestorWithdrawal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvestorDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $days = (int) $request->query('days', 30);
        if ($days < 1 || $days > 365) {
            $days = 30;
        }

        $startDate = now()->subDays($days)->startOfDay();

        $own = (bool) $request->user()?->isInvestor();
        $onlyId = $own
            ? (int) (Investor::query()->where('user_id', $request->user()->id)->where('trash', false)->value('id') ?? 0)
            : null;

        /* =========================================================
         |  TOP-LEVEL KPIs
         ========================================================= */

        // کل سرمایه (Investment های غیر حذف‌شده)
        $totalCapital = (float) Investment::notTrashed()
            ->when($own, fn ($q) => $q->where('investor_id', $onlyId))
            ->whereIn('status', ['active', 'partially_used', 'fully_used'])
            ->sum('capital_amount_afn');

        // سرمایه در دسترس (آماده تخصیص)
        $availableCapital = (float) Investment::notTrashed()
            ->when($own, fn ($q) => $q->where('investor_id', $onlyId))
            ->whereIn('status', ['active', 'partially_used'])
            ->sum('available_amount_afn');

        // سرمایه تخصیص‌یافته (Invested)
        $investedCapital = (float) Investment::notTrashed()
            ->when($own, fn ($q) => $q->where('investor_id', $onlyId))
            ->sum('allocated_amount_afn');

        // سود کل (pending + paid)
        $totalProfit = (float) InvestorProfitEntry::whereIn('status', ['pending', 'paid'])
            ->when($own, fn ($q) => $q->where('investor_id', $onlyId))
            ->sum('profit_amount_afn');

        // سود در انتظار پرداخت
        $pendingProfit = (float) InvestorProfitEntry::where('status', 'pending')
            ->when($own, fn ($q) => $q->where('investor_id', $onlyId))
            ->sum('profit_amount_afn');

        // سود پرداخت‌شده
        $paidProfit = (float) InvestorProfitEntry::where('status', 'paid')
            ->when($own, fn ($q) => $q->where('investor_id', $onlyId))
            ->sum('profit_amount_afn');

        // سود برداشت‌شده
        $withdrawnProfit = (float) InvestorWithdrawal::query()
            ->when($own, fn ($q) => $q->where('investor_id', $onlyId))
            ->where('type', 'profit')
            ->whereIn('status', ['approved', 'paid'])
            ->sum('amount_afn');

        // سرمایه برداشت‌شده
        $withdrawnCapital = (float) InvestorWithdrawal::query()
            ->when($own, fn ($q) => $q->where('investor_id', $onlyId))
            ->where('type', 'capital')
            ->whereIn('status', ['approved', 'paid'])
            ->sum('amount_afn');

        $kpis = [
            'total_capital'       => $totalCapital,
            'available_capital'   => $availableCapital,
            'invested_capital'    => $investedCapital,
            'total_profit'        => $totalProfit,
            'pending_profit'      => $pendingProfit,
            'paid_profit'         => $paidProfit,
            'withdrawn_profit'    => $withdrawnProfit,
            'withdrawn_capital'   => $withdrawnCapital,
            'remaining_profit'    => max(0, $totalProfit - $withdrawnProfit),
            'capital_utilization' => $totalCapital > 0
                ? round(($investedCapital / $totalCapital) * 100, 1)
                : 0,
            'profit_margin'       => $totalCapital > 0
                ? round(($totalProfit / $totalCapital) * 100, 1)
                : 0,
            'stock_value_afn'     => 0,
        ];

        $purchases = $own
            ? \App\Models\Product::query()
                ->where('trash', false)
                ->whereNotNull('variant_id')
                ->where('investor_id', $onlyId)
                ->orderByDesc('id')
                ->get(['id', 'name', 'quantity', 'remaining_quantity', 'line_total', 'exchange_rate', 'profit_per_unit'])
            : collect();

        $stockItems = $purchases->map(function ($row) {
            $rate = (float) ($row->exchange_rate ?? 0);
            $costAfn = (float) $row->line_total;
            $qty = (int) $row->quantity;
            $usd = $rate > 0 ? round($costAfn / $rate, 2) : 0;
            $profitUsd = $rate > 0 ? round(((float) $row->profit_per_unit * $qty) / $rate, 2) : 0;

            return [
                'name' => $row->name ?: 'Product',
                'quantity' => $qty,
                'remaining' => (int) $row->remaining_quantity,
                'rate' => $rate,
                'value_usd' => $usd,
                'profit_usd' => $profitUsd,
            ];
        })->values();

        $spentUsd = round($stockItems->sum('value_usd'), 2);
        $kpis['stock_value_usd'] = $spentUsd;

        if ($own) {
            $kpis['invested_capital'] = $spentUsd;
            $kpis['available_capital'] = max(0, round($totalCapital - $spentUsd, 2));
            $kpis['capital_utilization'] = $totalCapital > 0
                ? round(($spentUsd / $totalCapital) * 100, 1)
                : 0;
        }

        /* =========================================================
         |  CHART: Capital Trend (آخرین N روز)
         ========================================================= */

        $capitalTrend = Investment::notTrashed()
            ->when($own, fn ($q) => $q->where('investor_id', $onlyId))
            ->select(
                DB::raw('DATE(investment_date) as date'),
                DB::raw('SUM(capital_amount_afn) as capital'),
                DB::raw('SUM(allocated_amount_afn) as allocated'),
                DB::raw('SUM(profit_amount_afn) as profit'),
                DB::raw('COUNT(*) as count')
            )
            ->where('investment_date', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date'      => Carbon::parse($row->date)->format('M d'),
                'capital'   => (float) $row->capital,
                'allocated' => (float) $row->allocated,
                'profit'    => (float) $row->profit,
                'count'     => (int) $row->count,
            ]);

        /* =========================================================
         |  CHART: Profit Distribution (per Investor - top 8)
         ========================================================= */

        $profitByInvestor = InvestorProfitEntry::query()
            ->when($own, fn ($q) => $q->where('investor_profit_entries.investor_id', $onlyId))
            ->join('investors', 'investor_profit_entries.investor_id', '=', 'investors.id')
            ->whereIn('investor_profit_entries.status', ['pending', 'paid'])
            ->where('investor_profit_entries.earned_at', '>=', $startDate)
            ->select(
                'investors.id',
                'investors.full_name',
                DB::raw('SUM(investor_profit_entries.profit_amount_afn) as total')
            )
            ->groupBy('investors.id', 'investors.full_name')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'name'  => $row->full_name,
                'value' => (float) $row->total,
            ]);

        /* =========================================================
         |  CHART: Withdrawal Breakdown
         ========================================================= */

        $withdrawalBreakdown = [
            ['name' => 'Profit Withdrawn',  'value' => $withdrawnProfit],
            ['name' => 'Capital Withdrawn', 'value' => $withdrawnCapital],
        ];

        /* =========================================================
         |  CHART: Status Distribution of Investments
         ========================================================= */

        $investmentStatus = Investment::notTrashed()
            ->when($own, fn ($q) => $q->where('investor_id', $onlyId))
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(capital_amount_afn) as total'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'name'  => Investment::STATUS_LABELS[$row->status] ?? $row->status,
                'value' => (float) $row->total,
                'count' => (int) $row->count,
            ]);

        /* =========================================================
         |  COUNTERS
         ========================================================= */

        $counters = [
            'total_investors'     => Investor::notTrashed()->when($own, fn ($q) => $q->where('id', $onlyId))->count(),
            'active_investors'    => Investor::notTrashed()->active()->when($own, fn ($q) => $q->where('id', $onlyId))->count(),
            'total_investments'   => Investment::notTrashed()->when($own, fn ($q) => $q->where('investor_id', $onlyId))->count(),
            'active_investments'  => Investment::notTrashed()->active()->when($own, fn ($q) => $q->where('investor_id', $onlyId))->count(),
            'total_allocations'   => InvestmentProductAllocation::query()->when($own, fn ($q) => $q->whereHas('investment', fn ($i) => $i->where('investor_id', $onlyId)))->count(),
            'total_funded_qty'    => (int) InvestmentProductAllocation::query()->when($own, fn ($q) => $q->whereHas('investment', fn ($i) => $i->where('investor_id', $onlyId)))->sum('funded_quantity'),
            'total_sold_qty'      => (int) InvestmentProductAllocation::query()->when($own, fn ($q) => $q->whereHas('investment', fn ($i) => $i->where('investor_id', $onlyId)))->sum('sold_quantity'),
            'total_remaining_qty' => (int) InvestmentProductAllocation::query()->when($own, fn ($q) => $q->whereHas('investment', fn ($i) => $i->where('investor_id', $onlyId)))->sum('remaining_quantity'),
            'pending_withdrawals' => InvestorWithdrawal::pending()->when($own, fn ($q) => $q->where('investor_id', $onlyId))->count(),
            'pending_profit_rows' => InvestorProfitEntry::pending()->when($own, fn ($q) => $q->where('investor_id', $onlyId))->count(),
        ];

        /* =========================================================
         |  RECENT INVESTMENTS
         ========================================================= */

        $recentInvestments = Investment::query()
            ->with(['investor:id,full_name,phone'])
            ->notTrashed()
            ->when($own, fn ($q) => $q->where('investor_id', $onlyId))
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn ($inv) => [
                'id'                 => $inv->id,
                'investment_number'  => $inv->investment_number,
                'investor'           => $inv->investor?->full_name,
                'investor_phone'     => $inv->investor?->phone,
                'capital_amount_afn' => (float) $inv->capital_amount_afn,
                'available_afn'      => (float) $inv->available_amount_afn,
                'allocated_afn'      => (float) $inv->allocated_amount_afn,
                'profit_afn'         => (float) $inv->profit_amount_afn,
                'progress_percent'   => $inv->progress_percent,
                'status'             => $inv->status,
                'status_label'       => Investment::STATUS_LABELS[$inv->status] ?? $inv->status,
                'investment_date'    => optional($inv->investment_date)->toDateString(),
                'currency'           => 'USD',
            ]);

        /* =========================================================
         |  RECENT PROFIT ENTRIES
         ========================================================= */

        $recentProfitEntries = InvestorProfitEntry::query()
            ->when($own, fn ($q) => $q->where('investor_id', $onlyId))
            ->with([
                'investor:id,full_name',
                'sale:id,invoice_number,total_afn',
            ])
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn ($e) => [
                'id'                 => $e->id,
                'investor'           => $e->investor?->full_name,
                'sale_invoice'       => $e->sale?->invoice_number,
                'quantity'           => $e->quantity,
                'profit_amount_afn'  => (float) $e->profit_amount_afn,
                'profit_amount_curr' => (float) $e->profit_amount_afn,
                'currency'           => 'USD',
                'entry_type'         => $e->entry_type,
                'entry_type_label'   => $e->entry_type_label,
                'status'             => $e->status,
                'status_label'       => $e->status_label,
                'earned_at'          => optional($e->earned_at)->toDateTimeString(),
            ]);

        /* =========================================================
         |  RECENT WITHDRAWALS
         ========================================================= */

        $recentWithdrawals = InvestorWithdrawal::query()
            ->when($own, fn ($q) => $q->where('investor_id', $onlyId))
            ->with(['investor:id,full_name'])
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn ($w) => [
                'id'                => $w->id,
                'withdrawal_number' => $w->withdrawal_number,
                'investor'          => $w->investor?->full_name,
                'type'              => $w->type,
                'amount_afn'        => (float) $w->amount_afn,
                'amount_currency'   => (float) $w->amount_afn,
                'currency'          => 'USD',
                'status'            => $w->status,
                'withdrawal_date'   => optional($w->withdrawal_date)->toDateString(),
            ]);

        /* =========================================================
         |  TOP INVESTORS  (بر اساس مجموع سرمایه در investments)
         ========================================================= */

        $topInvestors = Investor::notTrashed()
            ->when($own, fn ($q) => $q->where('investors.id', $onlyId))
            ->select('investors.*')
            ->selectSub(
                Investment::selectRaw('COALESCE(SUM(capital_amount_afn), 0)')
                    ->whereColumn('investments.investor_id', 'investors.id')
                    ->where('investments.trash', false),
                'total_capital_afn'
            )
            ->selectSub(
                Investment::selectRaw('COALESCE(SUM(allocated_amount_afn), 0)')
                    ->whereColumn('investments.investor_id', 'investors.id')
                    ->where('investments.trash', false),
                'total_allocated_afn'
            )
            ->with(['wallet'])
            ->withCount(['investments'])
            ->orderByDesc('total_capital_afn')
            ->limit(10)
            ->get()
            ->map(fn ($inv) => [
                'id'                => $inv->id,
                'full_name'         => $inv->full_name,
                'phone'             => $inv->phone,
                'status'            => $inv->status,
                'investments_count' => $inv->investments_count,
                'total_capital_afn' => (float) $inv->total_capital_afn,
                'total_allocated'   => (float) $inv->total_allocated_afn,
                'capital_balance'   => (float) ($inv->wallet?->capital_balance_afn ?? 0),
                'profit_balance'    => (float) ($inv->wallet?->profit_balance_afn ?? 0),
                'total_invested'    => (float) ($inv->wallet?->total_invested_afn ?? 0),
                'total_profit'      => (float) ($inv->wallet?->total_profit_earned_afn ?? 0),
                'total_withdrawn'   => (float) ($inv->wallet?->total_withdrawn_afn ?? 0),
            ]);

        /* =========================================================
         |  TOP PROFITABLE INVESTORS (از wallet)
         ========================================================= */

        $topProfitableInvestors = Investor::notTrashed()
            ->when($own, fn ($q) => $q->where('investors.id', $onlyId))
            ->select('investors.*')
            ->selectSub(
                InvestorProfitEntry::selectRaw('COALESCE(SUM(profit_amount_afn), 0)')
                    ->whereColumn('investor_profit_entries.investor_id', 'investors.id')
                    ->whereIn('status', ['pending', 'paid']),
                'total_profit_afn'
            )
            ->orderByDesc('total_profit_afn')
            ->limit(10)
            ->get()
            ->map(fn ($inv) => [
                'id'               => $inv->id,
                'full_name'        => $inv->full_name,
                'phone'            => $inv->phone,
                'total_profit_afn' => (float) $inv->total_profit_afn,
            ]);

        /* =========================================================
         |  RECENT TRANSACTIONS (ledger)
         ========================================================= */

        $recentTransactions = InvestorTransaction::query()
            ->when($own, fn ($q) => $q->where('investor_id', $onlyId))
            ->with(['investor:id,full_name'])
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn ($t) => [
                'id'           => $t->id,
                'investor'     => $t->investor?->full_name,
                'type'         => $t->type,
                'type_label'   => $t->type_label,
                'amount_afn'   => (float) $t->amount_afn,
                'balance_type' => $t->balance_type,
                'balance_after'=> (float) $t->balance_after_afn,
                'currency'     => 'USD',
                'description'  => $t->description,
                'date'         => optional($t->transaction_date)->toDateString(),
            ]);

        /* =========================================================
         |  RENDER
         ========================================================= */

        return Inertia::render('investors/dashboard', [
            'days'                   => $days,
            'kpis'                   => $kpis,
            'capitalTrend'           => $capitalTrend,
            'profitByInvestor'       => $profitByInvestor,
            'withdrawalBreakdown'    => $withdrawalBreakdown,
            'investmentStatus'       => $investmentStatus,
            'counters'               => $counters,
            'recentInvestments'      => $recentInvestments,
            'recentProfitEntries'    => $recentProfitEntries,
            'recentWithdrawals'      => $recentWithdrawals,
            'recentTransactions'     => $recentTransactions,
            'topInvestors'           => $topInvestors,
            'topProfitableInvestors' => $topProfitableInvestors,
            'own'                    => $own,
            'stockItems'             => $stockItems,
        ]);
    }
}