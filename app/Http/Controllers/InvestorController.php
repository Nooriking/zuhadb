<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestmentRequest;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorTransaction;
use App\Models\InvestorWalletBalance;
use App\Models\InvestorWithdrawal;
use App\Models\ProductBatch;
use App\Services\ExchangeService;
use App\Services\InvestmentCapitalService;
use App\Services\InvestorMultiCurrencyDashboardService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Investor overview — identity lives on Users (role=investor).
 * Ledgers: investments, investor_transactions, investor_wallet_balances, product_batches.
 */
class InvestorController extends Controller
{
    private const INDEX_CURRENCIES = ['AFN', 'USD', 'AED'];

    public function __construct(
        private readonly InvestmentCapitalService $capital,
        private readonly InvestorMultiCurrencyDashboardService $multiCurrency,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Investor::class);

        $search = $request->query('search');
        $status = $request->query('status');

        $investors = Investor::query()
            ->with(['investorWalletBalances'])
            ->withCount(['investments', 'withdrawals', 'batches'])
            ->withSum('investments as total_invested_afn', 'amount_afn')
            ->notTrashed()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $ids = $investors->getCollection()->pluck('id')->all();
        $goodsTotals = $this->goodsTotalsByInvestor($ids);
        $profitTotals = $this->profitTotalsByInvestor($ids);
        $cashTotals = $this->cashDepositTotalsByInvestor($ids);
        $goodsDepositTotals = $this->goodsDepositTotalsByInvestor($ids);
        $withdrawalTotals = $this->withdrawalTotalsByInvestor($ids);
        $rates = $this->centralRatesToAfn();

        $investors->getCollection()->transform(function (Investor $investor) use (
            $goodsTotals,
            $profitTotals,
            $cashTotals,
            $goodsDepositTotals,
            $withdrawalTotals,
            $rates
        ) {
            $goods = $goodsTotals[$investor->id] ?? ['held' => '0.00', 'sold' => '0.00'];
            $profits = $profitTotals[$investor->id] ?? ['total' => '0.00'];
            $balances = $investor->investorWalletBalances->keyBy(
                fn ($row) => strtoupper((string) $row->currency_code)
            );

            $wallet = [];
            $totalAfnEquivalent = '0';
            foreach (self::INDEX_CURRENCIES as $code) {
                $row = $balances->get($code);
                $capitalCurrency = (string) ($row?->capital_balance_currency ?? '0');
                $profitCurrency = (string) ($row?->profit_balance_currency ?? '0');
                $capitalLedgerAfn = (string) ($row?->capital_balance_afn ?? '0');
                $profitLedgerAfn = (string) ($row?->profit_balance_afn ?? '0');
                $lastRate = (float) ($row?->last_rate ?? ($code === 'AFN' ? 1 : 0));
                $rate = $rates[$code] ?? null;

                $capitalAfnEq = $rate !== null
                    ? Money::round(Money::mul($capitalCurrency, (string) $rate, 8), 2)
                    : Money::round($capitalLedgerAfn, 2);
                $profitAfnEq = $rate !== null
                    ? Money::round(Money::mul($profitCurrency, (string) $rate, 8), 2)
                    : Money::round($profitLedgerAfn, 2);
                $balanceAfnEq = Money::round(Money::add($capitalAfnEq, $profitAfnEq, 8), 2);
                $totalAfnEquivalent = Money::add($totalAfnEquivalent, $balanceAfnEq, 8);

                $wallet[$code] = [
                    'capital' => (float) $capitalLedgerAfn,
                    'profit' => (float) $profitLedgerAfn,
                    'capital_currency' => (float) $capitalCurrency,
                    'profit_currency' => (float) $profitCurrency,
                    'balance_currency' => (float) Money::round(Money::add($capitalCurrency, $profitCurrency, 8), 2),
                    'last_rate' => $lastRate,
                    'rate' => $rate !== null ? (float) $rate : null,
                    'rate_available' => $rate !== null,
                    'capital_afn_equivalent' => (float) $capitalAfnEq,
                    'profit_afn_equivalent' => (float) $profitAfnEq,
                    'balance_afn_equivalent' => (float) $balanceAfnEq,
                ];
            }

            return [
                'id' => $investor->id,
                'full_name' => $investor->full_name,
                'phone' => $investor->phone,
                'email' => $investor->email,
                'status' => $investor->status,
                'wallet' => $wallet,
                'total_afn_equivalent' => (float) Money::round($totalAfnEquivalent, 2),
                'investments_count' => (int) $investor->investments_count,
                'withdrawals_count' => (int) $investor->withdrawals_count,
                'batches_count' => (int) $investor->batches_count,
                'ledger' => [
                    'cash_capital_afn' => (float) ($cashTotals[$investor->id] ?? 0),
                    'goods_contribution_afn' => (float) ($goodsDepositTotals[$investor->id] ?? 0),
                    'goods_held_afn' => (float) $goods['held'],
                    'goods_sold_afn' => (float) $goods['sold'],
                    'total_profit_afn' => (float) $profits['total'],
                    'paid_profit_afn' => (float) $profits['total'],
                    'pending_profit_afn' => 0.0,
                    'total_withdrawals_afn' => (float) ($withdrawalTotals[$investor->id] ?? 0),
                ],
            ];
        });

