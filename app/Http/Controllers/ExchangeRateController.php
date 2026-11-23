<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExchangeRateRequest;
use App\Http\Requests\UpdateExchangeRateRequest;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\CurrencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeRateController extends Controller
{
    public function __construct(private readonly CurrencyService $currencies) {}

    /* =========================================================
     |  INDEX
     ========================================================= */

    public function index(Request $request): Response
    {
        $fromCurrency = $request->query('from_currency_id');
        $toCurrency = $request->query('to_currency_id');
        $search = trim((string) $request->query('search', ''));
        $sort = $request->query('sort', 'effective_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['effective_at', 'rate', 'created_at', 'updated_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'effective_at';
        }

        $exchangeRates = ExchangeRate::query()
            ->with(['fromCurrency:id,code,name,symbol', 'toCurrency:id,code,name,symbol', 'creator:id,name'])
            ->notTrashed()
            ->when($fromCurrency, fn ($q) => $q->where('from_currency_id', $fromCurrency))
            ->when($toCurrency, fn ($q) => $q->where('to_currency_id', $toCurrency))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->whereHas('fromCurrency', function ($currency) use ($search) {
                        $currency->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })->orWhereHas('toCurrency', function ($currency) use ($search) {
                        $currency->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })->orWhere('source', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $currencies = Currency::notTrashed()->active()->select('id', 'code', 'name', 'symbol')->get();

        return Inertia::render('exchange-rates/index', [
            'exchangeRates' => $exchangeRates,
            'currencies' => $currencies,
            'currentRates' => $this->currentActiveRates(),
            'filters' => [
                'from_currency_id' => $fromCurrency,
                'to_currency_id' => $toCurrency,
                'search' => $search !== '' ? $search : null,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /* =========================================================
     |  HISTORY
     ========================================================= */

    public function history(Request $request): Response
    {
        $fromCurrency = $request->query('from_currency_id');
        $toCurrency = $request->query('to_currency_id');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $search = trim((string) $request->query('search', ''));

        $exchangeRates = ExchangeRate::query()
            ->with(['fromCurrency:id,code,name,symbol', 'toCurrency:id,code,name,symbol', 'creator:id,name'])
            ->notTrashed()
            ->when($fromCurrency, fn ($q) => $q->where('from_currency_id', $fromCurrency))
            ->when($toCurrency, fn ($q) => $q->where('to_currency_id', $toCurrency))
            ->when($fromDate, fn ($q) => $q->whereDate('effective_at', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('effective_at', '<=', $toDate))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->whereHas('fromCurrency', function ($currency) use ($search) {
                        $currency->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })->orWhereHas('toCurrency', function ($currency) use ($search) {
                        $currency->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })->orWhere('source', 'like', "%{$search}%");
                });
            })
            ->latestFirst()
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $currencies = Currency::notTrashed()->active()->select('id', 'code', 'name', 'symbol')->get();

        return Inertia::render('exchange-rates/history', [
            'exchangeRates' => $exchangeRates,
            'currencies' => $currencies,
            'filters' => [
                'from_currency_id' => $fromCurrency,
                'to_currency_id' => $toCurrency,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'search' => $search !== '' ? $search : null,
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
            'sources' => $this->allowedSources(),
        ]);
    }

    /* =========================================================
     |  STORE
     ========================================================= */

    public function store(StoreExchangeRateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $from = Currency::query()->findOrFail($data['from_currency_id']);
        $to = Currency::query()->findOrFail($data['to_currency_id']);

        $this->currencies->postRate(
            $from,
            $to,
            (string) $data['rate'],
            $data['effective_at'],
            Auth::id(),
            $data['source'] ?? 'manual',
        );

        return redirect()
            ->route('exchange-rates.index')
            ->with('success', 'Exchange rate created successfully.');
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(ExchangeRate $exchangeRate): Response
    {
        if ($exchangeRate->trash) {
            abort(404);
        }

        $exchangeRate->load(['fromCurrency', 'toCurrency', 'creator:id,name']);

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
            'currencies' => $currencies,
            'sources' => $this->allowedSources(),
        ]);
    }

    /* =========================================================
     |  UPDATE
     ========================================================= */

    public function update(UpdateExchangeRateRequest $request, ExchangeRate $exchangeRate): RedirectResponse
    {
        if ($exchangeRate->trash) {
            abort(404);
        }

        $data = $request->validated();
        $from = Currency::query()->findOrFail($data['from_currency_id']);
        $to = Currency::query()->findOrFail($data['to_currency_id']);

        // Same board convention as store/postRate: AFN→USD/AED is flipped to foreign→AFN.
        if (strtoupper((string) $from->code) === 'AFN' && in_array(strtoupper((string) $to->code), ['USD', 'AED'], true)) {
            $data['from_currency_id'] = $to->id;
            $data['to_currency_id'] = $from->id;
        }

        $data['created_by'] = Auth::id();

        $exchangeRate->update($data);

        return redirect()
            ->route('exchange-rates.index')
            ->with('success', 'Exchange rate updated successfully.');
    }

    /* =========================================================
     |  DESTROY — move to trash
     ========================================================= */

    public function destroy(ExchangeRate $exchangeRate): RedirectResponse
    {
        $exchangeRate->update([
            'trash' => true,
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
            'trash' => false,
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
            ->route('exchange-rates.trash')
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

    /* =========================================================
     |  HELPERS
     ========================================================= */

    /**
     * @return list<string>
     */
    private function allowedSources(): array
    {
        return ['manual', 'market', 'bank', 'other'];
    }

    /**
     * Latest effective non-trashed rate per currency pair.
     *
     * @return list<array<string, mixed>>
     */
    private function currentActiveRates(): array
    {
        $rows = ExchangeRate::query()
            ->with(['fromCurrency:id,code,name,symbol', 'toCurrency:id,code,name,symbol', 'creator:id,name'])
            ->notTrashed()
            ->where('effective_at', '<=', now())
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->get();

        $seen = [];
        $current = [];

        foreach ($rows as $row) {
            $key = $row->from_currency_id.'-'.$row->to_currency_id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $current[] = $row;
        }

        return $current;
    }
}
