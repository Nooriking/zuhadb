<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use App\Models\InvestmentProductAllocation;
use App\Models\Investor;
use App\Models\InvestorProfitEntry;
use App\Models\InvestorTransaction;
use App\Models\InvestorWithdrawal;
use App\Models\Product;
use App\Services\CurrencyService;
use App\Services\InvestorMultiCurrencyDashboardService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvestorDashboardController extends Controller
{
    public function __construct(
        private readonly CurrencyService $currencies,
        private readonly InvestorMultiCurrencyDashboardService $multiCurrency,
    ) {}

    public function index(Request $request): Response
    {
        $days = (int) $request->query('days', 30);
        if ($days < 1 || $days > 365) {
            $days = 30;
        }

        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        $own = (bool) $request->user()?->isInvestor();
        // Investor identity is the user row itself.
        $onlyId = $own ? (int) ($request->user()->id ?? 0) : null;

        $reporting = $this->currencies->reportingContext();
        $fxBoard = $this->currencies->dashboardFxBoard();
        $money = fn (float|string|null $amount): float => $this->currencies->reportAmount((float) ($amount ?? 0));

        $walletQuery = DB::table('investor_wallets')
            ->join('users', 'users.id', '=', 'investor_wallets.investor_id')
            ->where('users.role', 'investor')
            ->where('users.trash', false)
            ->whereNull('users.deleted_at');
        $this->restrictInvestor($walletQuery, $own, $onlyId, 'users.id');

        $walletRow = $walletQuery
            ->selectRaw('COALESCE(SUM(investor_wallets.capital_balance_afn), 0) as capital_balance')
            ->selectRaw('COALESCE(SUM(investor_wallets.profit_balance_afn), 0) as profit_balance')
            ->selectRaw('COALESCE(SUM(investor_wallets.total_invested_afn), 0) as total_invested')
            ->selectRaw('COALESCE(SUM(investor_wallets.total_profit_earned_afn), 0) as total_profit_earned')
            ->selectRaw('COALESCE(SUM(investor_wallets.total_withdrawn_afn), 0) as total_withdrawn')
            ->first();

        $availableCapital = (float) Investment::notTrashed()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->whereIn('status', ['active', 'partially_used'])
            ->sum('available_amount_afn');

        $investedCapital = (float) Investment::notTrashed()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->sum('allocated_amount_afn');

        $periodContributions = (float) Investment::notTrashed()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->whereDate('investment_date', '>=', $startDate->toDateString())
            ->whereDate('investment_date', '<=', $endDate->toDateString())
            ->sum('capital_amount_afn');

        $periodProfit = (float) InvestorProfitEntry::query()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->whereIn('status', ['pending', 'paid'])
            ->where('earned_at', '>=', $startDate)
            ->where('earned_at', '<=', $endDate)
            ->sum('profit_amount_afn');

        $periodPendingProfit = (float) InvestorProfitEntry::query()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->where('status', 'pending')
            ->where('earned_at', '>=', $startDate)
            ->where('earned_at', '<=', $endDate)
            ->sum('profit_amount_afn');

        $periodPaidProfit = (float) InvestorProfitEntry::query()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->where('status', 'paid')
            ->where('earned_at', '>=', $startDate)
            ->where('earned_at', '<=', $endDate)
            ->sum('profit_amount_afn');

        $periodWithdrawnProfit = (float) InvestorWithdrawal::query()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->where('type', 'profit')
            ->whereIn('status', ['approved', 'paid'])
            ->whereDate('withdrawal_date', '>=', $startDate->toDateString())
            ->whereDate('withdrawal_date', '<=', $endDate->toDateString())
            ->sum('amount_afn');

        $periodWithdrawnCapital = (float) InvestorWithdrawal::query()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->where('type', 'capital')
            ->whereIn('status', ['approved', 'paid'])
            ->whereDate('withdrawal_date', '>=', $startDate->toDateString())
            ->whereDate('withdrawal_date', '<=', $endDate->toDateString())
            ->sum('amount_afn');

        $currentCapital = (float) ($walletRow->capital_balance ?? 0);
        $remainingProfit = (float) ($walletRow->profit_balance ?? 0);
        $lifetimeProfit = (float) ($walletRow->total_profit_earned ?? 0);
        $lifetimeWithdrawn = (float) ($walletRow->total_withdrawn ?? 0);

        $cashCapitalAfn = (float) Investment::notTrashed()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->where('source_type', 'cash')
            ->sum('capital_amount_afn');
        $goodsCapitalAfn = (float) Investment::notTrashed()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->where('source_type', 'goods')
            ->sum('capital_amount_afn');
        $totalContributedAfn = round($cashCapitalAfn + $goodsCapitalAfn, 2);

        $soldGoodsAfn = (float) InvestmentProductAllocation::query()
            ->whereHas('investment', function ($q) use ($own, $onlyId) {
                $q->where('trash', false);
                $this->restrictInvestor($q, $own, $onlyId);
            })
            ->selectRaw('COALESCE(SUM(sold_quantity * cost_per_unit_afn), 0) as total')
            ->value('total');
        $remainingGoodsAfn = (float) InvestmentProductAllocation::query()
            ->whereHas('investment', function ($q) use ($own, $onlyId) {
                $q->where('trash', false);
                $this->restrictInvestor($q, $own, $onlyId);
            })
            ->selectRaw('COALESCE(SUM(remaining_quantity * cost_per_unit_afn), 0) as total')
            ->value('total');

        $usd = \App\Models\Currency::query()->notTrashed()->where('code', 'USD')->first();
        $aed = \App\Models\Currency::query()->notTrashed()->where('code', 'AED')->first();
        $ledger = \App\Models\Currency::ledger();
        $currentUsdEquivalent = null;
        $currentAedEquivalent = null;
        if ($ledger && $usd && $this->currencies->canConvert($ledger, $usd)) {
            $currentUsdEquivalent = (float) $this->currencies->convert((string) $totalContributedAfn, $ledger, $usd);
        }
        if ($ledger && $aed && $this->currencies->canConvert($ledger, $aed)) {
            $currentAedEquivalent = (float) $this->currencies->convert((string) $totalContributedAfn, $ledger, $aed);
        }

        // Original-currency goods totals (never sum AFN+USD+AED together).
        $multiCurrencySummary = $this->multiCurrency->summarize($own && $onlyId > 0 ? $onlyId : null);
        $goodsOriginalByCurrency = [
            'AFN' => (float) ($multiCurrencySummary['currency_sections']['AFN']['goods_purchase_value'] ?? 0),
            'USD' => (float) ($multiCurrencySummary['currency_sections']['USD']['goods_purchase_value'] ?? 0),
            'AED' => (float) ($multiCurrencySummary['currency_sections']['AED']['goods_purchase_value'] ?? 0),
        ];
        $goodsHistoricalAfn = (float) (
            $multiCurrencySummary['currency_sections']['AFN']['historical_afn']['goods_purchase_value']
            ?? 0
        );
        $goodsHistoricalAfn = round(
            $goodsHistoricalAfn
            + (float) ($multiCurrencySummary['currency_sections']['USD']['historical_afn']['goods_purchase_value'] ?? 0)
            + (float) ($multiCurrencySummary['currency_sections']['AED']['historical_afn']['goods_purchase_value'] ?? 0),
            2
        );

        $kpis3c = $this->currencies->mapThreeCurrency([
            'total_capital' => $currentCapital,
            'cash_capital' => $cashCapitalAfn,
            'goods_capital' => $goodsCapitalAfn,
            'total_contributed_capital' => $totalContributedAfn,
            'sold_goods_value' => $soldGoodsAfn,
            'remaining_goods_value' => $remainingGoodsAfn,
            'available_capital' => $availableCapital,
            'invested_capital' => $investedCapital,
            'lifetime_profit' => $lifetimeProfit,
            'lifetime_withdrawn' => $lifetimeWithdrawn,
            'period_contributions' => $periodContributions,
            'total_profit' => $periodProfit,
            'pending_profit' => $periodPendingProfit,
            'paid_profit' => $periodPaidProfit,
            'withdrawn_profit' => $periodWithdrawnProfit,
            'withdrawn_capital' => $periodWithdrawnCapital,
            'remaining_profit' => $remainingProfit,
        ]);

        $kpis = [
            'total_capital' => $money($currentCapital),
            'cash_capital' => $money($cashCapitalAfn),
            'goods_capital' => $money($goodsCapitalAfn),
            'total_contributed_capital' => $money($totalContributedAfn),
            'historical_afn_capital' => round($totalContributedAfn, 2),
            'goods_original_by_currency' => $goodsOriginalByCurrency,
            'goods_historical_afn' => $goodsHistoricalAfn,
            'current_usd_equivalent' => $currentUsdEquivalent,
            'current_aed_equivalent' => $currentAedEquivalent,
            'sold_goods_value' => $money($soldGoodsAfn),
            'remaining_goods_value' => $money($remainingGoodsAfn),
            'available_capital' => $money($availableCapital),
            'invested_capital' => $money($investedCapital),
            'lifetime_profit' => $money($lifetimeProfit),
            'lifetime_withdrawn' => $money($lifetimeWithdrawn),
            'period_contributions' => $money($periodContributions),
            'total_profit' => $money($periodProfit),
            'pending_profit' => $money($periodPendingProfit),
            'paid_profit' => $money($periodPaidProfit),
            'withdrawn_profit' => $money($periodWithdrawnProfit),
            'withdrawn_capital' => $money($periodWithdrawnCapital),
            'remaining_profit' => $money($remainingProfit),
            'capital_utilization' => $totalContributedAfn > 0
                ? round(($investedCapital / $totalContributedAfn) * 100, 1)
                : 0,
            'profit_margin' => $totalContributedAfn > 0
                ? round(($lifetimeProfit / $totalContributedAfn) * 100, 1)
                : 0,
        ];

        $purchases = $own && $onlyId > 0
            ? Product::query()
                ->where('trash', false)
                ->whereNotNull('variant_id')
                ->where('investor_id', $onlyId)
                ->orderByDesc('id')
                ->get([
                    'id',
                    'name',
                    'quantity',
                    'remaining_quantity',
                    'line_total',
                    'profit_per_unit',
                    'profit_currency_code',
                    'purchase_currency_code',
                    'purchase_unit_amount',
                    'purchase_line_amount',
                    'unit_amount_usd',
                    'unit_amount_aed',
                    'line_amount_usd',
                    'line_amount_aed',
                    'usd_to_afn_rate',
                    'aed_to_afn_rate',
                ])
            : collect();

        $stockItems = $purchases->map(function ($row) use ($money) {
            $qty = (int) $row->quantity;
            $remaining = (int) $row->remaining_quantity;
            $sold = max(0, $qty - $remaining);
            $cost = (float) $row->line_total;
            $profitPerUnit = (float) $row->profit_per_unit;
            $profit = $profitPerUnit * $qty;

            return [
                'name' => $row->name ?: 'Product',
                'quantity' => $qty,
                'remaining' => $remaining,
                'sold' => $sold,
                'value' => $money($cost),
                'profit' => $money($profit),
                'profit_per_unit_afn' => $profitPerUnit,
                'profit_currency_code' => $row->profit_currency_code,
                'original_currency' => $row->purchase_currency_code,
                'original_unit' => $row->purchase_unit_amount,
                'original_line' => $row->purchase_line_amount,
                'historical_afn' => round($cost, 2),
                'historical_usd' => $row->line_amount_usd !== null ? (float) $row->line_amount_usd : null,
                'historical_aed' => $row->line_amount_aed !== null ? (float) $row->line_amount_aed : null,
                'usd_to_afn_rate' => $row->usd_to_afn_rate,
                'aed_to_afn_rate' => $row->aed_to_afn_rate,
            ];
        })->values();

        $stockValueLedger = (float) $purchases->sum(fn ($row) => (float) $row->line_total);
        $kpis['stock_value'] = $money($stockValueLedger);
        $kpis3c['stock_value'] = $this->currencies->threeCurrencyFromLedger($stockValueLedger);

        $trendRows = Investment::notTrashed()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->select(
                DB::raw('DATE(investment_date) as date'),
                DB::raw('SUM(capital_amount_afn) as capital'),
                DB::raw('SUM(allocated_amount_afn) as allocated'),
                DB::raw('SUM(profit_amount_afn) as profit'),
                DB::raw('COUNT(*) as count')
            )
            ->whereDate('investment_date', '>=', $startDate->toDateString())
            ->whereDate('investment_date', '<=', $endDate->toDateString())
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $capitalTrend = [];
        $trendLedger = [];
        $cursor = $startDate->copy();
        while ($cursor->lte($endDate->copy()->startOfDay())) {
            $row = $trendRows->get($cursor->toDateString());
            $key = $cursor->toDateString();
            $capitalAfn = $row ? (float) $row->capital : 0.0;
            $allocatedAfn = $row ? (float) $row->allocated : 0.0;
            $profitAfn = $row ? (float) $row->profit : 0.0;
            $trendLedger["{$key}|capital"] = $capitalAfn;
            $trendLedger["{$key}|allocated"] = $allocatedAfn;
            $trendLedger["{$key}|profit"] = $profitAfn;
            $capitalTrend[] = [
                'date' => $cursor->format('M d'),
                'date_key' => $key,
                'capital' => $money($capitalAfn),
                'allocated' => $money($allocatedAfn),
                'profit' => $money($profitAfn),
                'count' => $row ? (int) $row->count : 0,
            ];
            $cursor->addDay();
        }

        $trend3c = $this->currencies->mapThreeCurrency($trendLedger);
        $capitalTrend = array_map(function (array $point) use ($trend3c) {
            $point['capital_3c'] = $trend3c[$point['date_key'].'|capital'] ?? null;
            $point['allocated_3c'] = $trend3c[$point['date_key'].'|allocated'] ?? null;
            $point['profit_3c'] = $trend3c[$point['date_key'].'|profit'] ?? null;
            unset($point['date_key']);

            return $point;
        }, $capitalTrend);

        $profitByInvestor = InvestorProfitEntry::query()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId, 'investor_profit_entries.investor_id'))
            ->join('users', 'investor_profit_entries.investor_id', '=', 'users.id')
            ->where('users.role', 'investor')
            ->where('users.trash', false)
            ->whereIn('investor_profit_entries.status', ['pending', 'paid'])
            ->where('investor_profit_entries.earned_at', '>=', $startDate)
            ->where('investor_profit_entries.earned_at', '<=', $endDate)
            ->select(
                'users.id',
                'users.name',
                DB::raw('SUM(investor_profit_entries.profit_amount_afn) as total')
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'value' => $money($row->total),
            ]);

        $withdrawalBreakdown = collect([
            ['name' => 'Profit withdrawn', 'value' => $kpis['withdrawn_profit']],
            ['name' => 'Capital withdrawn', 'value' => $kpis['withdrawn_capital']],
        ])->filter(fn ($row) => $row['value'] > 0)->values();

        $investmentStatus = Investment::notTrashed()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(capital_amount_afn) as total'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'name' => Investment::STATUS_LABELS[$row->status] ?? $row->status,
                'value' => $money($row->total),
                'count' => (int) $row->count,
            ]);

        $originalCurrencyTotals = [];
        if (! $reporting['conversion_available']) {
            $originalCurrencyTotals = Investment::query()
                ->where('investments.trash', false)
                ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId, 'investments.investor_id'))
                ->leftJoin('currencies', 'currencies.id', '=', 'investments.currency_id')
                ->select(
                    DB::raw("COALESCE(currencies.code, 'AFN') as code"),
                    DB::raw('SUM(COALESCE(investments.capital_amount_currency, investments.capital_amount_afn)) as total')
                )
                ->groupBy('code')
                ->get()
                ->map(fn ($row) => [
                    'code' => $row->code,
                    'total' => round((float) $row->total, 2),
                ])
                ->all();
        }

        $counters = [
            'total_investors' => Investor::notTrashed()->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId, 'id'))->count(),
            'active_investors' => Investor::notTrashed()->active()->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId, 'id'))->count(),
            'total_investments' => Investment::notTrashed()->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))->count(),
            'active_investments' => Investment::notTrashed()->active()->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))->count(),
            'total_allocations' => InvestmentProductAllocation::query()
                ->when($own, function ($q) use ($onlyId) {
                    if ($onlyId <= 0) {
                        $q->whereRaw('1 = 0');

                        return;
                    }
                    $q->whereHas('investment', fn ($i) => $i->where('investor_id', $onlyId));
                })
                ->count(),
            'total_funded_qty' => (int) InvestmentProductAllocation::query()
                ->when($own, function ($q) use ($onlyId) {
                    if ($onlyId <= 0) {
                        $q->whereRaw('1 = 0');

                        return;
                    }
                    $q->whereHas('investment', fn ($i) => $i->where('investor_id', $onlyId));
                })
                ->sum('funded_quantity'),
            'total_sold_qty' => (int) InvestmentProductAllocation::query()
                ->when($own, function ($q) use ($onlyId) {
                    if ($onlyId <= 0) {
                        $q->whereRaw('1 = 0');

                        return;
                    }
                    $q->whereHas('investment', fn ($i) => $i->where('investor_id', $onlyId));
                })
                ->sum('sold_quantity'),
            'pending_withdrawals' => InvestorWithdrawal::pending()
                ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
                ->count(),
            'pending_profit_rows' => InvestorProfitEntry::pending()
                ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
                ->count(),
        ];

        $recentInvestments = Investment::query()
            ->with(['investor:id,name,phone'])
            ->notTrashed()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->whereDate('investment_date', '>=', $startDate->toDateString())
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'investor_id' => $inv->investor_id,
                'investment_number' => $inv->investment_number,
                'investor' => $inv->investor?->full_name,
                'investor_phone' => $inv->investor?->phone,
                'capital_amount' => $money($inv->capital_amount_afn),
                'available' => $money($inv->available_amount_afn),
                'allocated' => $money($inv->allocated_amount_afn),
                'profit' => $money($inv->profit_amount_afn),
                'progress_percent' => $inv->progress_percent,
                'status' => $inv->status,
                'status_label' => Investment::STATUS_LABELS[$inv->status] ?? $inv->status,
                'investment_date' => optional($inv->investment_date)->toDateString(),
            ]);

        $recentProfitEntries = InvestorProfitEntry::query()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->with([
                'investor:id,name',
                'sale:id,invoice_number,total_afn',
            ])
            ->where('earned_at', '>=', $startDate)
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'investor' => $e->investor?->full_name,
                'sale_invoice' => $e->sale?->invoice_number,
                'quantity' => $e->quantity,
                'profit_amount' => $money($e->profit_amount_afn),
                'entry_type' => $e->entry_type,
                'entry_type_label' => $e->entry_type_label,
                'status' => $e->status,
                'status_label' => $e->status_label,
                'earned_at' => optional($e->earned_at)->toDateTimeString(),
            ]);

        $recentWithdrawals = InvestorWithdrawal::query()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->with(['investor:id,name'])
            ->whereDate('withdrawal_date', '>=', $startDate->toDateString())
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn ($w) => [
                'id' => $w->id,
                'withdrawal_number' => $w->withdrawal_number,
                'investor' => $w->investor?->full_name,
                'type' => $w->type,
                'amount' => $money($w->amount_afn),
                'status' => $w->status,
                'withdrawal_date' => optional($w->withdrawal_date)->toDateString(),
            ]);

        $topInvestors = Investor::notTrashed()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId, 'users.id'))
            ->select('users.*')
            ->selectSub(
                DB::table('investor_wallets')
                    ->selectRaw('COALESCE(capital_balance_afn, 0)')
                    ->whereColumn('investor_wallets.investor_id', 'users.id'),
                'capital_balance_afn'
            )
            ->selectSub(
                Investment::selectRaw('COALESCE(SUM(allocated_amount_afn), 0)')
                    ->whereColumn('investments.investor_id', 'users.id')
                    ->where('investments.trash', false),
                'total_allocated_afn'
            )
            ->with(['wallet'])
            ->withCount(['investments'])
            ->orderByDesc('capital_balance_afn')
            ->limit(10)
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'full_name' => $inv->full_name,
                'phone' => $inv->phone,
                'status' => $inv->status,
                'investments_count' => $inv->investments_count,
                'total_capital' => $money($inv->capital_balance_afn),
                'total_allocated' => $money($inv->total_allocated_afn),
                'total_profit' => $money($inv->wallet?->total_profit_earned_afn ?? 0),
                'total_withdrawn' => $money($inv->wallet?->total_withdrawn_afn ?? 0),
            ]);

        $topProfitableInvestors = Investor::notTrashed()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId, 'users.id'))
            ->select('users.*')
            ->selectSub(
                InvestorProfitEntry::selectRaw('COALESCE(SUM(profit_amount_afn), 0)')
                    ->whereColumn('investor_profit_entries.investor_id', 'users.id')
                    ->whereIn('status', ['pending', 'paid'])
                    ->where('earned_at', '>=', $startDate)
                    ->where('earned_at', '<=', $endDate),
                'total_profit_afn'
            )
            ->orderByDesc('total_profit_afn')
            ->limit(10)
            ->get()
            ->filter(fn ($inv) => (float) $inv->total_profit_afn > 0)
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'full_name' => $inv->full_name,
                'phone' => $inv->phone,
                'total_profit' => $money($inv->total_profit_afn),
            ])
            ->values();

        $recentTransactions = InvestorTransaction::query()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->with(['investor:id,name'])
            ->whereDate('transaction_date', '>=', $startDate->toDateString())
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'investor' => $t->investor?->full_name,
                'type' => $t->type,
                'type_label' => $t->type_label,
                'amount' => $money($t->amount_afn),
                'balance_type' => $t->balance_type,
                'balance_after' => $money($t->balance_after_afn),
                'description' => $t->description,
                'date' => optional($t->transaction_date)->toDateString(),
            ]);

        return Inertia::render('investors/dashboard', [
            'days' => $days,
            'kpis' => $kpis,
            'kpis3c' => $kpis3c,
            'capitalTrend' => $capitalTrend,
            'profitByInvestor' => $profitByInvestor,
            'withdrawalBreakdown' => $withdrawalBreakdown,
            'investmentStatus' => $investmentStatus,
            'counters' => $counters,
            'recentInvestments' => $recentInvestments,
            'recentProfitEntries' => $recentProfitEntries,
            'recentWithdrawals' => $recentWithdrawals,
            'recentTransactions' => $recentTransactions,
            'topInvestors' => $topInvestors,
            'topProfitableInvestors' => $topProfitableInvestors,
            'own' => $own,
            'stockItems' => $stockItems,
            'reporting' => $reporting,
            'fxBoard' => $fxBoard,
            'originalCurrencyTotals' => $originalCurrencyTotals,
            'currencySections' => $multiCurrencySummary['currency_sections'],
            'exchangeValuation' => $multiCurrencySummary['exchange_valuation'],
            'multiCurrencyLegacy' => $multiCurrencySummary['legacy'],
            'multiCurrencyLimitations' => $multiCurrencySummary['limitations'],
            'multiCurrencyFormulas' => $multiCurrencySummary['formulas'],
            'dashboardPath' => $own ? '/dashboard' : '/investors/dashboard',
        ]);
    }

    private function restrictInvestor(Builder|\Illuminate\Database\Query\Builder $query, bool $own, ?int $onlyId, string $column = 'investor_id'): void
    {
        if (! $own) {
            return;
        }

        if (! $onlyId || $onlyId <= 0) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where($column, $onlyId);
    }
}