        $stats = [
            'total_investors' => Investor::notTrashed()->count(),
            'active_investors' => Investor::notTrashed()->where('status', 'active')->count(),
            'cash_capital' => Money::round((string) InvestorTransaction::query()
                ->where('type', 'capital_deposit')
                ->sum('amount_afn'), 2),
            'goods_contribution' => Money::round((string) InvestorTransaction::query()
                ->where('type', 'goods_deposit')
                ->sum('amount_afn'), 2),
            'goods_held' => Money::round((string) $this->globalGoodsHeld(), 2),
            'goods_sold' => Money::round((string) $this->globalGoodsSold(), 2),
            'total_investor_profit' => Money::round((string) InvestorTransaction::query()
                ->where('type', 'profit_earned')
                ->sum('amount_afn'), 2),
            'paid_profit' => Money::round((string) InvestorTransaction::query()
                ->where('type', 'profit_earned')
                ->sum('amount_afn'), 2),
            'pending_profit' => '0.00',
            'total_withdrawals' => Money::round((string) InvestorWithdrawal::query()
                ->where('status', 'paid')
                ->sum('amount_afn'), 2),
            'current_balance' => Money::round(Money::add(
                (string) InvestorWalletBalance::query()->sum('capital_balance_afn'),
                (string) InvestorWalletBalance::query()->sum('profit_balance_afn'),
                8
            ), 2),
            'total_capital' => Money::round((string) InvestorWalletBalance::query()->sum('capital_balance_afn'), 2),
            'total_unallocated' => Money::round((string) InvestorWalletBalance::query()->sum('capital_balance_afn'), 2),
        ];

        $multiCurrencySummary = $this->multiCurrency->summarize(null);

