<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorTransaction;
use App\Models\InvestorWallet;
use App\Models\InvestorWalletBalance;
use App\Models\InvestorWithdrawal;
use App\Models\ProductBatch;
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
        $onlyId = $own ? (int) ($request->user()->id ?? 0) : null;

        $reporting = $this->currencies->reportingContext();
        $fxBoard = $this->currencies->dashboardFxBoard();
        $money = fn (float|string|null $amount): float => $this->currencies->reportAmount((float) ($amount ?? 0));

        $walletQuery = InvestorWalletBalance::query()
            ->join('users', 'users.id', '=', 'investor_wallet_balances.investor_id')
            ->where('users.role', 'investor')
            ->where('users.trash', false)
            ->whereNull('users.deleted_at');
        $this->restrictInvestor($walletQuery, $own, $onlyId, 'users.id');

        $walletRow = $walletQuery
            ->selectRaw('COALESCE(SUM(investor_wallet_balances.capital_balance_afn), 0) as capital_balance')
            ->selectRaw('COALESCE(SUM(investor_wallet_balances.profit_balance_afn), 0) as profit_balance')
            ->first();

        $currentCapital = (float) ($walletRow->capital_balance ?? 0);
        $remainingProfit = (float) ($walletRow->profit_balance ?? 0);

        $lifetimeProfit = (float) InvestorTransaction::query()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->where('type', 'profit_earned')
            ->sum('amount_afn');

        $lifetimeWithdrawn = (float) InvestorWithdrawal::query()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->where('status', 'paid')
            ->sum('amount_afn');

        $periodContributions = (float) Investment::notTrashed()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->whereDate('investment_date', '>=', $startDate->toDateString())
            ->whereDate('investment_date', '<=', $endDate->toDateString())
            ->sum('amount_afn');

        $periodProfit = (float) InvestorTransaction::query()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->where('type', 'profit_earned')
            ->whereDate('transaction_date', '>=', $startDate->toDateString())
            ->whereDate('transaction_date', '<=', $endDate->toDateString())
            ->sum('amount_afn');

        $periodPendingProfit = 0.0;
        $periodPaidProfit = $periodProfit;

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

        $cashCapitalAfn = (float) InvestorTransaction::query()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->where('type', 'capital_deposit')
            ->sum('amount_afn');
        $goodsCapitalAfn = (float) InvestorTransaction::query()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->where('type', 'goods_deposit')
            ->sum('amount_afn');
        $totalContributedAfn = round($cashCapitalAfn + $goodsCapitalAfn, 2);

        $goodsQuery = ProductBatch::query()
            ->where('product_batches.trash', false)
            ->whereNotNull('product_batches.investment_id')
            ->join('investments', 'investments.id', '=', 'product_batches.investment_id')
            ->where('investments.trash', false);
        $this->restrictInvestor($goodsQuery, $own, $onlyId, 'investments.investor_id');

        $soldGoodsAfn = (float) (clone $goodsQuery)
            ->selectRaw('COALESCE(SUM((product_batches.quantity - product_batches.remaining_quantity) * product_batches.unit_cost_afn), 0) as total')
            ->value('total');
        $remainingGoodsAfn = (float) (clone $goodsQuery)
            ->selectRaw('COALESCE(SUM(product_batches.remaining_quantity * product_batches.unit_cost_afn), 0) as total')
            ->value('total');

        $availableCapital = $currentCapital;
        $investedCapital = $goodsCapitalAfn;

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

        $multiCurrencySummary = $this->multiCurrency->summarize($own && $onlyId > 0 ? $onlyId : null);
        $goodsOriginalByCurrency = [
            'AFN' => (float) ($multiCurrencySummary['currency_sections']['AFN']['goods_purchase_value'] ?? 0),
            'USD' => (float) ($multiCurrencySummary['currency_sections']['USD']['goods_purchase_value'] ?? 0),
            'AED' => (float) ($multiCurrencySummary['currency_sections']['AED']['goods_purchase_value'] ?? 0),
        ];
        $goodsHistoricalAfn = round(
            (float) ($multiCurrencySummary['currency_sections']['AFN']['historical_afn']['goods_purchase_value'] ?? 0)
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

        $purchases = ProductBatch::query()
            ->where('trash', false)
            ->whereNotNull('investment_id')
            ->when($own && $onlyId > 0, function ($q) use ($onlyId) {
                $q->whereHas('investment', fn ($i) => $i->where('investor_id', $onlyId));
            })
            ->with(['variant:id,name,product_id', 'variant.product:id,name', 'investment:id,investor_id'])
            ->orderByDesc('id')
            ->limit($own ? 100 : 0)
            ->get();

        $stockItems = $purchases->map(function (ProductBatch $row) use ($money) {
            $qty = (int) $row->quantity;
            $remaining = (int) $row->remaining_quantity;
            $sold = max(0, $qty - $remaining);
            $cost = (float) $row->total_cost_afn;
            $profitPerUnit = (float) $row->profit_per_unit_afn;

            return [
                'name' => $row->variant?->product?->name ?: ($row->variant?->name ?: ('Batch '.$row->batch_code)),
                'quantity' => $qty,
                'remaining' => $remaining,
                'sold' => $sold,
                'value' => $money($cost),
                'profit' => $money($profitPerUnit * $qty),
                'profit_per_unit_afn' => $profitPerUnit,
                'original_unit' => $row->unit_cost_currency,
                'original_line' => (float) $row->purchase_amount_currency,
                'historical_afn' => round($cost, 2),
                'exchange_rate' => $row->exchange_rate,
            ];
        })->values();

        $stockValueLedger = (float) $purchases->sum(fn ($row) => (float) $row->total_cost_afn);
        $kpis['stock_value'] = $money($stockValueLedger);
        $kpis3c['stock_value'] = $this->currencies->threeCurrencyFromLedger($stockValueLedger);

        $trendRows = Investment::notTrashed()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->select(
                DB::raw('DATE(investment_date) as date'),
                DB::raw('SUM(amount_afn) as capital'),
                DB::raw('COUNT(*) as count')
            )
            ->whereDate('investment_date', '>=', $startDate->toDateString())
            ->whereDate('investment_date', '<=', $endDate->toDateString())
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $profitTrendRows = InvestorTransaction::query()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->where('type', 'profit_earned')
            ->select(
                DB::raw('DATE(transaction_date) as date'),
                DB::raw('SUM(amount_afn) as profit')
            )
            ->whereDate('transaction_date', '>=', $startDate->toDateString())
            ->whereDate('transaction_date', '<=', $endDate->toDateString())
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $capitalTrend = [];
        $trendLedger = [];
        $cursor = $startDate->copy();
        while ($cursor->lte($endDate->copy()->startOfDay())) {
            $key = $cursor->toDateString();
            $row = $trendRows->get($key);
            $profitRow = $profitTrendRows->get($key);
            $capitalAfn = $row ? (float) $row->capital : 0.0;
            $profitAfn = $profitRow ? (float) $profitRow->profit : 0.0;
            $trendLedger["{$key}|capital"] = $capitalAfn;
            $trendLedger["{$key}|allocated"] = 0.0;
            $trendLedger["{$key}|profit"] = $profitAfn;
            $capitalTrend[] = [
                'date' => $cursor->format('M d'),
                'date_key' => $key,
                'capital' => $money($capitalAfn),
                'allocated' => $money(0),
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

        $profitByInvestor = InvestorTransaction::query()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId, 'investor_transactions.investor_id'))
            ->join('users', 'investor_transactions.investor_id', '=', 'users.id')
            ->where('users.role', 'investor')
            ->where('users.trash', false)
            ->where('investor_transactions.type', 'profit_earned')
            ->whereDate('investor_transactions.transaction_date', '>=', $startDate->toDateString())
            ->whereDate('investor_transactions.transaction_date', '<=', $endDate->toDateString())
            ->select('users.id', 'users.name', DB::raw('SUM(investor_transactions.amount_afn) as total'))
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
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount_afn) as total'))
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
                    DB::raw('SUM(COALESCE(investments.amount_currency, investments.amount_afn)) as total')
                )
                ->groupBy('code')
                ->get()
                ->map(fn ($row) => [
                    'code' => $row->code,
                    'total' => round((float) $row->total, 2),
                ])
                ->all();
        }

        $batchCountQuery = ProductBatch::query()
            ->where('trash', false)
            ->whereNotNull('investment_id')
            ->when($own && $onlyId > 0, fn ($q) => $q->whereHas('investment', fn ($i) => $i->where('investor_id', $onlyId)));

        $counters = [
            'total_investors' => Investor::notTrashed()->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId, 'id'))->count(),
            'active_investors' => Investor::notTrashed()->active()->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId, 'id'))->count(),
            'total_investments' => Investment::notTrashed()->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))->count(),
            'active_investments' => Investment::notTrashed()->active()->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))->count(),
            'total_allocations' => (clone $batchCountQuery)->count(),
            'total_funded_qty' => (int) (clone $batchCountQuery)->sum('quantity'),
            'total_sold_qty' => (int) (clone $batchCountQuery)->selectRaw('COALESCE(SUM(quantity - remaining_quantity), 0) as sold')->value('sold'),
            'pending_withdrawals' => InvestorWithdrawal::pending()
                ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
                ->count(),
            'pending_profit_rows' => 0,
        ];

        $recentInvestments = Investment::query()
            ->with(['investor:id,name,phone', 'currency:id,code,symbol'])
            ->notTrashed()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->whereDate('investment_date', '>=', $startDate->toDateString())
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'investor_id' => $inv->investor_id,
                'investment_number' => 'INV-'.$inv->id,
                'investor' => $inv->investor?->full_name,
                'investor_phone' => $inv->investor?->phone,
                'capital_amount' => $money($inv->amount_afn),
                'available' => $money(0),
                'allocated' => $money($inv->amount_afn),
                'profit' => $money(0),
                'progress_percent' => 100,
                'status' => $inv->status,
                'status_label' => Investment::STATUS_LABELS[$inv->status] ?? $inv->status,
                'investment_date' => optional($inv->investment_date)->toDateString(),
            ]);

        $recentProfitEntries = InvestorTransaction::query()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId))
            ->where('type', 'profit_earned')
            ->with(['investor:id,name'])
            ->whereDate('transaction_date', '>=', $startDate->toDateString())
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'investor' => $e->investor?->full_name,
                'sale_invoice' => null,
                'quantity' => null,
                'profit_amount' => $money($e->amount_afn),
                'entry_type' => 'profit',
                'entry_type_label' => 'Profit Earned',
                'status' => 'paid',
                'status_label' => 'Paid',
                'earned_at' => optional($e->transaction_date)->toDateTimeString(),
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
                InvestorWalletBalance::query()
                    ->selectRaw('COALESCE(SUM(capital_balance_afn), 0)')
                    ->whereColumn('investor_wallet_balances.investor_id', 'users.id'),
                'capital_balance_afn'
            )
            ->withCount(['investments'])
            ->orderByDesc('capital_balance_afn')
            ->limit(10)
            ->get()
            ->map(function ($inv) use ($money) {
                $wallet = InvestorWallet::forInvestor((int) $inv->id);

                return [
                    'id' => $inv->id,
                    'full_name' => $inv->full_name,
                    'phone' => $inv->phone,
                    'status' => $inv->status,
                    'investments_count' => $inv->investments_count,
                    'total_capital' => $money($inv->capital_balance_afn),
                    'total_allocated' => $money(0),
                    'total_profit' => $money($wallet->profit_balance_afn),
                    'total_withdrawn' => $money(0),
                ];
            });

        $topProfitableInvestors = Investor::notTrashed()
            ->tap(fn ($q) => $this->restrictInvestor($q, $own, $onlyId, 'users.id'))
            ->select('users.*')
            ->selectSub(
                InvestorTransaction::query()
                    ->selectRaw('COALESCE(SUM(amount_afn), 0)')
                    ->whereColumn('investor_transactions.investor_id', 'users.id')
                    ->where('type', 'profit_earned')
                    ->whereDate('transaction_date', '>=', $startDate->toDateString())
                    ->whereDate('transaction_date', '<=', $endDate->toDateString()),
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
