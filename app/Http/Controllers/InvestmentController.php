<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Investment;
use App\Models\InvestorTransaction;
use App\Models\InvestorWalletBalance;
use App\Models\ProductBatch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentController extends Controller
{
    public function index(Request $request): Response
    {
        $investments = Investment::query()
            ->with([
                'investor:id,name,email',
                'currency:id,code,symbol',
            ])
            ->withCount('batches')
            ->withSum('batches as total_cost_batches', 'total_cost_afn')
            ->when($request->investor_id, fn ($q) => $q->where('investor_id', $request->investor_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->currency_id, fn ($q) => $q->where('currency_id', $request->currency_id))
            ->latest('investment_date')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'total_invested_afn' => (float) Investment::query()->where('status', 'active')->sum('amount_afn'),
            'total_batches' => (int) ProductBatch::query()->whereNotNull('investment_id')->count(),
            'active_count' => (int) Investment::query()->where('status', 'active')->count(),
        ];

        return Inertia::render('investments/index', [
            'investments' => $investments,
            'summary' => $summary,
            'investors' => User::investors()->select('id', 'name')->orderBy('name')->get(),
            'currencies' => Currency::query()->where('status', 'active')->get(['id', 'code', 'symbol']),
            'filters' => $request->only(['investor_id', 'status', 'currency_id']),
        ]);
    }

    public function show(Investment $investment): Response
    {
        $investment->load([
            'investor:id,name,email',
            'currency:id,code,symbol',
            'batches.variant.product.brand',
            'batches.purchaseCurrency:id,code,symbol',
            'transactions' => fn ($q) => $q->latest('transaction_date')->latest('id'),
            'creator:id,name',
        ]);

        $computed = [
            'total_profit_afn' => (float) $investment->transactions
                ->where('type', 'profit_earned')
                ->sum('amount_afn'),
            'total_withdrawn_afn' => (float) $investment->transactions
                ->whereIn('type', ['capital_withdrawn', 'profit_withdrawn'])
                ->sum(fn ($t) => abs((float) $t->amount_afn)),
            'remaining_capital_afn' => (float) $investment->amount_afn
                - (float) $investment->transactions
                    ->where('type', 'capital_withdrawn')
                    ->sum(fn ($t) => abs((float) $t->amount_afn)),
            'batches_cost_afn' => (float) $investment->batches->sum('total_cost_afn'),
            'batches_remaining_qty' => (int) $investment->batches->sum('remaining_quantity'),
        ];

        return Inertia::render('investments/show', [
            'investment' => $investment,
            'computed' => $computed,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('investments/create', [
            'investors' => User::investors()->select('id', 'name', 'email')->orderBy('name')->get(),
            'currencies' => Currency::query()
                ->where('status', 'active')
                ->get(['id', 'code', 'symbol', 'is_base']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'investor_id' => 'required|exists:users,id',
            'currency_id' => 'required|exists:currencies,id',
            'amount_currency' => 'required|numeric|min:0.01',
            'investment_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $investor = User::investors()->findOrFail($data['investor_id']);
        $currency = Currency::query()->findOrFail($data['currency_id']);

        DB::transaction(function () use ($data, $investor, $currency) {
            $afnId = (int) Currency::query()->where('code', 'AFN')->value('id');
            $isAfn = strtoupper((string) $currency->code) === 'AFN' || (int) $currency->id === $afnId;

            $rate = $isAfn ? 1.0 : ExchangeRate::getRate((int) $currency->id, $afnId);
            if ($rate === null || $rate <= 0) {
                throw ValidationException::withMessages([
                    'currency_id' => 'No exchange rate found for this currency to AFN.',
                ]);
            }

            $amountCurrency = round((float) $data['amount_currency'], 2);
            $amountAfn = round($amountCurrency * (float) $rate, 2);

            $investment = Investment::create([
                'investor_id' => $investor->id,
                'currency_id' => $currency->id,
                'exchange_rate' => $rate,
                'amount_currency' => $amountCurrency,
                'amount_afn' => $amountAfn,
                'investment_date' => $data['investment_date'],
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
                'trash' => false,
            ]);

            $wallet = InvestorWalletBalance::forInvestor((int) $investor->id, (string) $currency->code);
            $before = (float) $wallet->capital_balance_afn;

            InvestorTransaction::create([
                'investor_id' => $investor->id,
                'investment_id' => $investment->id,
                'currency_id' => $currency->id,
                'currency_code' => $currency->code,
                'currency_symbol' => $currency->symbol,
                'exchange_rate' => $rate,
                'type' => 'capital_deposit',
                'amount_currency' => $amountCurrency,
                'amount_afn' => $amountAfn,
                'balance_type' => 'capital',
                'balance_before_afn' => $before,
                'balance_after_afn' => $before + $amountAfn,
                'reference_type' => Investment::class,
                'reference_id' => $investment->id,
                'description' => 'Manual investment deposit',
                'transaction_date' => $data['investment_date'],
                'created_by' => auth()->id(),
            ]);

            $wallet->adjustCapital($amountCurrency, $amountAfn, (float) $rate);

            return $investment;
        });

        return redirect()->route('investments.index')->with('success', 'Investment created.');
    }

    public function edit(Investment $investment): Response
    {
        return Inertia::render('investments/edit', [
            'investment' => $investment->load([
                'investor:id,name,email',
                'currency:id,code,symbol',
            ]),
        ]);
    }

    public function update(Request $request, Investment $investment): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:active,closed',
            'notes' => 'nullable|string|max:2000',
        ]);

        $investment->update($data);

        return back()->with('success', 'Investment updated.');
    }

    public function destroy(Investment $investment): RedirectResponse
    {
        abort_if(
            $investment->batches()->exists(),
            422,
            'Cannot delete investment with linked batches.'
        );

        $investment->delete();

        return redirect()->route('investments.index')->with('success', 'Investment removed.');
    }
}
