<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestmentRequest;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentProductAllocation;
use App\Models\InvestmentPurchaseAllocation;
use App\Models\Investor;
use App\Models\InvestorProfitEntry;
use App\Models\Product;
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
 * This module lists investor users and their financial history only.
 *
 * KPI calculation sources (AFN ledger, no double counting):
 * - cash_capital_afn: SUM(investments.capital_amount_afn) WHERE source_type=cash
 * - goods_contribution_afn: SUM(investments.capital_amount_afn) WHERE source_type=goods
 * - goods_held_afn: SUM(allocations.remaining_quantity * cost_per_unit_afn)
 * - goods_sold_afn: SUM(allocations.sold_quantity * cost_per_unit_afn)
 * - total_investor_profit_afn: SUM(investor_profit_entries.profit_amount_afn) WHERE status in pending,paid
 * - paid_profit_afn: same table WHERE status=paid
 * - pending_profit_afn: same table WHERE status=pending
 * - total_withdrawals_afn: investor_wallets.total_withdrawn_afn
 * - current_balance_afn: capital_balance_afn + profit_balance_afn (wallet)
 */
class InvestorController extends Controller
{
    /** Currencies shown on the investors index (never cross-added in original amounts). */
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
            ->with([
                'wallet',
                'investorWalletBalances',
            ])
            ->withCount(['investments', 'withdrawals', 'batches', 'purchaseRows'])
            ->withSum([
                'investments as unallocated_capital_afn' => fn ($q) => $q
                    ->whereIn('status', ['active', 'partially_used']),
            ], 'available_amount_afn')
            ->withSum([
                'investments as cash_capital_afn' => fn ($q) => $q->where('source_type', 'cash')->where('trash', false),
            ], 'capital_amount_afn')
            ->withSum([
                'investments as goods_contribution_afn' => fn ($q) => $q->where('source_type', 'goods')->where('trash', false),
            ], 'capital_amount_afn')
            ->withSum([
                'profitEntries as total_investor_profit_afn' => fn ($q) => $q->whereIn('status', ['pending', 'paid']),
            ], 'profit_amount_afn')
            ->withSum([
                'profitEntries as paid_profit_afn' => fn ($q) => $q->where('status', 'paid'),
            ], 'profit_amount_afn')
            ->withSum([
                'profitEntries as pending_profit_afn' => fn ($q) => $q->where('status', 'pending'),
            ], 'profit_amount_afn')
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

        $goodsTotals = $this->goodsAllocationTotalsByInvestor(
            $investors->getCollection()->pluck('id')->all()
        );

        // Central board rates (units of AFN per 1 unit of currency). Null = no rate posted.
        $rates = $this->centralRatesToAfn();

        $investors->getCollection()->transform(function (Investor $investor) use ($goodsTotals, $rates) {
            $goods = $goodsTotals[$investor->id] ?? ['held' => '0.00', 'sold' => '0.00'];
            $balances = $investor->investorWalletBalances->keyBy(fn ($row) => strtoupper((string) $row->currency_code));

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

                // Equivalent at the central rate; without a posted rate fall back to the ledger snapshot.
                $capitalAfnEq = $rate !== null
                    ? Money::round(Money::mul($capitalCurrency, (string) $rate, 8), 2)
                    : Money::round($capitalLedgerAfn, 2);
                $profitAfnEq = $rate !== null
                    ? Money::round(Money::mul($profitCurrency, (string) $rate, 8), 2)
                    : Money::round($profitLedgerAfn, 2);
                $balanceAfnEq = Money::round(Money::add($capitalAfnEq, $profitAfnEq, 8), 2);

                $totalAfnEquivalent = Money::add($totalAfnEquivalent, $balanceAfnEq, 8);

                $wallet[$code] = [
                    // Ledger AFN snapshot (rate at the time of each transaction)
                    'capital' => (float) $capitalLedgerAfn,
                    'profit' => (float) $profitLedgerAfn,
                    // Original currency amounts
                    'capital_currency' => (float) $capitalCurrency,
                    'profit_currency' => (float) $profitCurrency,
                    'balance_currency' => (float) Money::round(Money::add($capitalCurrency, $profitCurrency, 8), 2),
                    'last_rate' => $lastRate,
                    // AFN equivalent at today's central rate
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
                // Counts + AFN ledger figures shown in the expanded detail panel
                'investments_count' => (int) $investor->investments_count,
                'withdrawals_count' => (int) $investor->withdrawals_count,
                'batches_count' => (int) $investor->batches_count,
                'ledger' => [
                    'cash_capital_afn' => (float) Money::round((string) ($investor->cash_capital_afn ?? '0'), 2),
                    'goods_contribution_afn' => (float) Money::round((string) ($investor->goods_contribution_afn ?? '0'), 2),
                    'goods_held_afn' => (float) $goods['held'],
                    'goods_sold_afn' => (float) $goods['sold'],
                    'total_profit_afn' => (float) Money::round((string) ($investor->total_investor_profit_afn ?? '0'), 2),
                    'paid_profit_afn' => (float) Money::round((string) ($investor->paid_profit_afn ?? '0'), 2),
                    'pending_profit_afn' => (float) Money::round((string) ($investor->pending_profit_afn ?? '0'), 2),
                    'total_withdrawals_afn' => (float) Money::round((string) ($investor->wallet?->total_withdrawn_afn ?? '0'), 2),
                ],
            ];
        });

