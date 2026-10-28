<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestorWithdrawalRequest;
use App\Models\AppSetting;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorTransaction;
use App\Models\InvestorWithdrawal;
use App\Services\CurrencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvestorWithdrawalController extends Controller
{
    public function __construct(
        private readonly CurrencyService $currencies,
    ) {}

    /* =========================================================
     |  INDEX — همه برداشت‌ها
     ========================================================= */

    public function index(Request $request): Response
    {
        $search      = $request->query('search');
        $investorId  = $request->query('investor_id');
        $status      = $request->query('status');
        $type        = $request->query('type');

        $withdrawals = InvestorWithdrawal::query()
            ->with([
                'investor:id,full_name,phone',
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
            'total_pending'  => InvestorWithdrawal::pending()->count(),
            'total_approved' => InvestorWithdrawal::approved()->count(),
            'total_paid'     => InvestorWithdrawal::paid()->count(),
            'total_amount_paid_afn' => (float) InvestorWithdrawal::paid()->sum('amount_afn'),
        ];

        return Inertia::render('investor-withdrawals/index', [
            'withdrawals' => $withdrawals,
            'investors'   => Investor::notTrashed()->select('id', 'full_name')->orderBy('full_name')->get(),
            'filters'     => [
                'search'      => $search,
                'investor_id' => $investorId,
                'status'      => $status,
                'type'        => $type,
            ],
            'stats' => $stats,
        ]);
    }

    /* =========================================================
     |  CREATE
     ========================================================= */

    public function create(Request $request): Response
    {
        $investorId = $request->query('investor_id');

        return Inertia::render('investor-withdrawals/create', [
            'investors'  => Investor::notTrashed()->active()
                                ->with('wallet')
                                ->select('id', 'full_name', 'phone')
                                ->orderBy('full_name')
                                ->get(),
            'selectedInvestorId' => $investorId,
            'nextNumber' => InvestorWithdrawal::generateWithdrawalNumber(),
            'currencies' => Currency::formOptions(),
        ]);
    }

    /* =========================================================
     |  STORE
     ========================================================= */

    public function store(StoreInvestorWithdrawalRequest $request): RedirectResponse
    {
        $withdrawal = DB::transaction(function () use ($request) {
            $data = $request->validated();

            $investor = Investor::findOrFail($data['investor_id']);
            $wallet   = $investor->getOrCreateWallet();

            $currency = $this->currencies->requireActive((int) $data['currency_id']);
            $amountEntered = (float) ($data['amount'] ?? $data['amount_afn'] ?? 0);
            $amountAfn = (float) $this->currencies->toLedger((string) $amountEntered, $currency);
            $snap = $this->currencies->snapshot($currency);
            $data['currency_id'] = $snap['id'];
            $data['currency_code'] = $snap['code'];
            $data['currency_symbol'] = $snap['symbol'];
            $data['exchange_rate'] = $snap['rate_to_default'];
            $data['amount_currency'] = $amountEntered;
            unset($data['amount']);

            // ⭐ بررسی موجودی
            if ($data['type'] === 'profit') {
                if ($amountAfn > (float) $wallet->profit_balance_afn) {
                    throw new \Exception('The withdrawal is larger than the profit available.');
                }
                $balanceBefore = (float) $wallet->profit_balance_afn;
            } else {
                if ($amountAfn > (float) $wallet->capital_balance_afn) {
                    throw new \Exception('The withdrawal is larger than the capital available.');
                }
                $balanceBefore = (float) $wallet->capital_balance_afn;
            }

            $data['withdrawal_number']  = InvestorWithdrawal::generateWithdrawalNumber();
            $data['amount_afn']         = $amountAfn;
            $data['balance_before_afn'] = $balanceBefore;
            $data['balance_after_afn']  = $balanceBefore - $amountAfn;
            $data['created_by']         = Auth::id();
            $data['status']             = $data['status'] ?? 'pending';

            return InvestorWithdrawal::create($data);
        });

        return redirect()
            ->route('investor-withdrawals.show', $withdrawal->id)
            ->with('success', "Withdrawal \"{$withdrawal->withdrawal_number}\" created successfully.");
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(InvestorWithdrawal $withdrawal): Response
    {
        $withdrawal->load([
            'investor:id,full_name,phone,email',
            'investment:id,investment_number',
            'approvedBy:id,name',
            'paidBy:id,name',
            'creator:id,name',
        ]);

        return Inertia::render('investor-withdrawals/show', [
            'withdrawal' => $withdrawal,
        ]);
    }

    /* =========================================================
     |  APPROVE
     ========================================================= */

    public function approve(InvestorWithdrawal $withdrawal): RedirectResponse
    {
        if (! $withdrawal->isPending()) {
            return back()->with('error', 'Only pending withdrawals can be approved.');
        }

        $withdrawal->approve(Auth::id());

        return back()->with('success', 'Withdrawal approved.');
    }

    /* =========================================================
     |  REJECT
     ========================================================= */

    public function reject(Request $request, InvestorWithdrawal $withdrawal): RedirectResponse
    {
        if (! $withdrawal->isPending()) {
            return back()->with('error', 'Only pending withdrawals can be rejected.');
        }

        $withdrawal->reject($request->input('reason'));

        return back()->with('success', 'Withdrawal rejected.');
    }

    /* =========================================================
     |  MARK AS PAID — کم کردن از Wallet + ثبت تراکنش
     ========================================================= */

    public function markAsPaid(InvestorWithdrawal $withdrawal): RedirectResponse
    {
        if (! $withdrawal->isPaid() === false && $withdrawal->status === 'paid') {
            return back()->with('error', 'Already paid.');
        }

        if ($withdrawal->status === 'rejected') {
            return back()->with('error', 'Rejected withdrawals cannot be paid.');
        }

        DB::transaction(function () use ($withdrawal) {
            $investor = $withdrawal->investor;
            $wallet   = $investor->getOrCreateWallet();

            $amountAfn = (float) $withdrawal->amount_afn;

            if ($withdrawal->isProfitWithdrawal()) {
                $wallet->decrement('profit_balance_afn', $amountAfn);
                $wallet->increment('total_withdrawn_afn', $amountAfn);

                $txType = 'profit_withdrawn';
            } else {
                $wallet->decrement('capital_balance_afn', $amountAfn);
                $wallet->increment('total_withdrawn_afn', $amountAfn);

                $txType = 'capital_withdrawn';
            }

            // ثبت تراکنش سرمایه‌گذار
            InvestorTransaction::record(
                investorId: $investor->id,
                type: $txType,
                amountAfn: $amountAfn,
                balanceType: $withdrawal->isProfitWithdrawal() ? 'profit' : 'capital',
                options: [
                    'investment_id'    => $withdrawal->investment_id,
                    'reference_type'   => InvestorWithdrawal::class,
                    'reference_id'     => $withdrawal->id,
                    'description'      => "Withdrawal {$withdrawal->withdrawal_number}",
                    'transaction_date' => $withdrawal->withdrawal_date,
                ]
            );

            $withdrawal->markAsPaid(Auth::id());
        });

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
            $wallet = $investor->getOrCreateWallet();
            $capital = round((float) $wallet->capital_balance_afn, 2);
            $profit = round((float) $wallet->profit_balance_afn, 2);
            $total = round($capital + $profit, 2);

            if ($total <= 0) {
                throw new \RuntimeException('This investor has no balance left to pay.');
            }

            $withdrawal = InvestorWithdrawal::create([
                'investor_id' => $investor->id,
                'withdrawal_number' => InvestorWithdrawal::generateWithdrawalNumber(),
                'type' => $profit > 0 && $capital <= 0 ? 'profit' : 'capital',
                'amount_afn' => $total,
                'balance_before_afn' => $total,
                'balance_after_afn' => 0,
                'withdrawal_date' => now()->toDateString(),
                'status' => 'paid',
                'notes' => 'Full settlement. Capital '.number_format($capital, 2).' USD and profit '.number_format($profit, 2).' USD. Account balance is now zero.',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'paid_by' => Auth::id(),
                'paid_at' => now(),
                'created_by' => Auth::id(),
            ]);

            if ($capital > 0) {
                InvestorTransaction::record(
                    investorId: $investor->id,
                    type: 'capital_withdrawn',
                    amountAfn: $capital,
                    balanceType: 'capital',
                    options: [
                        'reference_type' => InvestorWithdrawal::class,
                        'reference_id' => $withdrawal->id,
                        'description' => "Settlement {$withdrawal->withdrawal_number}",
                        'transaction_date' => $withdrawal->withdrawal_date,
                    ]
                );
            }

            if ($profit > 0) {
                InvestorTransaction::record(
                    investorId: $investor->id,
                    type: 'profit_withdrawn',
                    amountAfn: $profit,
                    balanceType: 'profit',
                    options: [
                        'reference_type' => InvestorWithdrawal::class,
                        'reference_id' => $withdrawal->id,
                        'description' => "Settlement {$withdrawal->withdrawal_number}",
                        'transaction_date' => $withdrawal->withdrawal_date,
                    ]
                );
            }

            Investment::query()
                ->where('investor_id', $investor->id)
                ->where('available_amount_afn', '>', 0)
                ->get()
                ->each(function (Investment $investment) {
                    $available = (float) $investment->available_amount_afn;
                    $investment->update([
                        'available_amount_afn' => 0,
                        'withdrawn_amount_afn' => (float) $investment->withdrawn_amount_afn + $available,
                        'user_id' => Auth::id(),
                    ]);
                    $investment->refreshStatus();
                });

            $wallet->update([
                'capital_balance_afn' => 0,
                'profit_balance_afn' => 0,
                'total_withdrawn_afn' => (float) $wallet->total_withdrawn_afn + $total,
                'last_transaction_at' => now(),
            ]);

            return $withdrawal;
        });
    }

    public function receipt(InvestorWithdrawal $withdrawal): Response
    {
        $withdrawal->load('investor:id,full_name,phone');
        $notes = (string) $withdrawal->notes;
        $capital = 0.0;
        $profit = 0.0;
        if (preg_match('/Capital ([0-9,.]+) USD and profit ([0-9,.]+) USD/', $notes, $match)) {
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
                'balance' => (float) $withdrawal->balance_after_afn,
                'notes' => $withdrawal->notes,
            ],
        ]);
    }

    /* =========================================================
     |  DESTROY
     ========================================================= */

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