<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestmentRequest;
use App\Http\Requests\UpdateInvestmentRequest;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\Investor;
use App\Services\CurrencyService;
use App\Services\InvestmentCapitalService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentController extends Controller
{
    public function __construct(
        private readonly CurrencyService $currencies,
        private readonly InvestmentCapitalService $capital,
    ) {}

    /* =========================================================
     |  INDEX
     ========================================================= */

    public function index(Request $request): Response
    {
        $search     = $request->query('search');
        $investorId = $request->query('investor_id');
        $status     = $request->query('status');
        $sourceType = $request->query('source_type');

        $investments = Investment::query()
            ->with([
                'investor:id,name,phone,email',
                'creator:id,name',
                'currency:id,code,name,symbol,decimal_places,rate_to_default,is_base',
            ])
            ->withCount(['purchaseAllocations', 'productAllocations'])
            ->notTrashed()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('investment_number', 'like', "%{$search}%")
                      ->orWhereHas('investor', function ($sub) use ($search) {
                          $sub->where('name', 'like', "%{$search}%")
                              ->orWhere('phone', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            })
            ->when($investorId, fn ($q, $v) => $q->where('investor_id', $v))
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->when($sourceType, fn ($q, $v) => $q->where('source_type', $v))
            ->orderByDesc('investment_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $moneyRows = Investment::notTrashed()
            ->with('currency')
            ->get()
            ->map(fn (Investment $row) => [
                'amount' => $row->capital_amount_currency ?? $row->capital_amount_afn,
                'currency' => $row->currency,
                'code' => $row->currency_code,
                'symbol' => $row->currency_symbol,
            ]);
        $profitRows = Investment::notTrashed()
            ->with('currency')
            ->get()
            ->map(fn (Investment $row) => [
                'amount' => $row->profit_amount_currency ?? $row->profit_amount_afn,
                'currency' => $row->currency,
                'code' => $row->currency_code,
                'symbol' => $row->currency_symbol,
            ]);

        $stats = [
            'total_investments'   => Investment::notTrashed()->count(),
            'active_investments'  => Investment::notTrashed()->whereIn('status', ['active', 'partially_used'])->count(),
            'total_capital_afn'   => Money::round((string) Investment::notTrashed()->sum('capital_amount_afn'), 2),
            'total_available_afn' => Money::round((string) Investment::notTrashed()->sum('available_amount_afn'), 2),
            'total_allocated_afn' => Money::round((string) Investment::notTrashed()->sum('allocated_amount_afn'), 2),
            'total_profit_afn'    => Money::round((string) Investment::notTrashed()->sum('profit_amount_afn'), 2),
            'capital_totals'      => $this->currencies->summarize($moneyRows),
            'profit_totals'       => $this->currencies->summarize($profitRows),
            'cash_investments'    => Investment::notTrashed()->where('source_type', 'cash')->count(),
            'goods_investments'   => Investment::notTrashed()->where('source_type', 'goods')->count(),
        ];

        return Inertia::render('investments/index', [
            'investments' => $investments,
            'investors'   => Investor::notTrashed()
                ->select('id', 'name', 'phone', 'email')
                ->orderBy('name')
                ->get(),
            'filters'     => [
                'search'      => $search,
                'investor_id' => $investorId,
                'status'      => $status,
                'source_type' => $sourceType,
            ],
            'stats' => $stats,
            'sourceTypes' => Investment::SOURCE_TYPES,
        ]);
    }

    /* =========================================================
     |  CREATE — removed from user workflow; capital is added
     |  from Investor Details.
     ========================================================= */

    public function create(Request $request): RedirectResponse
    {
        $investorId = $request->integer('user_id') ?: $request->integer('investor_id') ?: null;

        if ($investorId) {
            return redirect()
                ->route('investors.show', $investorId)
                ->with('success', 'Add cash capital or goods contribution from Investor Details.');
        }

        return redirect()
            ->route('investors.index')
            ->with('success', 'Open an investor and add capital from Investor Details.');
    }

    /* =========================================================
     |  STORE — internal capital pot (cash / goods). Prefer
     |  Investor Details → POST investors/{id}/capital.
     ========================================================= */

    public function store(StoreInvestmentRequest $request): RedirectResponse
    {
        $investment = $this->capital->deposit($request->validated());

        return redirect()
            ->route('investors.show', $investment->investor_id)
            ->with('success', ($investment->source_type === 'goods' ? 'Goods contribution' : 'Cash capital')." \"{$investment->investment_number}\" recorded.");
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(Investment $investment): Response
    {
        if ($investment->trash) {
            abort(404);
        }

        $investment->load([
            'investor:id,name,phone,email',
            'currency:id,code,name,symbol,decimal_places',
            'creator:id,name,email',
            'parent:id,investment_number',
            'renewals:id,investment_number,capital_amount_afn,status',
            'purchaseAllocations.purchase:id,name,invoice_number,line_total,purchase_date',
            'productAllocations.batch:id,batch_code,quantity,remaining_quantity',
            'productAllocations.variant.product:id,name',
            'productAllocations.variant.product.brand:id,name',
            'profitEntries' => fn ($q) => $q->latest()->limit(20),
        ]);

        $stats = [
            'total_purchases'    => $investment->purchaseAllocations()->count(),
            'total_batches'      => $investment->productAllocations()->count(),
            'total_profit'       => Money::round((string) $investment->profit_amount_afn, 2),
            'profit_paid'        => Money::round((string) $investment->profitEntries()->where('status', 'paid')->sum('profit_amount_afn'), 2),
            'profit_pending'     => Money::round((string) $investment->profitEntries()->where('status', 'pending')->sum('profit_amount_afn'), 2),

            'source_type'         => $investment->source_type,
            'source_type_label'   => $investment->source_type_label,
            'is_cash'             => $investment->isCashInvestment(),
            'is_goods'            => $investment->isGoodsInvestment(),
        ];

        return Inertia::render('investments/show', [
            'investment' => $investment,
            'stats'      => $stats,
        ]);
    }

    /* =========================================================
     |  EDIT
     ========================================================= */

    public function edit(Investment $investment): Response
    {
        if ($investment->trash) {
            abort(404);
        }

        return Inertia::render('investments/edit', [
            'investment' => $investment->load(['investor:id,name,phone,email', 'currency:id,code,name,symbol']),
            'sourceTypes' => Investment::SOURCE_TYPES,
            'currencies' => Currency::formOptions([$investment->currency_id]),
        ]);
    }

    /* =========================================================
     |  UPDATE
     ========================================================= */

    public function update(UpdateInvestmentRequest $request, Investment $investment): RedirectResponse
    {
        $data = $request->validated();

        // ✅ محافظت: نمی‌توان source_type را تغییر داد اگر allocation دارد
        if (
            isset($data['source_type']) &&
            $data['source_type'] !== $investment->source_type &&
            $investment->purchaseAllocations()->exists()
        ) {
            return back()->with('error', 'Cannot change investment source type after allocations exist.');
        }

        $data['created_by'] = Auth::id();   // ✅ نام واضح‌تر

        $investment->update($data);

        return redirect()
            ->route('investments.show', $investment->id)
            ->with('success', "Investment \"{$investment->investment_number}\" updated successfully.");
    }

    /* =========================================================
     |  DESTROY — move to trash
     ========================================================= */

    public function destroy(Investment $investment): RedirectResponse
    {
        if ($investment->purchaseAllocations()->exists()) {
            return back()->with('error', 'Cannot delete investment with purchase allocations. Close it instead.');
        }

        $number = $investment->investment_number;

        $investment->update([
            'trash'      => true,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('investments.index')
            ->with('success', "Investment \"{$number}\" was moved to trash.");
    }

    /* =========================================================
     |  RESTORE
     ========================================================= */

    public function restore(int $id): RedirectResponse
    {
        $investment = Investment::onlyTrashed()->findOrFail($id);

        $investment->update([
            'trash'      => false,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('investments.index')
            ->with('success', "Investment \"{$investment->investment_number}\" was restored.");
    }

    /* =========================================================
     |  FORCE DELETE
     ========================================================= */

    public function forceDelete(int $id): RedirectResponse
    {
        $investment = Investment::onlyTrashed()->findOrFail($id);

        if ($investment->purchaseAllocations()->exists()) {
            return back()->with('error', 'Cannot permanently delete investment with allocations.');
        }

        $number = $investment->investment_number;
        $investment->forceDelete();

        return redirect()
            ->route('investments.index')
            ->with('success', "Investment \"{$number}\" was permanently deleted.");
    }

    /* =========================================================
     |  TRASH LIST
     ========================================================= */

    public function trash(): Response
    {
        $investments = Investment::onlyTrashed()
            ->with(['investor:id,name,phone,email', 'creator:id,name', 'currency:id,code,name,symbol'])
            ->orderByDesc('updated_at')
            ->paginate(15);

        return Inertia::render('investments/trash', [
            'investments' => $investments,
        ]);
    }

    /* =========================================================
     |  WITHDRAW — برداشت از سرمایه
     |  ✅ فقط برای investment نقدی مجاز است
     ========================================================= */

    public function withdraw(Request $request, Investment $investment): RedirectResponse
    {
        if ($investment->isGoodsInvestment()) {
            return back()->with('error', 'Goods investments cannot be withdrawn in cash. Sell the products first.');
        }

        if ($investment->trash) {
            return back()->with('error', 'Cannot withdraw from a trashed investment.');
        }

        $validated = $request->validate([
            'amount_afn' => ['required', 'numeric', 'min:0.01', 'max:' . $investment->available_amount_afn],
            'notes'      => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($investment, $validated) {
            $amount = Money::round((string) $validated['amount_afn'], 2);

            $investment->update([
                'available_amount_afn' => Money::round(
                    Money::sub((string) $investment->available_amount_afn, $amount, 8),
                    2
                ),
                'withdrawn_amount_afn' => Money::round(
                    Money::add((string) $investment->withdrawn_amount_afn, $amount, 8),
                    2
                ),
                'created_by'           => Auth::id(),
            ]);

            $wallet = $investment->investor->getOrCreateWallet();
            $wallet->update([
                'capital_balance_afn' => Money::round(
                    Money::sub((string) $wallet->capital_balance_afn, $amount, 8),
                    2
                ),
                'total_withdrawn_afn' => Money::round(
                    Money::add((string) $wallet->total_withdrawn_afn, $amount, 8),
                    2
                ),
                'last_transaction_at' => now(),
            ]);

            $investment->refresh();
            $investment->refreshStatus();
        });

        return back()->with('success', 'Withdrawal completed successfully.');
    }

    /* =========================================================
     |  CLOSE — بستن Investment
     ========================================================= */

    public function close(Investment $investment): RedirectResponse
    {
        if ($investment->status === 'closed') {
            return back()->with('error', 'Already closed.');
        }

        if ($investment->isCashInvestment() && (float) $investment->available_amount_afn > 0.01) {
            return back()->with('error', 'Cannot close investment with available capital. Withdraw first.');
        }

        $hasRemaining = $investment->productAllocations()
            ->where('remaining_quantity', '>', 0)
            ->exists();

        if ($hasRemaining) {
            return back()->with('error', 'Cannot close investment with unsold products.');
        }

        $investment->update([
            'status'     => 'closed',
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Investment closed successfully.');
    }
}