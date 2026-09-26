<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCurrencyRequest;
use App\Http\Requests\UpdateCurrencyRequest;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{
    /* =========================================================
     |  INDEX
     ========================================================= */

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $currencies = Currency::query()
            ->with('creator:id,name')
            ->notTrashed()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%")
                      ->orWhere('symbol', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('is_base')
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('currencies/index', [
            'currencies' => $currencies,
            'filters'    => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /* =========================================================
     |  CREATE
     ========================================================= */

    public function create(): Response
    {
        return Inertia::render('currencies/create');
    }

    /* =========================================================
     |  STORE
     ========================================================= */

    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();
        $data['is_base'] = $data['is_base'] ?? false;

        // اگر is_base=true، بقیه را false کن
        DB::transaction(function () use (&$data) {
            if (! empty($data['is_base'])) {
                Currency::where('is_base', true)->update(['is_base' => false]);
            }
            Currency::create($data);
        });

        return redirect()
            ->route('currencies.index')
            ->with('success', "Currency \"{$data['code']}\" was created successfully.");
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(Currency $currency): Response
    {
        if ($currency->trash) {
            abort(404);
        }

        $currency->load('creator:id,name,email');

        return Inertia::render('currencies/show', [
            'currency' => $currency,
        ]);
    }

    /* =========================================================
     |  EDIT
     ========================================================= */

    public function edit(Currency $currency): Response
    {
        if ($currency->trash) {
            abort(404);
        }

        return Inertia::render('currencies/edit', [
            'currency' => $currency,
        ]);
    }

    /* =========================================================
     |  UPDATE
     ========================================================= */

    public function update(UpdateCurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();
        $data['is_base'] = $data['is_base'] ?? false;

        DB::transaction(function () use ($currency, &$data) {
            if (! empty($data['is_base'])) {
                Currency::where('id', '!=', $currency->id)
                    ->where('is_base', true)
                    ->update(['is_base' => false]);
            }
            $currency->update($data);
        });

        return redirect()
            ->route('currencies.index')
            ->with('success', "Currency \"{$currency->code}\" was updated successfully.");
    }

    /* =========================================================
     |  DESTROY — move to trash
     ========================================================= */

    public function destroy(Currency $currency): RedirectResponse
    {
        if ($currency->is_base) {
            return back()->with('error', 'You cannot trash the base currency.');
        }

        $code = $currency->code;

        $currency->update([
            'trash'   => true,
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('currencies.index')
            ->with('success', "Currency \"{$code}\" was moved to trash.");
    }

    /* =========================================================
     |  RESTORE
     ========================================================= */

    public function restore(int $id): RedirectResponse
    {
        $currency = Currency::onlyTrashed()->findOrFail($id);

        $currency->update([
            'trash'   => false,
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('currencies.index')
            ->with('success', "Currency \"{$currency->code}\" was restored.");
    }

    /* =========================================================
     |  FORCE DELETE
     ========================================================= */

    public function forceDelete(int $id): RedirectResponse
    {
        $currency = Currency::onlyTrashed()->findOrFail($id);

        $code = $currency->code;
        $currency->forceDelete();

        return redirect()
            ->route('currencies.index')
            ->with('success', "Currency \"{$code}\" was permanently deleted.");
    }

    /* =========================================================
     |  TRASH LIST
     ========================================================= */

    public function trash(): Response
    {
        $currencies = Currency::onlyTrashed()
            ->with('creator:id,name')
            ->orderByDesc('updated_at')
            ->paginate(15);

        return Inertia::render('currencies/trash', [
            'currencies' => $currencies,
        ]);
    }

    /* =========================================================
     |  TOGGLE STATUS
     ========================================================= */

    public function toggleStatus(Currency $currency): RedirectResponse
    {
        if ($currency->is_base && $currency->status === 'active') {
            return back()->with('error', 'You cannot deactivate the base currency.');
        }

        $currency->update([
            'status'  => $currency->status === 'active' ? 'inactive' : 'active',
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', "Currency status changed to {$currency->status}.");
    }

    /* =========================================================
     |  SET AS BASE
     ========================================================= */

    public function setAsBase(Currency $currency): RedirectResponse
    {
        if ($currency->trash) {
            return back()->with('error', 'Cannot set a trashed currency as base.');
        }

        DB::transaction(function () use ($currency) {
            Currency::where('is_base', true)->update(['is_base' => false]);
            $currency->update([
                'is_base' => true,
                'status'  => 'active',
                'user_id' => Auth::id(),
            ]);
        });

        return back()->with('success', "Currency \"{$currency->code}\" is now the base currency.");
    }
}