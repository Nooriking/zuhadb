<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRateHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeRateHistoryController extends Controller
{
    /* =========================================================
     |  INDEX
     ========================================================= */

    public function index(Request $request): Response
    {
        $fromDate = $request->query('from_date');
        $toDate   = $request->query('to_date');

        $rates = ExchangeRateHistory::query()
            ->with('creator:id,name')
            ->when($fromDate, fn ($q, $v) => $q->whereDate('rate_date', '>=', $v))
            ->when($toDate, fn ($q, $v) => $q->whereDate('rate_date', '<=', $v))
            ->orderByDesc('rate_date')
            ->paginate(30)
            ->withQueryString();

        $stats = [
            'latest_rate'        => (float) (ExchangeRateHistory::latestRate()?->usd_to_afn ?? 0),
            'latest_date'        => ExchangeRateHistory::latestRate()?->rate_date,
            'avg_this_month'     => ExchangeRateHistory::averageThisMonth(),
            'min_this_month'     => ExchangeRateHistory::monthMinMax()['min'],
            'max_this_month'     => ExchangeRateHistory::monthMinMax()['max'],
            'total_records'      => ExchangeRateHistory::count(),
        ];

        return Inertia::render('exchange-rate-history/index', [
            'rates'   => $rates,
            'filters' => [
                'from_date' => $fromDate,
                'to_date'   => $toDate,
            ],
            'stats' => $stats,
        ]);
    }

    /* =========================================================
     |  CREATE
     ========================================================= */

    public function create(): Response
    {
        return Inertia::render('exchange-rate-history/create', [
            'latestRate' => ExchangeRateHistory::latestRate()?->usd_to_afn,
        ]);
    }

    /* =========================================================
     |  STORE
     ========================================================= */

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rate_date'  => ['required', 'date', 'unique:exchange_rate_history,rate_date'],
            'usd_to_afn' => ['required', 'numeric', 'min:0.0001'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['created_by'] = Auth::id();

        $rate = ExchangeRateHistory::create($validated);

        return redirect()
            ->route('exchange-rate-history.index')
            ->with('success', "USD rate for {$rate->rate_date->format('Y-m-d')} was saved.");
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(ExchangeRateHistory $exchangeRateHistory): Response
    {
        $exchangeRateHistory->load('creator:id,name,email');

        return Inertia::render('exchange-rate-history/show', [
            'rate' => $exchangeRateHistory,
        ]);
    }

    /* =========================================================
     |  EDIT
     ========================================================= */

    public function edit(ExchangeRateHistory $exchangeRateHistory): Response
    {
        return Inertia::render('exchange-rate-history/edit', [
            'rate' => $exchangeRateHistory,
        ]);
    }

    /* =========================================================
     |  UPDATE
     ========================================================= */

    public function update(Request $request, ExchangeRateHistory $exchangeRateHistory): RedirectResponse
    {
        $validated = $request->validate([
            'rate_date'  => [
                'required',
                'date',
                Rule::unique('exchange_rate_history', 'rate_date')->ignore($exchangeRateHistory->id),
            ],
            'usd_to_afn' => ['required', 'numeric', 'min:0.0001'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ]);

        $exchangeRateHistory->update($validated);

        return redirect()
            ->route('exchange-rate-history.index')
            ->with('success', 'USD rate was updated.');
    }

    /* =========================================================
     |  DESTROY
     ========================================================= */

    public function destroy(ExchangeRateHistory $exchangeRateHistory): RedirectResponse
    {
        $date = $exchangeRateHistory->rate_date->format('Y-m-d');
        $exchangeRateHistory->delete();

        return redirect()
            ->route('exchange-rate-history.index')
            ->with('success', "Rate for {$date} was deleted.");
    }

    /* =========================================================
     |  QUICK STORE — ثبت سریع نرخ امروز (برای داشبورد)
     ========================================================= */

    public function quickStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'usd_to_afn' => ['required', 'numeric', 'min:0.0001'],
        ]);

        $today = now()->toDateString();

        DB::transaction(function () use ($validated, $today) {
            ExchangeRateHistory::updateOrCreate(
                ['rate_date' => $today],
                [
                    'usd_to_afn' => $validated['usd_to_afn'],
                    'created_by' => Auth::id(),
                ],
            );
        });

        return back()->with('success', "Today's rate ({$today}) was saved.");
    }
}