<?php

namespace App\Http\Controllers;

use App\Models\Investor;
use App\Models\InvestorTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvestorTransactionController extends Controller
{
    private function scopedInvestorId(?\Illuminate\Contracts\Auth\Authenticatable $user): ?int
    {
        if (! $user || ! method_exists($user, 'isInvestor') || ! $user->isInvestor()) {
            return null;
        }

        // Investor identity is the authenticated user row.
        return $user->trash ? -1 : (int) $user->id;
    }

    /* =========================================================
     |  INDEX — همه تراکنش‌ها
     ========================================================= */

    public function index(Request $request): Response
    {
        $ownInvestorId = $this->scopedInvestorId($request->user());
        $investorId = $ownInvestorId ?? $request->query('investor_id');
        $type       = $request->query('type');
        $from       = $request->query('from');
        $to         = $request->query('to');

        $transactions = InvestorTransaction::query()
            ->with([
                'investor:id,name,phone',
                'investment:id,investment_number',
                'creator:id,name',
            ])
            ->when($investorId, fn ($q, $v) => $q->where('investor_id', $v))
            ->when($type, fn ($q, $v) => $q->where('type', $v))
            ->when($from, fn ($q, $v) => $q->whereDate('transaction_date', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('transaction_date', '<=', $v))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('investor-transactions/index', [
            'transactions' => $transactions,
            'investors'    => $ownInvestorId
                ? Investor::notTrashed()->whereKey($ownInvestorId)->select('id', 'name', 'phone')->get()
                : Investor::notTrashed()->select('id', 'name', 'phone')->orderBy('name')->get(),
            'types'        => InvestorTransaction::TYPE_LABELS,
            'filters'      => [
                'investor_id' => $investorId,
                'type'        => $type,
                'from'        => $from,
                'to'          => $to,
            ],
        ]);
    }

    /* =========================================================
     |  SHOW — جزئیات یک تراکنش
     ========================================================= */

    public function show(InvestorTransaction $transaction): Response
    {
        $ownInvestorId = $this->scopedInvestorId(request()->user());
        if ($ownInvestorId !== null && (int) $transaction->investor_id !== $ownInvestorId) {
            abort(403);
        }

        $transaction->load([
            'investor:id,name,phone,email',
            'investment:id,investment_number,capital_amount_afn',
            'creator:id,name,email',
        ]);

        return Inertia::render('investor-transactions/show', [
            'transaction' => $transaction,
        ]);
    }

    /* =========================================================
     |  BY INVESTOR — تراکنش‌های یک سرمایه‌گذار خاص
     ========================================================= */

    public function byInvestor(Investor $investor): Response
    {
        $ownInvestorId = $this->scopedInvestorId(request()->user());
        if ($ownInvestorId !== null && (int) $investor->id !== $ownInvestorId) {
            abort(403);
        }

        $transactions = InvestorTransaction::query()
            ->with(['investment:id,investment_number', 'creator:id,name'])
            ->where('investor_id', $investor->id)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(30);

        $investor->load('wallet');

        return Inertia::render('investor-transactions/by-investor', [
            'investor'     => $investor,
            'transactions' => $transactions,
        ]);
    }
}