        $stats = [
            'total_investors' => Investor::notTrashed()->count(),
            'active_investors' => Investor::notTrashed()->where('status', 'active')->count(),
            // Source: investments.capital_amount_afn (cash) — contributed cash capital, not wallet residual
            'cash_capital' => Money::round((string) Investment::notTrashed()->where('source_type', 'cash')->sum('capital_amount_afn'), 2),
            // Source: investments.capital_amount_afn (goods)
            'goods_contribution' => Money::round((string) Investment::notTrashed()->where('source_type', 'goods')->sum('capital_amount_afn'), 2),
            // Source: investment_product_allocations remaining/sold × cost
            'goods_held' => Money::round((string) InvestmentProductAllocation::query()
                ->whereHas('investment', fn ($q) => $q->where('trash', false))
                ->selectRaw('COALESCE(SUM(remaining_quantity * cost_per_unit_afn), 0) as total')
                ->value('total'), 2),
            'goods_sold' => Money::round((string) InvestmentProductAllocation::query()
                ->whereHas('investment', fn ($q) => $q->where('trash', false))
                ->selectRaw('COALESCE(SUM(sold_quantity * cost_per_unit_afn), 0) as total')
                ->value('total'), 2),
            // Source: investor_profit_entries (pending+paid) — avoids wallet lifetime vs paid confusion
            'total_investor_profit' => Money::round((string) InvestorProfitEntry::query()
                ->whereIn('status', ['pending', 'paid'])
                ->sum('profit_amount_afn'), 2),
            'paid_profit' => Money::round((string) InvestorProfitEntry::query()
                ->where('status', 'paid')
                ->sum('profit_amount_afn'), 2),
            'pending_profit' => Money::round((string) InvestorProfitEntry::query()
                ->where('status', 'pending')
                ->sum('profit_amount_afn'), 2),
            // Source: investor_wallets.total_withdrawn_afn
            'total_withdrawals' => Money::round((string) DB::table('investor_wallets')->sum('total_withdrawn_afn'), 2),
            // Source: wallet capital_balance_afn + profit_balance_afn
            'current_balance' => Money::round(Money::add(
                (string) DB::table('investor_wallets')->sum('capital_balance_afn'),
                (string) DB::table('investor_wallets')->sum('profit_balance_afn'),
                8
            ), 2),
            // Kept for backward-compatible cards
            'total_capital' => Money::round((string) DB::table('investor_wallets')->sum('capital_balance_afn'), 2),
            'total_unallocated' => Money::round((string) Investment::query()
                ->where('trash', false)
                ->whereIn('status', ['active', 'partially_used'])
                ->sum('available_amount_afn'), 2),
        ];

        // Portfolio-wide original-currency sections (AFN/USD/AED never cross-added).
        $multiCurrencySummary = $this->multiCurrency->summarize(null);

