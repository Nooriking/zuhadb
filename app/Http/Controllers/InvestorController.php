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
use Illuminate\Validation\ValidationException;
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
                'wallet.currency:id,code,name,symbol',
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
     |  CREATE — ✅ فقط یوزرهای بدون investor
     ========================================================= */

    public function create(): Response
    {
        // ✅ user_id هایی که قبلاً investor فعال دارند (trash = false)
        $usedUserIds = Investor::query()
            ->notTrashed()
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->toArray();

        return Inertia::render('investors/create', [
            'users' => User::query()
                ->notTrashed()
                ->where('role', User::ROLE_INVESTOR)
                ->whereNotIn('id', $usedUserIds)  // ✅ حذف کاربران استفاده‌شده
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /* =========================================================
     |  STORE — ✅ پشتیبانی از سرمایه‌گذار نقدی و جنسی + جلوگیری از تکرار user_id
     ========================================================= */

       /* =========================================================
     |  STORE — ✅ پشتیبانی از سرمایه‌گذار نقدی و جنسی + جلوگیری از تکرار user_id
     ========================================================= */

    public function store(StoreInvestorRequest $request): RedirectResponse
    {
        $investor = DB::transaction(function () use ($request) {
            $data = $request->validated();

            // ✅ بررسی نهایی: آیا این user_id قبلاً استفاده شده؟
            if (! empty($data['user_id'])) {
                $userId = (int) $data['user_id'];

                // بررسی در فعال‌ها
                $activeConflict = Investor::query()
                    ->where('user_id', $userId)
                    ->where('trash', false)
                    ->exists();

                if ($activeConflict) {
                    throw ValidationException::withMessages([
                        'user_id' => 'This user is already linked to another investor.',
                    ]);
                }

                // بررسی در trash شده‌ها — اگر بود، restore کن و دیتای جدید اعمال کن
                $trashedInvestor = Investor::withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)
                    ->where('user_id', $userId)
                    ->where('trash', true)
                    ->first();

                if ($trashedInvestor) {
                    // Restore investor قدیمی
                    $trashedInvestor->update([
                        'trash'      => false,
                        'deleted_at' => null,
                        'full_name'  => $data['full_name'] ?? $trashedInvestor->full_name,
                        'phone'      => $data['phone'] ?? $trashedInvestor->phone,
                        'email'      => $data['email'] ?? $trashedInvestor->email,
                        'address'    => $data['address'] ?? $trashedInvestor->address,
                        'notes'      => $data['notes'] ?? $trashedInvestor->notes,
                        'status'     => $data['status'] ?? 'active',
                        'created_by' => Auth::id(),
                    ]);

                    // ✅ اگر capital دارد، investment بساز
                    $capitalAmount = (float) ($data['capital_amount'] ?? 0);
                    $exchangeRate  = (float) ($data['exchange_rate'] ?? 1);

                    if ($capitalAmount > 0.01) {
                        $trashedInvestor->investments()->create([
                            'investment_number'    => Investment::generateInvestmentNumber(),
                            'capital_amount_afn'   => $capitalAmount,
                            'available_amount_afn' => $capitalAmount,
                            'allocated_amount_afn' => 0,
                            'profit_amount_afn'    => 0,
                            'withdrawn_amount_afn' => 0,
                            'source_type'          => 'cash',
                            'exchange_rate'        => $exchangeRate > 0 ? $exchangeRate : 1,
                            'status'               => 'active',
                            'investment_date'      => now()->toDateString(),
                            'notes'                => 'Initial capital deposit (restored)',
                            'created_by'           => Auth::id(),
                        ]);

                        $wallet = $trashedInvestor->getOrCreateWallet();
                        $wallet->update([
                            'capital_balance_afn' => $capitalAmount,
                            'total_invested_afn'  => $capitalAmount,
                            'last_transaction_at' => now(),
                        ]);
                    } else {
                        $trashedInvestor->getOrCreateWallet();
                    }

                    return $trashedInvestor->fresh();
                }
            }

            // ✅ جدا کردن فیلدهای اضافی از دیتای جدول investors
            $capitalAmount = (float) ($data['capital_amount'] ?? 0);
            $exchangeRate  = (float) ($data['exchange_rate'] ?? 1);

            unset($data['capital_amount'], $data['exchange_rate']);

            // ✅ متادیتای استاندارد
            $data['created_by'] = Auth::id();
            $data['user_id']    = ! empty($data['user_id']) ? $data['user_id'] : null;
            $data['status']     = $data['status'] ?? 'active';
            $data['trash']      = false;

            // ✅ ساخت سرمایه‌گذار
            $investor = Investor::create($data);

            // ✅ ساخت wallet
            $wallet = $investor->getOrCreateWallet();

            // ✅ فقط اگر capital > 0 بود → investment pot بساز
            if ($capitalAmount > 0.01) {
                $investor->investments()->create([
                    'investment_number'    => Investment::generateInvestmentNumber(),
                    'capital_amount_afn'   => $capitalAmount,
                    'available_amount_afn' => $capitalAmount,
                    'allocated_amount_afn' => 0,
                    'profit_amount_afn'    => 0,
                    'withdrawn_amount_afn' => 0,
                    'source_type'          => 'cash',
                    'exchange_rate'        => $exchangeRate > 0 ? $exchangeRate : 1,
                    'status'               => 'active',
                    'investment_date'      => now()->toDateString(),
                    'notes'                => 'Initial capital deposit',
                    'created_by'           => Auth::id(),
                ]);

                $wallet->update([
                    'capital_balance_afn' => $capitalAmount,
                    'total_invested_afn'  => $capitalAmount,
                    'last_transaction_at' => now(),
                ]);
            }

            return $investor;
        });

        return redirect()
            ->route('investors.index')
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
            'wallet.currency:id,code,name,symbol',
            'user:id,name,email',
            'creator:id,name,email',
            'investments' => fn ($q) => $q->with('currency:id,code,name,symbol')->latest()->limit(10),
            'transactions' => fn ($q) => $q->with('currency:id,code,name,symbol')->latest()->limit(20),
            'withdrawals' => fn ($q) => $q->with('currency:id,code,name,symbol')->latest()->limit(20),
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

            // ✅ آمار جدید: نقدی vs جنسی
            'has_cash_capital'       => $investor->hasCashCapital(),
            'is_goods_investor'      => $investor->isGoodsInvestor(),
            'active_goods_value_afn' => $investor->active_goods_value_afn,
            'active_goods_count'     => $investor->active_goods_count,
            'available_cash_afn'     => $investor->available_cash_afn,
        ];

        return Inertia::render('investors/show', [
            'investor' => $investor,
            'stats'    => $stats,
        ]);
    }

    /* =========================================================
     |  EDIT — ✅ فقط یوزرهای بدون investor (به جز خود این investor)
     ========================================================= */

    public function edit(Investor $investor): Response
    {
        if ($investor->trash) {
            abort(404);
        }

        // ✅ user_id هایی که قبلاً investor فعال دارند، به جز investor فعلی
        $usedUserIds = Investor::query()
            ->notTrashed()
            ->whereNotNull('user_id')
            ->where('id', '!=', $investor->id)   // ✅ استثنای خود این investor
            ->pluck('user_id')
            ->toArray();

        return Inertia::render('investors/edit', [
            'investor' => $investor,
            'users'    => User::query()
                ->notTrashed()
                ->where('role', User::ROLE_INVESTOR)
                ->whereNotIn('id', $usedUserIds)  // ✅ حذف کاربران استفاده‌شده
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /* =========================================================
     |  UPDATE — ✅ جلوگیری از تکرار user_id
     ========================================================= */

    public function update(UpdateInvestorRequest $request, Investor $investor): RedirectResponse
    {
        $data = $request->validated();

        // ✅ بررسی: آیا user_id جدید به investor دیگری تعلق دارد؟
        if (! empty($data['user_id']) && (int) $data['user_id'] !== (int) $investor->user_id) {
            $alreadyUsed = Investor::query()
                ->notTrashed()
                ->where('user_id', $data['user_id'])
                ->where('id', '!=', $investor->id)
                ->exists();

            if ($alreadyUsed) {
                throw ValidationException::withMessages([
                    'user_id' => 'This user is already linked to another investor.',
                ]);
            }
        }

        $data['user_id'] = ! empty($data['user_id']) ? $data['user_id'] : null;

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

        // ✅ مدل خودش user_id را NULL می‌کند (در booted)
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

        // ✅ بررسی: آیا user_id این investor با investor فعال دیگری تضاد دارد؟
        if ($investor->user_id) {
            $conflict = Investor::query()
                ->notTrashed()
                ->where('user_id', $investor->user_id)
                ->where('id', '!=', $investor->id)
                ->exists();

            if ($conflict) {
                return back()->with('error', 'Cannot restore: this user is already linked to another active investor.');
            }
        }

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