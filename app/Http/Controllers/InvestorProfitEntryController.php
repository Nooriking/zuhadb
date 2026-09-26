<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorProfitEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvestorProfitEntryController extends Controller
{
    /* =========================================================
     |  INDEX — لیست همه‌ی سودها
     ========================================================= */

    public function index(Request $request): Response
    {
        $investorId   = $request->query('investor_id');
        $investmentId = $request->query('investment_id');
        $status       = $request->query('status');
        $entryType    = $request->query('entry_type');

        $entries = InvestorProfitEntry::query()
            ->with([
                'investor:id,full_name,phone',
                'investment:id,investment_number',
                'sale:id,invoice_number',
                'payer:id,name',
            ])
            ->when($investorId, fn ($q, $v) => $q->where('investor_id', $v))
            ->when($investmentId, fn ($q, $v) => $q->where('investment_id', $v))
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->when($entryType, fn ($q, $v) => $q->where('entry_type', $v))
            ->orderByDesc('earned_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Stats
        $stats = [
            'total_entries'    => InvestorProfitEntry::count(),
            'pending_count'    => InvestorProfitEntry::pending()->count(),
            'paid_count'       => InvestorProfitEntry::paid()->count(),
            'total_profit_afn' => (float) InvestorProfitEntry::where('entry_type', 'profit')->where('status', '!=', 'cancelled')->sum('profit_amount_afn'),
            'pending_profit'   => (float) InvestorProfitEntry::pending()->sum('profit_amount_afn'),
            'paid_profit'      => (float) InvestorProfitEntry::paid()->sum('profit_amount_afn'),
        ];

        return Inertia::render('investor-profit-entries/index', [
            'entries'    => $entries,
            'investors'  => Investor::notTrashed()->select('id', 'full_name')->orderBy('full_name')->get(),
            'investments'=> Investment::notTrashed()->select('id', 'investment_number')->orderByDesc('id')->limit(200)->get(),
            'filters'    => [
                'investor_id'   => $investorId,
                'investment_id' => $investmentId,
                'status'        => $status,
                'entry_type'    => $entryType,
            ],
            'stats' => $stats,
        ]);
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(InvestorProfitEntry $entry): Response
    {
        $entry->load([
            'investor:id,full_name,phone,email',
            'investment:id,investment_number,capital_amount_afn',
            'productAllocation.batch:id,batch_code',
            'productAllocation.variant.product:id,name',
            'productAllocation.variant.product.brand:id,name',
            'sale:id,invoice_number,total_afn',
            'saleItem:id,quantity,line_total_afn',
            'payer:id,name,email',
        ]);

        return Inertia::render('investor-profit-entries/show', [
            'entry' => $entry,
        ]);
    }

    /* =========================================================
     |  MARK AS PAID
     ========================================================= */

    public function markAsPaid(InvestorProfitEntry $entry): RedirectResponse
    {
        if ($entry->status === 'paid') {
            return back()->with('error', 'Already paid.');
        }

        if ($entry->status === 'cancelled') {
            return back()->with('error', 'Cancelled entries cannot be marked as paid.');
        }

        $entry->markAsPaid();

        return back()->with('success', 'Profit entry marked as paid.');
    }

    /* =========================================================
     |  MARK MULTIPLE AS PAID (Bulk)
     ========================================================= */

    public function markBatchAsPaid(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['exists:investor_profit_entries,id'],
        ]);

        DB::transaction(function () use ($validated) {
            $entries = InvestorProfitEntry::whereIn('id', $validated['ids'])
                ->where('status', 'pending')
                ->get();

            foreach ($entries as $entry) {
                $entry->markAsPaid('Bulk payment');
            }
        });

        return back()->with('success', 'Selected entries marked as paid.');
    }

    /* =========================================================
     |  CANCEL
     ========================================================= */

    public function cancel(Request $request, InvestorProfitEntry $entry): RedirectResponse
    {
        if ($entry->status === 'paid') {
            return back()->with('error', 'Cannot cancel a paid entry.');
        }

        if ($entry->status === 'cancelled') {
            return back()->with('error', 'Already cancelled.');
        }

        $reason = $request->input('reason');

        try {
            $entry->cancel($reason);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Profit entry cancelled.');
    }

    /* =========================================================
     |  BY INVESTOR — سودهای یک سرمایه‌گذار خاص
     ========================================================= */

    public function byInvestor(Investor $investor): Response
    {
        $entries = InvestorProfitEntry::query()
            ->with(['investment:id,investment_number', 'sale:id,invoice_number'])
            ->where('investor_id', $investor->id)
            ->orderByDesc('earned_at')
            ->paginate(30);

        $investor->load('wallet');

        $stats = [
            'total_entries'   => InvestorProfitEntry::forInvestor($investor->id)->count(),
            'pending_profit'  => (float) InvestorProfitEntry::forInvestor($investor->id)->pending()->sum('profit_amount_afn'),
            'paid_profit'     => (float) InvestorProfitEntry::forInvestor($investor->id)->paid()->sum('profit_amount_afn'),
            'total_profit'    => (float) InvestorProfitEntry::forInvestor($investor->id)->sum('profit_amount_afn'),
        ];

        return Inertia::render('investor-profit-entries/by-investor', [
            'investor' => $investor,
            'entries'  => $entries,
            'stats'    => $stats,
        ]);
    }

    /* =========================================================
     |  BY INVESTMENT — سودهای یک Investment خاص
     ========================================================= */

    public function byInvestment(Investment $investment): Response
    {
        $entries = InvestorProfitEntry::query()
            ->with(['investor:id,full_name', 'sale:id,invoice_number'])
            ->where('investment_id', $investment->id)
            ->orderByDesc('earned_at')
            ->paginate(30);

        $investment->load('investor:id,full_name');

        $stats = [
            'total_entries'   => InvestorProfitEntry::forInvestment($investment->id)->count(),
            'pending_profit'  => (float) InvestorProfitEntry::forInvestment($investment->id)->pending()->sum('profit_amount_afn'),
            'paid_profit'     => (float) InvestorProfitEntry::forInvestment($investment->id)->paid()->sum('profit_amount_afn'),
            'total_profit'    => (float) InvestorProfitEntry::forInvestment($investment->id)->sum('profit_amount_afn'),
        ];

        return Inertia::render('investor-profit-entries/by-investment', [
            'investment' => $investment,
            'entries'    => $entries,
            'stats'      => $stats,
        ]);
    }
}