        return Inertia::render('investors/index', [
            'investors' => $investors,
            'filters'   => [
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
                'cash_capital' => 'investments.capital_amount_afn WHERE source_type=cash',
                'goods_contribution' => 'investments.capital_amount_afn WHERE source_type=goods',
                'goods_held' => 'investment_product_allocations.remaining_quantity * cost_per_unit_afn',
                'goods_sold' => 'investment_product_allocations.sold_quantity * cost_per_unit_afn',
                'total_investor_profit' => 'investor_profit_entries.profit_amount_afn WHERE status IN (pending,paid)',
                'paid_profit' => 'investor_profit_entries.profit_amount_afn WHERE status=paid',
                'pending_profit' => 'investor_profit_entries.profit_amount_afn WHERE status=pending',
                'total_withdrawals' => 'investor_wallets.total_withdrawn_afn',
                'current_balance' => 'investor_wallets.capital_balance_afn + profit_balance_afn',
            ],
            'currencySections' => $multiCurrencySummary['currency_sections'],
            'exchangeValuation' => $multiCurrencySummary['exchange_valuation'],
            'legacy' => $multiCurrencySummary['legacy'],
            'limitations' => $multiCurrencySummary['limitations'],
        ]);
    }

    /**
     * Identity registration moved to Users module.
     */
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

    /**
     * Add cash capital or goods contribution from Investor Details.
     */
    public function storeCapital(StoreInvestmentRequest $request, Investor $investor): RedirectResponse
    {
        $this->authorize('update', $investor);

        $data = $request->validated();
        $data['user_id'] = $investor->id;

        $investment = $this->capital->deposit($data);

        $label = $investment->source_type === 'goods' ? 'Goods contribution' : 'Cash capital';

        return redirect()
            ->route('investors.show', $investor->id)
            ->with('success', "{$label} \"{$investment->investment_number}\" recorded.");
    }

    public function show(Investor $investor): Response
    {
        $this->authorize('view', $investor);

        if ($investor->trash) {
            abort(404);
        }

        if (! $investor->wallet) {
            $investor->getOrCreateWallet();
        }

        $investor->load([
            'wallet.currency:id,code,name,symbol,decimal_places',
            'creator:id,name,email',
            'investments' => fn ($q) => $q
                ->with([
                    'currency:id,code,name,symbol,decimal_places',
                    'purchaseAllocations.purchase:id,name,invoice_number,purchase_date,quantity,remaining_quantity',
                    'purchaseAllocations.purchase.purchaseBatches',
                    'purchaseAllocations.purchase.supplier:id,name',
                    'purchaseAllocations.purchase.variant:id,name,product_id,volume_ml',
                    'purchaseAllocations.purchase.variant.product:id,name',
                ])
                ->latest(),
            'transactions' => fn ($q) => $q->with('currency:id,code,name,symbol,decimal_places')->latest(),
            'withdrawals' => fn ($q) => $q->with('currency:id,code,name,symbol,decimal_places')->latest(),
            'profitEntries' => fn ($q) => $q
                ->with([
                    'currency:id,code,name,symbol,decimal_places',
                    'sale:id,invoice_number,sale_date',
                    'saleItem:id,sale_id,quantity,unit_cost_afn,investor_profit_afn',
                ])
                ->latest(),
            'batches' => fn ($q) => $q->with(['variant.product:id,name', 'variant:id,product_id,name,volume_ml'])
                                       ->latest(),
        ]);

        $funding = $this->buildFundingSummary($investor);
        $fundedPurchases = $this->buildFundedPurchases($investor);

        $paidProfitAfn = Money::round(
            (string) $investor->profitEntries->where('status', 'paid')->sum('profit_amount_afn'),
            2
        );
        $pendingProfitAfn = Money::round(
            (string) $investor->profitEntries->where('status', 'pending')->sum('profit_amount_afn'),
            2
        );
        $realizedProfitAfn = Money::round(Money::add($paidProfitAfn, $pendingProfitAfn, 8), 2);

        $goodsTotals = $this->goodsAllocationTotalsByInvestor([$investor->id]);
        $goods = $goodsTotals[$investor->id] ?? ['held' => '0.00', 'sold' => '0.00'];

        $cashCapital = Money::round(
            (string) $investor->investments->where('source_type', 'cash')->sum('capital_amount_afn'),
            2
        );
        $goodsContribution = Money::round(
            (string) $investor->investments->where('source_type', 'goods')->sum('capital_amount_afn'),
            2
        );

        $stats = [
            'total_investments'  => $investor->investments->count(),
            'active_investments' => $investor->investments->whereIn('status', ['active', 'partially_used'])->count(),
            'total_transactions' => $investor->transactions->count(),
            'total_withdrawals'  => $investor->withdrawals->count(),
            'total_batches'      => $investor->batches->count(),
            'active_batches'     => $investor->batches->where('remaining_quantity', '>', 0)->count(),
            'funded_purchases'   => count($fundedPurchases),
            'realized_profit_afn'=> $realizedProfitAfn,
            'paid_profit_afn'    => $paidProfitAfn,
            'pending_profit_afn' => $pendingProfitAfn,
            'cash_capital_afn'   => $cashCapital,
            'goods_contribution_afn' => $goodsContribution,
            'goods_held_afn'     => $goods['held'],
            'goods_sold_afn'     => $goods['sold'],
            'has_cash_capital'       => $investor->hasCashCapital(),
            'is_goods_investor'      => $investor->isGoodsInvestor(),
            'active_goods_value_afn' => $investor->active_goods_value_afn,
            'active_goods_count'     => $investor->active_goods_count,
            'available_cash_afn'     => $investor->available_cash_afn,
        ];

        $multiCurrencySummary = $this->multiCurrency->summarize((int) $investor->id);

        return Inertia::render('investors/show', [
            'investor'         => $investor,
            'stats'            => $stats,
            'funding'          => $funding,
            'funded_purchases' => $fundedPurchases,
            'currencies'       => Currency::formOptions(),
            'sourceTypes'      => Investment::SOURCE_TYPES,
            'currencySections' => $multiCurrencySummary['currency_sections'],
            'exchangeValuation' => $multiCurrencySummary['exchange_valuation'],
            'multiCurrencyLegacy' => $multiCurrencySummary['legacy'],
            'multiCurrencyLimitations' => $multiCurrencySummary['limitations'],
        ]);
    }

    /**
     * @param  list<int>  $investorIds
     * @return array<int, array{held:string,sold:string}>
     */
    /**
     * Current central rates to AFN from ExchangeService (exchange_rates table).
     * A currency without a posted rate is null so callers can fall back to the ledger snapshot.
     *
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
            // getCurrentRates() requires both pairs; resolve each independently when the board is incomplete.
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

    protected function goodsAllocationTotalsByInvestor(array $investorIds): array
    {
        if ($investorIds === []) {
            return [];
        }

        $rows = InvestmentProductAllocation::query()
            ->selectRaw('investments.investor_id as investor_id')
            ->selectRaw('COALESCE(SUM(remaining_quantity * cost_per_unit_afn), 0) as held')
            ->selectRaw('COALESCE(SUM(sold_quantity * cost_per_unit_afn), 0) as sold')
            ->join('investments', 'investments.id', '=', 'investment_product_allocations.investment_id')
            ->where('investments.trash', false)
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

    protected function buildFundingSummary(Investor $investor): array
    {
        $contributed = [];
        $unallocated = [];

        foreach ($investor->investments as $investment) {
            $code = $investment->currency_code ?: ($investment->currency?->code ?: 'AFN');
            $symbol = $investment->currency_symbol ?: ($investment->currency?->symbol ?: '؋');
            $decimals = (int) ($investment->currency?->decimal_places ?? 2);

            if (! isset($contributed[$code])) {
                $contributed[$code] = [
                    'currency_code'   => $code,
                    'currency_symbol' => $symbol,
                    'decimal_places'  => $decimals,
                    'original_total'  => 0.0,
                    'ledger_afn'      => 0.0,
                ];
            }

            $original = $investment->capital_amount_currency !== null
                ? (float) $investment->capital_amount_currency
                : (float) $investment->capital_amount_afn;

            $contributed[$code]['original_total'] = (float) Money::add(
                (string) $contributed[$code]['original_total'],
                (string) $original,
                8
            );
            $contributed[$code]['ledger_afn'] = (float) Money::add(
                (string) $contributed[$code]['ledger_afn'],
                (string) $investment->capital_amount_afn,
                8
            );

            if (in_array($investment->status, ['active', 'partially_used'], true)) {
                if (! isset($unallocated[$code])) {
                    $unallocated[$code] = [
                        'currency_code'   => $code,
                        'currency_symbol' => $symbol,
                        'decimal_places'  => $decimals,
                        'original_total'  => 0.0,
                        'ledger_afn'      => 0.0,
                    ];
                }

                $availableOriginal = $investment->available_amount_currency !== null
                    ? (float) $investment->available_amount_currency
                    : (float) $investment->available_amount_afn;

                $unallocated[$code]['original_total'] = (float) Money::add(
                    (string) $unallocated[$code]['original_total'],
                    (string) $availableOriginal,
                    8
                );
                $unallocated[$code]['ledger_afn'] = (float) Money::add(
                    (string) $unallocated[$code]['ledger_afn'],
                    (string) $investment->available_amount_afn,
                    8
                );
            }
        }

        return [
            'contributed_by_currency' => array_values($contributed),
            'unallocated_by_currency' => array_values($unallocated),
            'current_balance_afn'     => (float) Money::round(Money::add(
                (string) ($investor->wallet?->capital_balance_afn ?? 0),
                (string) ($investor->wallet?->profit_balance_afn ?? 0),
                8
            ), 2),
            'reporting_currency'      => 'AFN',
            'reporting_note'          => 'Ledger totals use recorded AFN amounts. Original currency totals are kept separate and are never mixed.',
        ];
    }

    protected function buildFundedPurchases(Investor $investor): array
    {
        $rows = [];
        $seenPurchaseIds = [];

        $directBatches = ProductBatch::query()
            ->where('investor_id', $investor->id)
            ->where('trash', false)
            ->with([
                'purchaseItem:id,name,invoice_number,purchase_date,quantity,remaining_quantity',
                'variant:id,name,product_id,volume_ml',
                'variant.product:id,name',
            ])
            ->orderByDesc('received_date')
            ->get();

        foreach ($directBatches as $batch) {
            $purchase = $batch->purchaseItem;
            if (! $purchase) {
                continue;
            }
            $seenPurchaseIds[$purchase->id] = true;
            $purchase->setAttribute('variant_id', $batch->variant_id);
            $purchase->setAttribute('investor_id', $batch->investor_id);
            $purchase->setAttribute('purchase_price_afn', $batch->total_cost_afn);
            $purchase->setAttribute('line_total', $batch->total_cost_afn);
            $purchase->setRelation('variant', $batch->variant);
            $rows[] = $this->mapFundedPurchase($purchase, 'investor_id', null, null);
        }

        $allocations = InvestmentPurchaseAllocation::query()
            ->whereHas('investment', fn ($q) => $q->where('investor_id', $investor->id))
            ->with([
                'investment:id,investment_number,investor_id,currency_code,currency_symbol,exchange_rate',
                'purchase:id,name,invoice_number,purchase_date,quantity,remaining_quantity',
                'purchase.purchaseBatches.variant:id,name,product_id,volume_ml',
                'purchase.purchaseBatches.variant.product:id,name',
            ])
            ->orderByDesc('created_at')
            ->get();

        foreach ($allocations as $allocation) {
            $purchase = $allocation->purchase;
            if (! $purchase || isset($seenPurchaseIds[$purchase->id])) {
                continue;
            }

            $seenPurchaseIds[$purchase->id] = true;
            $rows[] = $this->mapFundedPurchase(
                $purchase,
                'allocation',
                $allocation->investment?->investment_number,
                (float) $allocation->allocated_amount_afn,
            );
        }

        return $rows;
    }

    protected function mapFundedPurchase(
        Product $purchase,
        string $linkType,
        ?string $investmentNumber,
        ?float $allocatedAfn,
    ): array {
        $productName = $purchase->variant?->product?->name
            ?? $purchase->variant?->name
            ?? $purchase->name
            ?? '—';

        $hasReliableLink = $linkType === 'investor_id' || $linkType === 'allocation';

        $batch = $purchase->relationLoaded('purchaseBatches')
            ? $purchase->purchaseBatches->first()
            : null;

        return [
            'id'                    => $purchase->id,
            'purchase_date'         => optional($purchase->purchase_date)?->toDateString(),
            'invoice_number'        => $purchase->invoice_number,
            'product_name'          => $productName,
            'variant_name'          => $purchase->variant?->name,
            'quantity'              => (int) $purchase->quantity,
            'remaining_quantity'    => (int) $purchase->remaining_quantity,
            'unit_cost_afn'         => (float) ($purchase->getAttribute('purchase_price_afn') ?? $batch?->unit_cost_afn ?? 0),
            'line_total_afn'        => (float) ($purchase->getAttribute('line_total') ?? $batch?->total_cost_afn ?? 0),
            'original_currency'     => $batch?->profit_currency_code,
            'original_currency_symbol' => null,
            'original_line_amount'  => $batch?->unit_cost_currency !== null
                ? (float) $batch->unit_cost_currency * (int) $purchase->quantity
                : null,
            'exchange_rate'         => $batch?->exchange_rate !== null ? (float) $batch->exchange_rate : null,
            'supplier_name'         => $purchase->getAttribute('supplier_name'),
            'link_type'             => $linkType,
            'investment_number'     => $investmentNumber,
            'allocated_amount_afn'  => $allocatedAfn,
            'allocation_status'     => $hasReliableLink ? 'allocated' : 'unallocated',
        ];
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

        if ($investor->wallet && (float) $investor->wallet->capital_balance_afn > 0.01) {
            return back()->with('error', 'Cannot deactivate investor with active capital balance. Close investments first.');
        }

        if ($investor->investments()->whereIn('status', ['active', 'partially_used'])->exists()) {
            return back()->with('error', 'Cannot deactivate investor with active investments.');
        }

        $name = $investor->full_name;

        // Soft-deactivate the user identity — never delete financial history.
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
            ->with(['wallet', 'creator:id,name'])
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
}
