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
use App\Services\ExchangeRateService;
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

    public function show(Investor $investor, ExchangeRateService $rates): Response
    {
        $this->authorize('view', $investor);

        if ($investor->trash) {
            abort(404);
        }

        $investor->load([
            'investments' => fn ($q) => $q->latest('investment_date'),
            'investments.currency:id,code,symbol',
            'investments.batches' => fn ($q) => $q->where('status', 'active'),
            'investments.batches.variant.product:id,name,brand_id',
            'investments.batches.variant.product.brand:id,name',
            'investorWalletBalances.currency:id,code,symbol',
            'transactions' => fn ($q) => $q->latest('transaction_date')->limit(100),
            'transactions.currency:id,code,symbol',
            'withdrawals' => fn ($q) => $q->latest('withdrawal_date')->limit(50),
            'withdrawals.currency:id,code,symbol',
        ]);

        // ─── KPIs (all AFN; frontend builds USD/AED) ───
        $totals = [
            'capital_afn' => (float) $investor->investorWalletBalances->sum('capital_balance_afn'),
            'profit_afn' => (float) $investor->investorWalletBalances->sum('profit_balance_afn'),
            'invested_afn' => (float) $investor->investments->where('status', 'active')->sum('amount_afn'),
            'withdrawn_afn' => (float) $investor->transactions
                ->whereIn('type', ['capital_withdrawn', 'profit_withdrawn'])
                ->sum(fn ($t) => abs((float) $t->amount_afn)),
        ];

        // ─── Active inventory (summary only) ───
        $activeBatches = $investor->investments
            ->flatMap(fn ($inv) => $inv->batches)
            ->where('remaining_quantity', '>', 0);

        $inventory = [
            'quantity' => (int) $activeBatches->sum('remaining_quantity'),
            'cost_afn' => (float) $activeBatches->sum(fn ($b) =>
                (float) $b->unit_cost_afn * (int) $b->remaining_quantity
            ),
            'sale_value_afn' => (float) $activeBatches->sum(fn ($b) =>
                (float) $b->sale_price_afn * (int) $b->remaining_quantity
            ),
            'batch_count' => $activeBatches->count(),
        ];
        $inventory['potential_profit_afn'] = max(0, $inventory['sale_value_afn'] - $inventory['cost_afn']);

        // ─── Wallet by currency (only if > 0) ───
        $walletByCurrency = $investor->investorWalletBalances
            ->filter(fn ($w) => (float) $w->capital_balance_afn > 0 || (float) $w->profit_balance_afn > 0)
            ->map(fn ($w) => [
                'currency_code' => $w->currency_code,
                'currency_symbol' => $w->currency?->symbol,
                'capital_currency' => (float) $w->capital_balance_currency,
                'capital_afn' => (float) $w->capital_balance_afn,
                'profit_currency' => (float) $w->profit_balance_currency,
                'profit_afn' => (float) $w->profit_balance_afn,
            ])
            ->values();

        return Inertia::render('investors/show', [
            'investor' => [
                'id' => $investor->id,
                'name' => $investor->name,
                'email' => $investor->email,
                'phone' => $investor->phone,
                'status' => $investor->status ?? 'active',
                'created_at' => $investor->created_at?->toIso8601String(),
            ],
            'totals' => $totals,
            'inventory' => $inventory,
            'walletByCurrency' => $walletByCurrency,
            'investments' => $investor->investments->map(fn ($inv) => [
                'id' => $inv->id,
                'currency_code' => $inv->currency?->code,
                'exchange_rate' => (float) $inv->exchange_rate,
                'amount_currency' => (float) $inv->amount_currency,
                'amount_afn' => (float) $inv->amount_afn,
                'investment_date' => $inv->investment_date?->toDateString(),
                'status' => $inv->status,
                'batches_count' => $inv->batches->count(),
            ])->values(),
            'transactions' => $investor->transactions->map(fn ($t) => [
                'id' => $t->id,
                'date' => $t->transaction_date?->toDateString(),
                'type' => $t->type,
                'currency_code' => $t->currency?->code ?? $t->currency_code,
                'amount_afn' => (float) $t->amount_afn,
                'balance_after_afn' => (float) $t->balance_after_afn,
                'description' => $t->description,
            ])->values(),
            'withdrawals' => $investor->withdrawals->map(fn ($w) => [
                'id' => $w->id,
                'withdrawal_number' => $w->withdrawal_number,
                'type' => $w->type,
                'currency_code' => $w->currency?->code ?? $w->currency_code,
                'amount_afn' => (float) $w->amount_afn,
                'withdrawal_date' => $w->withdrawal_date?->toDateString(),
                'status' => $w->status,
            ])->values(),
            // flatBoard so page prop matches shared useExchange() shape (not nested board()).
            'exchange' => $rates->flatBoard(),
            'currencies' => Currency::formOptions(),
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

}