        return Inertia::render('investors/index', [
            'investors' => $investors,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'stats' => $stats,
            'rates' => [
                'AFN' => 1.0,
                'USD' => isset($rates['USD']) ? (float) $rates['USD'] : null,
                'AED' => isset($rates['AED']) ? (float) $rates['AED'] : null,
            ],
            'kpi_sources' => [
                'cash_capital' => 'investor_transactions.amount_afn WHERE type=capital_deposit',
                'goods_contribution' => 'investor_transactions.amount_afn WHERE type=goods_deposit',
                'goods_held' => 'product_batches.remaining_quantity * unit_cost_afn via investments',
                'goods_sold' => '(quantity - remaining_quantity) * unit_cost_afn via investments',
                'total_investor_profit' => 'investor_transactions.amount_afn WHERE type=profit_earned',
                'paid_profit' => 'investor_transactions.amount_afn WHERE type=profit_earned',
                'pending_profit' => '0 (profit posted to wallet at sale time)',
                'total_withdrawals' => 'investor_withdrawals.amount_afn WHERE status=paid',
                'current_balance' => 'investor_wallet_balances.capital_balance_afn + profit_balance_afn',
            ],
            'currencySections' => $multiCurrencySummary['currency_sections'],
            'exchangeValuation' => $multiCurrencySummary['exchange_valuation'],
            'legacy' => $multiCurrencySummary['legacy'],
            'limitations' => $multiCurrencySummary['limitations'],
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()
            ->route('users.create')
            ->with('success', 'Create a user and assign the Investor role. Then manage capital from Investor Details.');
    }

    public function store(): RedirectResponse
    {
        return redirect()
            ->route('users.create')
            ->with('error', 'Investor identity is managed in Users. Assign the investor role there, then add capital from Investor Details.');
    }

    public function storeCapital(StoreInvestmentRequest $request, Investor $investor): RedirectResponse
    {
        $this->authorize('update', $investor);

        $data = $request->validated();
        $data['user_id'] = $investor->id;

        $investment = $this->capital->deposit($data);

        return redirect()
            ->route('investors.show', $investor->id)
            ->with('success', "Cash capital #{$investment->id} recorded.");
    }

    public function show(Investor $investor): Response
    {
        $this->authorize('view', $investor);

        if ($investor->trash) {
            abort(404);
        }

        $investor->getOrCreateWallet();

        $investor->load([
            'investments' => fn ($q) => $q->with(['currency:id,code,name,symbol'])->latest(),
            'transactions' => fn ($q) => $q->with('currency:id,code,name,symbol')->latest(),
            'withdrawals' => fn ($q) => $q->with('currency:id,code,name,symbol')->latest(),
            'batches' => fn ($q) => $q->with(['variant.product:id,name', 'variant:id,product_id,name,volume_ml'])
                ->latest(),
            'investorWalletBalances',
        ]);

        $goods = $this->goodsTotalsByInvestor([$investor->id])[$investor->id] ?? ['held' => '0.00', 'sold' => '0.00'];
        $profitTotal = (float) $investor->transactions->where('type', 'profit_earned')->sum('amount_afn');
        $cashCapital = (float) $investor->transactions->where('type', 'capital_deposit')->sum('amount_afn');
        $goodsContribution = (float) $investor->transactions->where('type', 'goods_deposit')->sum('amount_afn');

        $stats = [
            'total_investments' => $investor->investments->count(),
            'active_investments' => $investor->investments->where('status', 'active')->count(),
            'total_transactions' => $investor->transactions->count(),
            'total_withdrawals' => $investor->withdrawals->count(),
            'total_batches' => $investor->batches->count(),
            'active_batches' => $investor->batches->where('remaining_quantity', '>', 0)->count(),
            'funded_purchases' => $investor->batches->count(),
            'realized_profit_afn' => Money::round((string) $profitTotal, 2),
            'paid_profit_afn' => Money::round((string) $profitTotal, 2),
            'pending_profit_afn' => '0.00',
            'cash_capital_afn' => Money::round((string) $cashCapital, 2),
            'goods_contribution_afn' => Money::round((string) $goodsContribution, 2),
            'goods_held_afn' => $goods['held'],
            'goods_sold_afn' => $goods['sold'],
            'has_cash_capital' => $investor->hasCashCapital(),
            'is_goods_investor' => $investor->isGoodsInvestor(),
            'active_goods_value_afn' => $investor->active_goods_value_afn,
            'active_goods_count' => $investor->active_goods_count,
            'available_cash_afn' => $investor->available_cash_afn,
        ];

        $multiCurrencySummary = $this->multiCurrency->summarize((int) $investor->id);
        $fundedPurchases = $this->buildFundedPurchases($investor);
        $funding = $this->buildFundingSummary($investor);

        // Attach empty profitEntries collection for older frontend that still reads it.
        $investor->setRelation('profitEntries', collect());
        $investor->setAttribute('wallet', $investor->getOrCreateWallet());

        return Inertia::render('investors/show', [
            'investor' => $investor,
            'stats' => $stats,
            'funding' => $funding,
            'funded_purchases' => $fundedPurchases,
            'currencies' => Currency::formOptions(),
            'sourceTypes' => [
                'cash' => 'Cash capital',
                'goods' => 'Goods contribution',
            ],
            'currencySections' => $multiCurrencySummary['currency_sections'],
            'exchangeValuation' => $multiCurrencySummary['exchange_valuation'],
            'multiCurrencyLegacy' => $multiCurrencySummary['legacy'],
            'multiCurrencyLimitations' => $multiCurrencySummary['limitations'],
        ]);
    }

    public function edit(Investor $investor): RedirectResponse
    {
        return redirect()
            ->route('users.edit', $investor->id)
            ->with('success', 'Investor identity is edited in Users.');
    }

    public function update(): RedirectResponse
    {
        return redirect()
            ->route('investors.index')
            ->with('error', 'Investor identity is managed in Users.');
    }

    public function destroy(Investor $investor): RedirectResponse
    {
        $this->authorize('delete', $investor);

        if ((float) $investor->available_cash_afn > 0.01) {
            return back()->with('error', 'Cannot deactivate investor with active capital balance. Close investments first.');
        }

        if ($investor->investments()->where('status', 'active')->exists()) {
            return back()->with('error', 'Cannot deactivate investor with active investments.');
        }

        $name = $investor->full_name;
        $investor->update([
            'trash' => true,
            'status' => 'inactive',
        ]);

        return redirect()
            ->route('investors.index')
            ->with('success', "Investor \"{$name}\" was deactivated. Financial history was preserved.");
    }

    public function restore(int $id): RedirectResponse
    {
        $investor = Investor::onlyTrashed()->findOrFail($id);
        $investor->update([
            'trash' => false,
            'status' => 'active',
        ]);

        return redirect()
            ->route('investors.index')
            ->with('success', "Investor \"{$investor->full_name}\" was restored.");
    }

    public function forceDelete(int $id): RedirectResponse
    {
        return redirect()
            ->route('investors.trash')
            ->with('error', 'Investor users cannot be permanently deleted from this module. Manage users in Users, and never discard financial history.');
    }

    public function trash(): Response
    {
        $investors = Investor::onlyTrashed()
            ->orderByDesc('updated_at')
            ->paginate(15);

        return Inertia::render('investors/trash', [
            'investors' => $investors,
        ]);
    }

    public function toggleStatus(Investor $investor): RedirectResponse
    {
        $this->authorize('update', $investor);

        $investor->update([
            'status' => $investor->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', "Investor status changed to {$investor->status}.");
    }

    /**
     * @return array{AFN: string, USD: string|null, AED: string|null}
     */
    private function centralRatesToAfn(): array
    {
        $rates = ['AFN' => '1', 'USD' => null, 'AED' => null];

        try {
            $board = app(ExchangeService::class)->getCurrentRates();
            $usd = (string) ($board['usd_to_afn'] ?? '0');
            $aed = (string) ($board['aed_to_afn'] ?? '0');
            $rates['USD'] = (float) $usd > 0 ? $usd : null;
            $rates['AED'] = (float) $aed > 0 ? $aed : null;
        } catch (\Throwable) {
            $service = app(ExchangeService::class);
            foreach (['USD', 'AED'] as $code) {
                try {
                    $rate = $service->getRate($code, 'AFN');
                    $rates[$code] = $rate > 0 ? sprintf('%.8F', $rate) : null;
                } catch (\Throwable) {
                    $rates[$code] = null;
                }
            }
        }

        return $rates;
    }

    /**
     * @param  list<int>  $investorIds
     * @return array<int, array{held:string,sold:string}>
     */
    private function goodsTotalsByInvestor(array $investorIds): array
    {
        if ($investorIds === []) {
            return [];
        }

        $rows = ProductBatch::query()
            ->selectRaw('investments.investor_id as investor_id')
            ->selectRaw('COALESCE(SUM(product_batches.remaining_quantity * product_batches.unit_cost_afn), 0) as held')
            ->selectRaw('COALESCE(SUM((product_batches.quantity - product_batches.remaining_quantity) * product_batches.unit_cost_afn), 0) as sold')
            ->join('investments', 'investments.id', '=', 'product_batches.investment_id')
            ->where('investments.trash', false)
            ->where('product_batches.trash', false)
            ->whereIn('investments.investor_id', $investorIds)
            ->groupBy('investments.investor_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->investor_id] = [
                'held' => Money::round((string) $row->held, 2),
                'sold' => Money::round((string) $row->sold, 2),
            ];
        }

        return $out;
    }

    private function globalGoodsHeld(): float
    {
        return (float) ProductBatch::query()
            ->where('trash', false)
            ->whereNotNull('investment_id')
            ->selectRaw('COALESCE(SUM(remaining_quantity * unit_cost_afn), 0) as total')
            ->value('total');
    }

    private function globalGoodsSold(): float
    {
        return (float) ProductBatch::query()
            ->where('trash', false)
            ->whereNotNull('investment_id')
            ->selectRaw('COALESCE(SUM((quantity - remaining_quantity) * unit_cost_afn), 0) as total')
            ->value('total');
    }

    /**
     * @param  list<int>  $investorIds
     * @return array<int, float>
     */
    private function profitTotalsByInvestor(array $investorIds): array
    {
        return $this->sumTxByInvestor($investorIds, 'profit_earned');
    }

    /**
     * @param  list<int>  $investorIds
     * @return array<int, float>
     */
    private function cashDepositTotalsByInvestor(array $investorIds): array
    {
        return $this->sumTxByInvestor($investorIds, 'capital_deposit');
    }

    /**
     * @param  list<int>  $investorIds
     * @return array<int, float>
     */
    private function goodsDepositTotalsByInvestor(array $investorIds): array
    {
        return $this->sumTxByInvestor($investorIds, 'goods_deposit');
    }

    /**
     * @param  list<int>  $investorIds
     * @return array<int, float>
     */
    private function withdrawalTotalsByInvestor(array $investorIds): array
    {
        if ($investorIds === []) {
            return [];
        }

        return InvestorWithdrawal::query()
            ->whereIn('investor_id', $investorIds)
            ->where('status', 'paid')
            ->selectRaw('investor_id, COALESCE(SUM(amount_afn), 0) as total')
            ->groupBy('investor_id')
            ->pluck('total', 'investor_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @param  list<int>  $investorIds
     * @return array<int, float>
     */
    private function sumTxByInvestor(array $investorIds, string $type): array
    {
        if ($investorIds === []) {
            return [];
        }

        $rows = InvestorTransaction::query()
            ->whereIn('investor_id', $investorIds)
            ->where('type', $type)
            ->selectRaw('investor_id, COALESCE(SUM(amount_afn), 0) as total')
            ->groupBy('investor_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->investor_id] = ['total' => Money::round((string) $row->total, 2)];
            if ($type !== 'profit_earned') {
                $out[(int) $row->investor_id] = (float) $row->total;
            }
        }

        if ($type === 'profit_earned') {
            return $out;
        }

        return $out;
    }

    private function buildFundingSummary(Investor $investor): array
    {
        $contributed = [];

        foreach ($investor->investments as $investment) {
            $code = $investment->currency?->code ?: 'AFN';
            $symbol = $investment->currency?->symbol ?: '؋';

            if (! isset($contributed[$code])) {
                $contributed[$code] = [
                    'currency_code' => $code,
                    'currency_symbol' => $symbol,
                    'decimal_places' => 2,
                    'original_total' => 0.0,
                    'ledger_afn' => 0.0,
                ];
            }

            $contributed[$code]['original_total'] = (float) Money::add(
                (string) $contributed[$code]['original_total'],
                (string) ($investment->amount_currency ?? 0),
                8
            );
            $contributed[$code]['ledger_afn'] = (float) Money::add(
                (string) $contributed[$code]['ledger_afn'],
                (string) ($investment->amount_afn ?? 0),
                8
            );
        }

        $wallet = $investor->getOrCreateWallet();

        return [
            'contributed_by_currency' => array_values($contributed),
            'unallocated_by_currency' => [],
            'current_balance_afn' => (float) Money::round(Money::add(
                (string) $wallet->capital_balance_afn,
                (string) $wallet->profit_balance_afn,
                8
            ), 2),
            'reporting_currency' => 'AFN',
            'reporting_note' => 'Ledger totals use recorded AFN amounts. Original currency totals are kept separate and are never mixed.',
        ];
    }

    private function buildFundedPurchases(Investor $investor): array
    {
        return ProductBatch::query()
            ->whereHas('investment', fn ($q) => $q->where('investor_id', $investor->id))
            ->where('trash', false)
            ->with(['variant:id,name,product_id,volume_ml', 'variant.product:id,name', 'investment:id,investor_id'])
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (ProductBatch $batch) {
                return [
                    'id' => $batch->id,
                    'purchase_date' => optional($batch->purchase_date)?->toDateString(),
                    'invoice_number' => $batch->invoice_number,
                    'product_name' => $batch->variant?->product?->name ?? $batch->variant?->name ?? '—',
                    'variant_name' => $batch->variant?->name,
                    'quantity' => (int) $batch->quantity,
                    'remaining_quantity' => (int) $batch->remaining_quantity,
                    'unit_cost_afn' => (float) $batch->unit_cost_afn,
                    'line_total_afn' => (float) $batch->total_cost_afn,
                    'original_currency' => $batch->purchaseCurrency?->code,
                    'original_line_amount' => (float) $batch->purchase_amount_currency,
                    'exchange_rate' => $batch->exchange_rate !== null ? (float) $batch->exchange_rate : null,
                    'link_type' => 'investment',
                    'investment_number' => $batch->investment_id ? ('INV-'.$batch->investment_id) : null,
                    'allocated_amount_afn' => (float) $batch->total_cost_afn,
                    'allocation_status' => 'allocated',
                ];
            })
            ->all();
    }
}
