<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestmentRequest;
use App\Http\Requests\UpdateInvestmentRequest;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorTransaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentController extends Controller
{
    /* =========================================================
     |  INDEX
     ========================================================= */

    public function index(Request $request): Response
    {
        $search     = $request->query('search');
        $investorId = $request->query('investor_id');
        $status     = $request->query('status');

        $investments = Investment::query()
            ->with([
                'investor:id,full_name,phone',
                'creator:id,name',
            ])
            ->withCount(['purchaseAllocations', 'productAllocations'])
            ->notTrashed()
            ->when($search, function ($query, $search) {
                $query->where('investment_number', 'like', "%{$search}%")
                    ->orWhereHas('investor', function ($q) use ($search) {
                        $q->where('full_name', 'like', "%{$search}%");
                    });
            })
            ->when($investorId, fn ($q, $v) => $q->where('investor_id', $v))
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('investment_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total_investments'   => Investment::notTrashed()->count(),
            'active_investments'  => Investment::notTrashed()->whereIn('status', ['active', 'partially_used'])->count(),
            'total_capital_afn'   => Investment::notTrashed()->sum('capital_amount_afn'),
            'total_available_afn' => Investment::notTrashed()->sum('available_amount_afn'),
            'total_allocated_afn' => Investment::notTrashed()->sum('allocated_amount_afn'),
            'total_profit_afn'    => Investment::notTrashed()->sum('profit_amount_afn'),
        ];

        return Inertia::render('investments/index', [
            'investments' => $investments,
            'investors'   => Investor::notTrashed()->select('id', 'full_name', 'phone')->orderBy('full_name')->get(),
            'filters'     => [
                'search'      => $search,
                'investor_id' => $investorId,
                'status'      => $status,
            ],
            'stats' => $stats,
        ]);
    }

    /* =========================================================
     |  CREATE
     ========================================================= */

    public function create(Request $request): Response
    {
        return Inertia::render('investments/create', [
            'users' => User::query()
                ->notTrashed()
                ->where('role', User::ROLE_INVESTOR)
                ->select('id', 'name', 'email', 'phone')
                ->orderBy('name')
                ->get(),
            'nextNumber' => Investment::generateInvestmentNumber(),
        ]);
    }

    /* =========================================================
     |  STORE
     ========================================================= */

    public function store(StoreInvestmentRequest $request): RedirectResponse
    {
        $investment = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $user = User::query()->where('role', User::ROLE_INVESTOR)->findOrFail($data['user_id']);
            $investor = Investor::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => 'active',
                    'trash' => false,
                    'created_by' => Auth::id(),
                ]
            );

            $capitalAfn = (float) ($data['capital_amount_afn'] ?? 0);
            $data['investor_id'] = $investor->id;
            unset($data['user_id']);

            $data['investment_number']    = Investment::generateInvestmentNumber();
            $data['available_amount_afn'] = $capitalAfn;
            $data['allocated_amount_afn'] = 0;
            $data['profit_amount_afn']    = 0;
            $data['withdrawn_amount_afn'] = 0;
            $data['status']               = 'active';
            $data['created_by']           = Auth::id();
            $data['user_id']              = Auth::id();

            $investment = Investment::create($data);

            if ($capitalAfn > 0) {
                InvestorTransaction::record(
                    $investment->investor_id,
                    'capital_deposit',
                    $capitalAfn,
                    'capital',
                    [
                        'investment_id' => $investment->id,
                        'description' => 'Capital deposit '.$investment->investment_number,
                        'transaction_date' => $investment->investment_date?->toDateString(),
                    ]
                );

                $wallet = $investment->investor->getOrCreateWallet();
                $wallet->update([
                    'capital_balance_afn' => (float) $wallet->capital_balance_afn + $capitalAfn,
                    'total_invested_afn'  => (float) $wallet->total_invested_afn + $capitalAfn,
                    'last_transaction_at' => now(),
                ]);
            } else {
                $investment->investor->getOrCreateWallet();
            }

            return $investment;
        });

        return redirect()
            ->route('investments.show', $investment->id)
            ->with('success', "Investment \"{$investment->investment_number}\" created successfully.");
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
            'investor:id,full_name,phone,email',
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
            'total_profit'       => $investment->profit_amount_afn,
            'profit_paid'        => $investment->profitEntries()->where('status', 'paid')->sum('profit_amount_afn'),
            'profit_pending'     => $investment->profitEntries()->where('status', 'pending')->sum('profit_amount_afn'),
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
            'investment' => $investment->load('investor:id,full_name'),
        ]);
    }

    /* =========================================================
     |  UPDATE
     ========================================================= */

    public function update(UpdateInvestmentRequest $request, Investment $investment): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();

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
        // نمی‌توان Investment با تخصیص را حذف کرد
        if ($investment->purchaseAllocations()->exists()) {
            return back()->with('error', 'Cannot delete investment with purchase allocations. Close it instead.');
        }

        $number = $investment->investment_number;

        $investment->update([
            'trash'   => true,
            'user_id' => Auth::id(),
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
            'trash'   => false,
            'user_id' => Auth::id(),
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
            ->with(['investor:id,full_name', 'creator:id,name'])
            ->orderByDesc('updated_at')
            ->paginate(15);

        return Inertia::render('investments/trash', [
            'investments' => $investments,
        ]);
    }

    /* =========================================================
     |  WITHDRAW — برداشت از سرمایه
     ========================================================= */

    public function withdraw(Request $request, Investment $investment): RedirectResponse
    {
        $validated = $request->validate([
            'amount_afn' => ['required', 'numeric', 'min:0.01', 'max:' . $investment->available_amount_afn],
            'notes'      => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($investment, $validated) {
            $amount = (float) $validated['amount_afn'];

            $investment->update([
                'available_amount_afn'  => (float) $investment->available_amount_afn - $amount,
                'withdrawn_amount_afn'  => (float) $investment->withdrawn_amount_afn + $amount,
                'user_id'               => Auth::id(),
            ]);

            // به‌روزرسانی wallet
            $wallet = $investment->investor->getOrCreateWallet();
            $wallet->update([
                'capital_balance_afn' => (float) $wallet->capital_balance_afn - $amount,
                'total_withdrawn_afn' => (float) $wallet->total_withdrawn_afn + $amount,
                'last_transaction_at' => now(),
            ]);

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

        // بررسی: آیا هنوز موجودی قابل برداشت دارد؟
        if ((float) $investment->available_amount_afn > 0.01) {
            return back()->with('error', 'Cannot close investment with available capital. Withdraw first.');
        }

        // بررسی: آیا هنوز محصولات فروخته‌نشده دارد؟
        $hasRemaining = $investment->productAllocations()
            ->where('remaining_quantity', '>', 0)
            ->exists();

        if ($hasRemaining) {
            return back()->with('error', 'Cannot close investment with unsold products.');
        }

        $investment->update([
            'status'  => 'closed',
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Investment closed successfully.');
    }
}