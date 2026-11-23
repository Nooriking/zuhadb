<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorTransaction;
use App\Models\InvestorWalletBalance;
use App\Models\InvestorWithdrawal;
use App\Models\SaleItem;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvestorDashboardController extends Controller
{
    public function __construct(
        private readonly ExchangeRateService $rates,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $own = (bool) $user?->isInvestor();
        $investorId = $own
            ? (int) $user->id
            : ($request->filled('investor_id') ? (int) $request->investor_id : null);

        $exchange = $this->rates->board();
        $tri = fn (float $afn): array => $this->rates->threeFromAfn($afn);

        // Section 1: Wallet + investment KPIs (aggregated SQL)

        $wallet = InvestorWalletBalance::query()
            ->when($investorId, fn ($q) => $q->where('investor_id', $investorId))
            ->selectRaw('
                COALESCE(SUM(capital_balance_afn), 0) as capital_afn,
                COALESCE(SUM(profit_balance_afn), 0) as profit_afn
            ')
            ->first();

        $totalCapital = (float) ($wallet->capital_afn ?? 0);
        $totalProfit = (float) ($wallet->profit_afn ?? 0);

        $totalInvested = (float) Investment::query()
            ->notTrashed()
            ->when($investorId, fn ($q) => $q->where('investor_id', $investorId))
            ->sum('amount_afn');

        $txTotals = InvestorTransaction::query()
            ->when($investorId, fn ($q) => $q->where('investor_id', $investorId))
            ->whereIn('type', ['capital_withdrawn', 'profit_withdrawn', 'profit_earned'])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type IN ('capital_withdrawn','profit_withdrawn') THEN ABS(amount_afn) ELSE 0 END), 0) as withdrawn_afn,
                COALESCE(SUM(CASE WHEN type = 'profit_earned' THEN amount_afn ELSE 0 END), 0) as profit_earned_afn,
                COALESCE(SUM(CASE WHEN type = 'profit_withdrawn' THEN ABS(amount_afn) ELSE 0 END), 0) as profit_paid_afn
            ")
            ->first();

        $totalWithdrawn = (float) ($txTotals->withdrawn_afn ?? 0);
        $totalProfitEarnedActual = (float) ($txTotals->profit_earned_afn ?? 0);
        $totalProfitPaid = (float) ($txTotals->profit_paid_afn ?? 0);

        // Section 2: Weighted average investment FX
        // avg = Σ amount_afn / Σ amount_currency  (per currency)

        $averageRates = $this->rates->investmentWeightedAverages($investorId);

        // Section 3: Inventory — SQL aggregate only (no model hydrate)

        $inventoryAgg = DB::table('product_batches')
            ->join('investments', 'investments.id', '=', 'product_batches.investment_id')
            ->where('product_batches.trash', false)
            ->whereNull('product_batches.deleted_at')
            ->where('investments.trash', false)
            ->whereNull('investments.deleted_at')
            ->when($investorId, fn ($q) => $q->where('investments.investor_id', $investorId))
            ->where('product_batches.status', 'active')
            ->where('product_batches.remaining_quantity', '>', 0)
            ->selectRaw('
                COALESCE(SUM(product_batches.remaining_quantity * product_batches.unit_cost_afn), 0) as cost_afn,
                COALESCE(SUM(product_batches.remaining_quantity * product_batches.sale_price_afn), 0) as sale_afn,
                COALESCE(SUM(product_batches.remaining_quantity), 0) as qty,
                COUNT(*) as batch_count,
                COUNT(DISTINCT product_batches.variant_id) as variant_count
            ')
            ->first();

        $inventoryCostAfn = (float) ($inventoryAgg->cost_afn ?? 0);
        $inventorySaleValueAfn = (float) ($inventoryAgg->sale_afn ?? 0);
        $potentialProfitAfn = max(0, $inventorySaleValueAfn - $inventoryCostAfn);
        $inventoryQuantity = (int) ($inventoryAgg->qty ?? 0);
        $inventoryBatchCount = (int) ($inventoryAgg->batch_count ?? 0);
        $inventoryVariantCount = (int) ($inventoryAgg->variant_count ?? 0);

        // ═══════════════════════════════════════════════
        // Section 4: Goods list — only when a specific investor is selected
        // (admin with no filter: skip the heavy list)
        // ═══════════════════════════════════════════════

        $inventoryItems = ($investorId || $own)
            ? $this->inventoryItemsFor($investorId)
            : [];

        // ═══════════════════════════════════════════════
        // Section 5: Monthly / byCurrency / top / recent
        // ═══════════════════════════════════════════════

        $monthly = InvestorTransaction::query()
            ->when($investorId, fn ($q) => $q->where('investor_id', $investorId))
            ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as ym, type, SUM(ABS(amount_afn)) as total")
            ->where('transaction_date', '>=', now()->subMonths(12))
            ->groupBy('ym', 'type')
            ->orderBy('ym')
            ->get()
            ->groupBy('ym')
            ->map(fn ($group, $ym) => [
                'month' => $ym,
                'deposits' => (float) $group->whereIn('type', ['capital_deposit', 'goods_deposit'])->sum('total'),
                'profits' => (float) $group->where('type', 'profit_earned')->sum('total'),
                'withdrawals' => (float) $group->whereIn('type', ['capital_withdrawn', 'profit_withdrawn'])->sum('total'),
            ])
            ->values();

        $byCurrency = InvestorWalletBalance::query()
            ->when($investorId, fn ($q) => $q->where('investor_id', $investorId))
            ->select('currency_id', 'currency_code')
            ->selectRaw('SUM(capital_balance_afn) as capital_afn, SUM(profit_balance_afn) as profit_afn, SUM(capital_balance_currency) as capital_curr, SUM(profit_balance_currency) as profit_curr')
            ->groupBy('currency_id', 'currency_code')
            ->get()
            ->map(fn ($r) => [
                'currency_code' => $r->currency_code,
                'capital_afn' => (float) $r->capital_afn,
                'profit_afn' => (float) $r->profit_afn,
                'capital_currency' => (float) $r->capital_curr,
                'profit_currency' => (float) $r->profit_curr,
            ]);

        $topInvestors = $own
            ? []
            : Investor::query()
                ->notTrashed()
                ->select('users.id', 'users.name')
                ->withSum(['investments as total_invested_afn' => fn ($q) => $q->where('trash', false)], 'amount_afn')
                ->with(['investorWalletBalances:id,investor_id,capital_balance_afn,profit_balance_afn'])
                ->orderByDesc('total_invested_afn')
                ->limit(10)
                ->get()
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'total_invested_afn' => (float) ($u->total_invested_afn ?? 0),
                    'capital_afn' => (float) $u->investorWalletBalances->sum('capital_balance_afn'),
                    'profit_afn' => (float) $u->investorWalletBalances->sum('profit_balance_afn'),
                ])
                ->values();

        $recentTransactions = InvestorTransaction::query()
            ->when($investorId, fn ($q) => $q->where('investor_id', $investorId))
            ->with(['investor:id,name', 'currency:id,code'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'investor_id', 'currency_id', 'type', 'amount_afn', 'balance_after_afn', 'transaction_date'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'date' => $t->transaction_date?->toDateString(),
                'investor' => $t->investor?->name,
                'type' => $t->type,
                'currency_code' => $t->currency?->code,
                'amount_afn' => (float) $t->amount_afn,
                'balance_after_afn' => (float) $t->balance_after_afn,
            ]);

        $pendingWithdrawals = InvestorWithdrawal::query()
            ->when($investorId, fn ($q) => $q->where('investor_id', $investorId))
            ->where('status', 'pending')
            ->with(['investor:id,name', 'currency:id,code'])
            ->latest('id')
            ->limit(10)
            ->get(['id', 'withdrawal_number', 'investor_id', 'currency_id', 'type', 'amount_afn', 'withdrawal_date'])
            ->map(fn ($w) => [
                'id' => $w->id,
                'withdrawal_number' => $w->withdrawal_number,
                'investor' => $w->investor?->name,
                'type' => $w->type,
                'amount_afn' => (float) $w->amount_afn,
                'currency_code' => $w->currency?->code,
                'withdrawal_date' => $w->withdrawal_date?->toDateString(),
            ]);

        return Inertia::render('investors/dashboard', [
            'kpis' => [
                'total_capital_afn' => $totalCapital,
                'total_profit_afn' => $totalProfit,
                'total_invested_afn' => $totalInvested,
                'total_withdrawn_afn' => $totalWithdrawn,
                'total_profit_earned_actual_afn' => $totalProfitEarnedActual,
                'total_profit_paid_afn' => $totalProfitPaid,
                'inventory_cost_afn' => $inventoryCostAfn,
                'inventory_sale_value_afn' => $inventorySaleValueAfn,
                'potential_profit_afn' => $potentialProfitAfn,
                'inventory_quantity' => $inventoryQuantity,
                'inventory_batch_count' => $inventoryBatchCount,
                'inventory_variant_count' => $inventoryVariantCount,
                'total_investors' => $own ? 1 : Investor::query()->notTrashed()->count(),
            ],
            'kpis3c' => [
                'total_capital' => $tri($totalCapital),
                'total_profit' => $tri($totalProfit),
                'total_invested' => $tri($totalInvested),
                'total_withdrawn' => $tri($totalWithdrawn),
                'total_profit_earned_actual' => $tri($totalProfitEarnedActual),
                'total_profit_paid' => $tri($totalProfitPaid),
                'inventory_cost' => $tri($inventoryCostAfn),
                'inventory_sale_value' => $tri($inventorySaleValueAfn),
                'potential_profit' => $tri($potentialProfitAfn),
            ],
            'averageRates' => $averageRates,
            'inventoryItems' => $inventoryItems,
            'monthly' => $monthly,
            'byCurrency' => $byCurrency,
            'topInvestors' => $topInvestors,
            'recentTransactions' => $recentTransactions,
            'pendingWithdrawals' => $pendingWithdrawals,
            'investors' => $own
                ? []
                : Investor::query()->notTrashed()->select('id', 'name')->orderBy('name')->get(),
            'exchange' => $exchange,
            // Alias used by three-currency dashboard tests / shared frontend helpers.
            'fxBoard' => $this->rates->flatBoard(),
            'own' => $own,
            'filters' => ['investor_id' => $investorId],
            'dashboardPath' => $own ? '/dashboard' : '/investors/dashboard',
        ]);
    }

    /**
     * Full investor goods list in one SQL join (profit via left-joined subquery).
     * Sold qty = quantity − remaining (no inventory_transactions scan).
     *
     * @return list<array<string, mixed>>
     */
    private function inventoryItemsFor(?int $investorId): array
    {
        $profitSub = DB::table('investor_transactions as it')
            ->join('sale_items as si', function ($join) {
                $join->on('si.id', '=', 'it.reference_id')
                    ->where('it.reference_type', '=', SaleItem::class);
            })
            ->where('it.type', 'profit_earned')
            ->groupBy('si.batch_id')
            ->selectRaw('si.batch_id, SUM(it.amount_afn) as profit');

        return DB::table('product_batches as pb')
            ->join('investments as inv', 'inv.id', '=', 'pb.investment_id')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'pb.variant_id')
            ->leftJoin('products as p', 'p.id', '=', 'pv.product_id')
            ->leftJoin('currencies as pc', 'pc.id', '=', 'pb.purchase_currency_id')
            ->leftJoinSub($profitSub, 'bp', 'bp.batch_id', '=', 'pb.id')
            ->where('pb.trash', false)
            ->whereNull('pb.deleted_at')
            ->where('inv.trash', false)
            ->whereNull('inv.deleted_at')
            ->when($investorId, fn ($q) => $q->where('inv.investor_id', $investorId))
            ->orderByDesc('pb.received_date')
            ->orderByDesc('pb.id')
            ->select([
                'pb.id',
                'pb.batch_code',
                'pb.variant_id',
                'pb.status',
                'pb.quantity',
                'pb.remaining_quantity',
                'pb.unit_cost_afn',
                'pb.sale_price_afn',
                'pb.store_profit_per_unit_afn',
                'pb.purchase_amount_currency',
                'pb.exchange_rate',
                'pb.purchase_date',
                'pb.invoice_number',
                'p.name as product_name',
                'pv.name as variant_name',
                'pv.volume_ml',
                'pv.concentration',
                'pc.code as purchase_currency_code',
                'bp.profit as actual_profit_afn',
            ])
            ->get()
            ->map(function ($b) {
                $remainingQty = (int) $b->remaining_quantity;
                $qty = (int) $b->quantity;
                $soldQty = max(0, $qty - $remainingQty);
                $unitCost = (float) $b->unit_cost_afn;
                $salePrice = (float) $b->sale_price_afn;

                return [
                    'batch_id' => (int) $b->id,
                    'batch_code' => $b->batch_code,
                    'variant_id' => (int) $b->variant_id,
                    'product_name' => $b->product_name ?? '—',
                    'variant_name' => $b->variant_name,
                    'volume_ml' => $b->volume_ml !== null ? (int) $b->volume_ml : null,
                    'concentration' => $b->concentration,
                    'status' => $b->status,
                    'quantity' => $qty,
                    'remaining_quantity' => $remainingQty,
                    'sold_quantity' => $soldQty,
                    'unit_cost_afn' => $unitCost,
                    'sale_price_afn' => $salePrice,
                    'store_profit_per_unit_afn' => (float) $b->store_profit_per_unit_afn,
                    'cost_value_afn' => round($unitCost * $remainingQty, 2),
                    'sale_value_afn' => round($salePrice * $remainingQty, 2),
                    'potential_profit_afn' => max(0, round(($salePrice - $unitCost) * $remainingQty, 2)),
                    'actual_profit_afn' => round((float) ($b->actual_profit_afn ?? 0), 2),
                    'purchase_currency_code' => $b->purchase_currency_code,
                    'purchase_amount_currency' => (float) $b->purchase_amount_currency,
                    'exchange_rate' => (float) $b->exchange_rate,
                    'purchase_date' => $b->purchase_date,
                    'invoice_number' => $b->invoice_number,
                ];
            })
            ->values()
            ->all();
    }
}
