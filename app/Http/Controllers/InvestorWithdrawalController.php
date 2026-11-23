<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestorWithdrawalRequest;
use App\Models\AppSetting;
use App\Models\Currency;
use App\Models\Investor;
use App\Models\InvestorTransaction;
use App\Models\InvestorWalletBalance;
use App\Models\InvestorWithdrawal;
use App\Services\InvestorWithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvestorWithdrawalController extends Controller
{
    public function __construct(
        private readonly InvestorWithdrawalService $withdrawals,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $investorId = $request->query('investor_id');
        $status = $request->query('status');
        $type = $request->query('type');

        $withdrawals = InvestorWithdrawal::query()
            ->with([
                'investor:id,name,phone',
                'currency:id,code,name,symbol',
                'approvedBy:id,name',
                'paidBy:id,name',
                'creator:id,name',
            ])
            ->when($search, fn ($q, $s) => $q->where('withdrawal_number', 'like', "%{$s}%"))
            ->when($investorId, fn ($q, $v) => $q->where('investor_id', $v))
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->when($type, fn ($q, $v) => $q->where('type', $v))
            ->orderByDesc('withdrawal_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total_pending' => InvestorWithdrawal::pending()->count(),
            'total_approved' => InvestorWithdrawal::approved()->count(),
            'total_paid' => InvestorWithdrawal::paid()->count(),
            'total_amount_paid_afn' => (float) InvestorWithdrawal::paid()->sum('amount_afn'),
        ];

        return Inertia::render('investor-withdrawals/index', [
            'withdrawals' => $withdrawals,
            'investors' => Investor::notTrashed()->select('id', 'name')->orderBy('name')->get(),
            'filters' => [
                'search' => $search,
                'investor_id' => $investorId,
                'status' => $status,
                'type' => $type,
            ],
            'stats' => $stats,
        ]);
    }

    public function create(Request $request): Response
    {
        $investorId = $request->query('investor_id');

        $investors = Investor::notTrashed()->active()
            ->select('id', 'name', 'phone')
            ->orderBy('name')
            ->get()
            ->map(function (Investor $investor) {
                $summary = $this->withdrawals->balanceSummary($investor);

                return [
                    'id' => $investor->id,
                    'full_name' => $investor->full_name,
                    'phone' => $investor->phone,
                    'wallet' => [
                        'capital_balance_afn' => (string) $summary['capital_afn'],
                        'profit_balance_afn' => (string) $summary['profit_afn'],
                        'by_currency' => $summary['by_currency'],
                    ],
                ];
            });

        return Inertia::render('investor-withdrawals/create', [
            'investors' => $investors,
            'selectedInvestorId' => $investorId ? (int) $investorId : null,
            'nextNumber' => InvestorWithdrawal::generateWithdrawalNumber(),
            'currencies' => Currency::formOptions(),
        ]);
    }

    public function store(StoreInvestorWithdrawalRequest $request): RedirectResponse
    {
        try {
            $withdrawal = $this->withdrawals->create($request->validated(), Auth::id());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('investor-withdrawals.show', $withdrawal->id)
            ->with('success', "Withdrawal \"{$withdrawal->withdrawal_number}\" created successfully.");
    }

    public function show(InvestorWithdrawal $withdrawal): Response
    {
        $withdrawal->load([
            'investor:id,name,phone,email',
            'investment:id,investor_id,amount_afn',
            'currency:id,code,name,symbol',
            'approvedBy:id,name',
            'paidBy:id,name',
            'creator:id,name',
        ]);

        return Inertia::render('investor-withdrawals/show', [
            'withdrawal' => $withdrawal,
        ]);
    }

    public function approve(InvestorWithdrawal $withdrawal): RedirectResponse
    {
        if (! $withdrawal->isPending()) {
            return back()->with('error', 'Only pending withdrawals can be approved.');
        }

        $withdrawal->approve(Auth::id());

        return back()->with('success', 'Withdrawal approved.');
    }

    public function reject(Request $request, InvestorWithdrawal $withdrawal): RedirectResponse
    {
        if (! $withdrawal->isPending()) {
            return back()->with('error', 'Only pending withdrawals can be rejected.');
        }

        $withdrawal->reject($request->input('reason'));

        return back()->with('success', 'Withdrawal rejected.');
    }

    public function markAsPaid(InvestorWithdrawal $withdrawal): RedirectResponse
    {
        try {
            $this->withdrawals->markAsPaid($withdrawal, Auth::id());
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: 'Unable to mark withdrawal as paid.';

            return back()->with('error', $message);
        }

        return back()->with('success', 'Withdrawal marked as paid.');
    }

    /**
     * Pay the investor everything still in the account, zero the balances, and open the receipt.
     */
    public function settle(Investor $investor): RedirectResponse
    {
        try {
            $withdrawal = $this->settleAccount($investor);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('investor-withdrawals.receipt', $withdrawal->id)
            ->with('success', 'The investor was paid and the account balance is now zero.');
    }

    private function settleAccount(Investor $investor): InvestorWithdrawal
    {
        return DB::transaction(function () use ($investor) {
            $locked = Investor::query()->whereKey($investor->id)->lockForUpdate()->firstOrFail();
            $capital = round($this->withdrawals->availableCapitalAfn($locked), 2);
            $profit = round($this->withdrawals->availableProfitAfn($locked), 2);
            $total = round($capital + $profit, 2);

            if ($total <= 0) {
                throw new \RuntimeException('This investor has no balance left to pay.');
            }

            $ledger = Currency::ledger()
                ?? Currency::query()->notTrashed()->where('code', 'AFN')->firstOrFail();

            $withdrawal = InvestorWithdrawal::create([
                'investor_id' => $locked->id,
                'currency_id' => $ledger->id,
                'currency_code' => $ledger->code,
                'currency_symbol' => $ledger->symbol,
                'exchange_rate' => 1,
                'withdrawal_number' => InvestorWithdrawal::generateWithdrawalNumber(),
                'type' => $profit > 0 && $capital <= 0 ? 'profit' : 'capital',
                'amount_currency' => $total,
                'amount_afn' => $total,
                'balance_before_afn' => $total,
                'balance_after_afn' => 0,
                'withdrawal_date' => now()->toDateString(),
                'status' => 'paid',
                'notes' => 'Full settlement. Capital '.number_format($capital, 2).' AFN and profit '.number_format($profit, 2).' AFN. Account balance is now zero.',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'paid_by' => Auth::id(),
                'paid_at' => now(),
                'created_by' => Auth::id(),
            ]);

            if ($capital > 0) {
                InvestorTransaction::record(
                    investorId: $locked->id,
                    type: 'capital_withdrawn',
                    amountAfn: $capital,
                    balanceType: 'capital',
                    options: [
                        'currency_id' => $ledger->id,
                        'currency_code' => $ledger->code,
                        'currency_symbol' => $ledger->symbol,
                        'exchange_rate' => 1,
                        'amount_currency' => $capital,
                        'reference_type' => InvestorWithdrawal::class,
                        'reference_id' => $withdrawal->id,
                        'description' => "Settlement {$withdrawal->withdrawal_number}",
                        'transaction_date' => $withdrawal->withdrawal_date,
                    ]
                );
            }

            if ($profit > 0) {
                InvestorTransaction::record(
                    investorId: $locked->id,
                    type: 'profit_withdrawn',
                    amountAfn: $profit,
                    balanceType: 'profit',
                    options: [
                        'currency_id' => $ledger->id,
                        'currency_code' => $ledger->code,
                        'currency_symbol' => $ledger->symbol,
                        'exchange_rate' => 1,
                        'amount_currency' => $profit,
                        'reference_type' => InvestorWithdrawal::class,
                        'reference_id' => $withdrawal->id,
                        'description' => "Settlement {$withdrawal->withdrawal_number}",
                        'transaction_date' => $withdrawal->withdrawal_date,
                    ]
                );
            }

            InvestorWalletBalance::query()
                ->where('investor_id', $locked->id)
                ->update([
                    'capital_balance_currency' => 0,
                    'capital_balance_afn' => 0,
                    'profit_balance_currency' => 0,
                    'profit_balance_afn' => 0,
                ]);

            return $withdrawal;
        });
    }

    public function receipt(InvestorWithdrawal $withdrawal): Response
    {
        $withdrawal->load('investor:id,name,phone', 'currency:id,code,symbol');
        $notes = (string) $withdrawal->notes;
        $capital = 0.0;
        $profit = 0.0;
        if (preg_match('/Capital ([0-9,.]+) (?:USD|AFN) and profit ([0-9,.]+) (?:USD|AFN)/', $notes, $match)) {
            $capital = (float) str_replace(',', '', $match[1]);
            $profit = (float) str_replace(',', '', $match[2]);
        } else {
            $capital = $withdrawal->type === 'capital' ? (float) $withdrawal->amount_afn : 0;
            $profit = $withdrawal->type === 'profit' ? (float) $withdrawal->amount_afn : 0;
        }

        $lines = [];
        if ($capital > 0) {
            $lines[] = ['name' => 'Capital returned', 'quantity' => null, 'amount' => $capital];
        }
        if ($profit > 0) {
            $lines[] = ['name' => 'Profit returned', 'quantity' => null, 'amount' => $profit];
        }
        if ($lines === []) {
            $lines[] = ['name' => 'Payout', 'quantity' => null, 'amount' => (float) $withdrawal->amount_afn];
        }

        $code = $withdrawal->currency_code ?: 'AFN';

        return Inertia::render('investor-withdrawals/receipt', [
            'company' => AppSetting::bill(),
            'investor' => [
                'id' => $withdrawal->investor_id,
                'name' => $withdrawal->investor?->full_name,
                'phone' => $withdrawal->investor?->phone,
            ],
            'printedAt' => now()->format('Y-m-d H:i'),
            'receipt' => [
                'number' => $withdrawal->withdrawal_number,
                'date' => optional($withdrawal->withdrawal_date)->toDateString(),
                'title' => 'Investor payout receipt',
                'lines' => $lines,
                'amount' => (float) $withdrawal->amount_afn,
                'amount_currency' => (float) ($withdrawal->amount_currency ?? $withdrawal->amount_afn),
                'currency_code' => $code,
                'exchange_rate' => (float) ($withdrawal->exchange_rate ?? 1),
                'balance' => (float) $withdrawal->balance_after_afn,
                'notes' => $withdrawal->notes,
            ],
        ]);
    }

    public function destroy(InvestorWithdrawal $withdrawal): RedirectResponse
    {
        if ($withdrawal->isPaid()) {
            return back()->with('error', 'Paid withdrawals cannot be deleted.');
        }

        $number = $withdrawal->withdrawal_number;
        $withdrawal->delete();

        return redirect()
            ->route('investor-withdrawals.index')
            ->with('success', "Withdrawal \"{$number}\" was deleted.");
    }
}
