<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestorRequest;
use App\Http\Requests\UpdateInvestorRequest;
use App\Models\Investor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvestorController extends Controller
{
    /* =========================================================
     |  INDEX
     ========================================================= */

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $investors = Investor::query()
            ->with([
                'wallet',
                'user:id,name',
                'creator:id,name',
            ])
            ->withCount(['investments', 'withdrawals', 'batches'])
            ->notTrashed()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total_investors'   => Investor::notTrashed()->count(),
            'active_investors'  => Investor::notTrashed()->where('status', 'active')->count(),
            'total_capital'     => (float) DB::table('investor_wallets')->sum('capital_balance_afn'),
            'total_profit_paid' => (float) DB::table('investor_wallets')->sum('total_profit_earned_afn'),
            'total_withdrawn'   => (float) DB::table('investor_wallets')->sum('total_withdrawn_afn'),
        ];

        return Inertia::render('investors/index', [
            'investors' => $investors,
            'filters'   => [
                'search' => $search,
                'status' => $status,
            ],
            'stats' => $stats,
        ]);
    }

    /* =========================================================
     |  CREATE
     ========================================================= */

    public function create(): Response
    {
        return Inertia::render('investors/create', [
            'users' => User::query()
                ->notTrashed()
                ->where('role', User::ROLE_INVESTOR)
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /* =========================================================
     |  STORE
     ========================================================= */

    public function store(StoreInvestorRequest $request): RedirectResponse
    {
        $investor = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['created_by'] = Auth::id();
            $data['user_id']    = !empty($data['user_id']) ? $data['user_id'] : null;

            $investor = Investor::create($data);

            $investor->getOrCreateWallet();

            return $investor;
        });

        return redirect()
            ->route('investors.show', $investor->id)
            ->with('success', "Investor \"{$investor->full_name}\" was created successfully.");
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(Investor $investor): Response
    {
        if ($investor->trash) {
            abort(404);
        }

        if (! $investor->wallet) {
            $investor->getOrCreateWallet();
        }

        $investor->load([
            'wallet',
            'user:id,name,email',
            'creator:id,name,email',
            'investments' => fn ($q) => $q->latest()->limit(10),
            'transactions' => fn ($q) => $q->latest()->limit(20),
            'withdrawals' => fn ($q) => $q->latest()->limit(20),
            'batches' => fn ($q) => $q->with(['variant.product:id,name', 'variant:id,product_id,name'])
                                       ->where('remaining_quantity', '>', 0)
                                       ->latest()
                                       ->limit(20),
        ]);

        $stats = [
            'total_investments'  => $investor->investments()->count(),
            'active_investments' => $investor->investments()->whereIn('status', ['active', 'partially_used'])->count(),
            'total_transactions' => $investor->transactions()->count(),
            'total_withdrawals'  => $investor->withdrawals()->count(),
            'total_batches'      => $investor->batches()->count(),
            'active_batches'     => $investor->batches()->where('remaining_quantity', '>', 0)->count(),
        ];

        return Inertia::render('investors/show', [
            'investor' => $investor,
            'stats'    => $stats,
        ]);
    }

    /* =========================================================
     |  EDIT
     ========================================================= */

    public function edit(Investor $investor): Response
    {
        if ($investor->trash) {
            abort(404);
        }

        return Inertia::render('investors/edit', [
            'investor' => $investor,
            'users'    => User::query()
                ->notTrashed()
                ->where('role', User::ROLE_INVESTOR)
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /* =========================================================
     |  UPDATE
     ========================================================= */

    public function update(UpdateInvestorRequest $request, Investor $investor): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = !empty($data['user_id']) ? $data['user_id'] : null;

        $investor->update($data);

        return redirect()
            ->route('investors.show', $investor->id)
            ->with('success', "Investor \"{$investor->full_name}\" was updated successfully.");
    }

    /* =========================================================
     |  DESTROY — move to trash
     ========================================================= */

    public function destroy(Investor $investor): RedirectResponse
    {
        if ($investor->wallet && (float) $investor->wallet->capital_balance_afn > 0.01) {
            return back()->with('error', 'Cannot delete investor with active capital balance.');
        }

        if ($investor->investments()->whereIn('status', ['active', 'partially_used'])->exists()) {
            return back()->with('error', 'Cannot delete investor with active investments.');
        }

        $name = $investor->full_name;

        $investor->update([
            'trash'      => true,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('investors.index')
            ->with('success', "Investor \"{$name}\" was moved to trash.");
    }

    /* =========================================================
     |  RESTORE / FORCE DELETE / TRASH
     ========================================================= */

    public function restore(int $id): RedirectResponse
    {
        $investor = Investor::onlyTrashed()->findOrFail($id);

        $investor->update([
            'trash'      => false,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('investors.index')
            ->with('success', "Investor \"{$investor->full_name}\" was restored.");
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $investor = Investor::onlyTrashed()->findOrFail($id);

        if ($investor->wallet && (float) $investor->wallet->capital_balance_afn > 0.01) {
            return back()->with('error', 'Cannot permanently delete investor with active capital balance.');
        }

        if ($investor->investments()->exists()) {
            return back()->with('error', 'Cannot permanently delete investor with investment history.');
        }

        $name = $investor->full_name;
        $investor->forceDelete();

        return redirect()
            ->route('investors.index')
            ->with('success', "Investor \"{$name}\" was permanently deleted.");
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

    /* =========================================================
     |  TOGGLE STATUS
     ========================================================= */

    public function toggleStatus(Investor $investor): RedirectResponse
    {
        $investor->update([
            'status'     => $investor->status === 'active' ? 'inactive' : 'active',
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', "Investor status changed to {$investor->status}.");
    }
}