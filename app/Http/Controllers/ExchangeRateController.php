<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExchangeRateRequest;
use App\Http\Requests\UpdateExchangeRateRequest;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeRateController extends Controller
{
    /* =========================================================
     |  INDEX
     ========================================================= */

    public function index(Request $request): Response
    {
        $fromCurrency = $request->query('from_currency_id');
        $toCurrency   = $request->query('to_currency_id');

        $exchangeRates = ExchangeRate::query()
            ->with(['fromCurrency:id,code,name,symbol', 'toCurrency:id,code,name,symbol', 'creator:id,name'])
            ->notTrashed()
            ->when($fromCurrency, fn ($q, $v) => $q->where('from_currency_id', $v))
            ->when($toCurrency, fn ($q, $v) => $q->where('to_currency_id', $v))
            ->latestFirst()
            ->paginate(15)
            ->withQueryString();

        $currencies = Currency::notTrashed()->active()->select('id', 'code', 'name', 'symbol')->get();

        return Inertia::render('exchange-rates/index', [
            'exchangeRates' => $exchangeRates,
            'currencies'    => $currencies,
            'filters'       => [
                'from_currency_id' => $fromCurrency,
                'to_currency_id'   => $toCurrency,
            ],
        ]);
    }

    /* =========================================================
     |  CREATE
     ========================================================= */

    public function create(): Response
    {
        $currencies = Currency::notTrashed()->active()->select('id', 'code', 'name', 'symbol')->get();

        return Inertia::render('exchange-rates/create', [
            'currencies' => $currencies,
        ]);
    }

    /* =========================================================
     |  STORE
     ========================================================= */

    public function store(StoreExchangeRateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = Auth::id();

        $exchangeRate = ExchangeRate::create($data);

        return redirect()
            ->route('exchange-rates.index')
            ->with('success', "Exchange rate created successfully.");
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(ExchangeRate $exchangeRate): Response
    {
        if ($exchangeRate->trash) {
            abort(404);
        }

        $exchangeRate->load(['fromCurrency', 'toCurrency', 'creator:id,name,email']);

        return Inertia::render('exchange-rates/show', [
            'exchangeRate' => $exchangeRate,
        ]);
    }

    /* =========================================================
     |  EDIT
     ========================================================= */

    public function edit(ExchangeRate $exchangeRate): Response
    {
        if ($exchangeRate->trash) {
            abort(404);
        }

        $currencies = Currency::notTrashed()->active()->select('id', 'code', 'name', 'symbol')->get();

        return Inertia::render('exchange-rates/edit', [
            'exchangeRate' => $exchangeRate,
            'currencies'   => $currencies,
        ]);
    }

    /* =========================================================
     |  UPDATE
     ========================================================= */

    public function update(UpdateExchangeRateRequest $request, ExchangeRate $exchangeRate): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = Auth::id();

        $exchangeRate->update($data);

        return redirect()
            ->route('exchange-rates.index')
            ->with('success', "Exchange rate updated successfully.");
    }

    /* =========================================================
     |  DESTROY — move to trash
     ========================================================= */

    public function destroy(ExchangeRate $exchangeRate): RedirectResponse
    {
        $exchangeRate->update([
            'trash'      => true,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('exchange-rates.index')
            ->with('success', 'Exchange rate was moved to trash.');
    }

    /* =========================================================
     |  RESTORE
     ========================================================= */

    public function restore(int $id): RedirectResponse
    {
        $exchangeRate = ExchangeRate::onlyTrashed()->findOrFail($id);

        $exchangeRate->update([
            'trash'      => false,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('exchange-rates.index')
            ->with('success', 'Exchange rate was restored.');
    }

    /* =========================================================
     |  FORCE DELETE
     ========================================================= */

    public function forceDelete(int $id): RedirectResponse
    {
        $exchangeRate = ExchangeRate::onlyTrashed()->findOrFail($id);

        $exchangeRate->delete();

        return redirect()
            ->route('exchange-rates.index')
            ->with('success', 'Exchange rate was permanently deleted.');
    }

    /* =========================================================
     |  TRASH LIST
     ========================================================= */

    public function trash(): Response
    {
        $exchangeRates = ExchangeRate::onlyTrashed()
            ->with(['fromCurrency:id,code,name,symbol', 'toCurrency:id,code,name,symbol', 'creator:id,name'])
            ->latestFirst()
            ->paginate(15);

        return Inertia::render('exchange-rates/trash', [
            'exchangeRates' => $exchangeRates,
        ]);
    }
}