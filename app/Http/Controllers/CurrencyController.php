<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCurrencyRequest;
use App\Http\Requests\UpdateCurrencyRequest;
use App\Models\Currency;
use App\Services\CurrencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{
    public function __construct(
        private readonly CurrencyService $currencies,
    ) {}

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
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'rateDefinition' => 'One unit of this currency equals this many units of the default currency.',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('currencies/create', [
            'hasDefault' => Currency::query()->notTrashed()->base()->exists(),
            'rateDefinition' => 'One unit of this currency equals this many units of the default currency.',
        ]);
    }

    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();
        $data['is_base'] = (bool) ($data['is_base'] ?? false);

        $hasDefault = Currency::query()->notTrashed()->base()->exists();
        if (! $hasDefault) {
            $data['is_base'] = true;
            $data['rate_to_default'] = 1;
        }

        if ($data['is_base']) {
            $data['status'] = 'active';
        }

        DB::transaction(function () use ($data) {
            $currency = Currency::create($data);

            if ($currency->is_base) {
                $this->currencies->makeDefault($currency);
            }
        });

        return redirect()
            ->route('currencies.index')
            ->with('success', "Currency \"{$data['code']}\" was created successfully.");
    }

    public function show(Currency $currency): Response
    {
        if ($currency->trash) {
            abort(404);
        }

        $currency->load('creator:id,name,email');

        return Inertia::render('currencies/show', [
            'currency' => $currency,
            'rateDefinition' => 'One unit of this currency equals this many units of the default currency.',
        ]);
    }

    public function edit(Currency $currency): Response
    {
        if ($currency->trash) {
            abort(404);
        }

        return Inertia::render('currencies/edit', [
            'currency' => $currency,
            'rateDefinition' => 'One unit of this currency equals this many units of the default currency.',
        ]);
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();
        $data['is_base'] = (bool) ($data['is_base'] ?? false);

        if ($currency->is_base && $data['is_base'] === false) {
            return back()->withErrors([
                'is_base' => 'Choose another default currency first. The system must always have one default.',
            ]);
        }

        if ($currency->is_base && ($data['status'] ?? '') === 'inactive') {
            return back()->withErrors([
                'status' => 'The default currency cannot be inactive.',
            ]);
        }

        if ($data['is_base']) {
            $data['status'] = 'active';
        }

        DB::transaction(function () use ($currency, $data) {
            $becomingDefault = ! empty($data['is_base']) && ! $currency->is_base;

            $currency->update($data);

            if ($becomingDefault) {
                $this->currencies->makeDefault($currency->fresh());
            } elseif ($currency->is_base) {
                $currency->update(['rate_to_default' => 1]);
            }
        });

        return redirect()
            ->route('currencies.index')
            ->with('success', "Currency \"{$currency->code}\" was updated successfully.");
    }

    public function destroy(Currency $currency): RedirectResponse
    {
        if ($currency->is_base) {
            return back()->with('error', 'You cannot trash the default currency.');
        }

        $code = $currency->code;

        $currency->update([
            'trash' => true,
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('currencies.index')
            ->with('success', "Currency \"{$code}\" was moved to trash.");
    }

    public function restore(int $id): RedirectResponse
    {
        $currency = Currency::onlyTrashed()->findOrFail($id);

        $currency->update([
            'trash' => false,
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('currencies.index')
            ->with('success', "Currency \"{$currency->code}\" was restored.");
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $currency = Currency::onlyTrashed()->findOrFail($id);

        if ($currency->is_base) {
            return back()->with('error', 'You cannot permanently delete the default currency.');
        }

        $code = $currency->code;
        $currency->forceDelete();

        return redirect()
            ->route('currencies.index')
            ->with('success', "Currency \"{$code}\" was permanently deleted.");
    }

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

    public function toggleStatus(Currency $currency): RedirectResponse
    {
        if ($currency->is_base && $currency->status === 'active') {
            return back()->with('error', 'You cannot deactivate the default currency.');
        }

        $currency->update([
            'status' => $currency->status === 'active' ? 'inactive' : 'active',
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', "Currency status changed to {$currency->status}.");
    }

    public function setAsBase(Currency $currency): RedirectResponse
    {
        $this->currencies->makeDefault($currency);

        return back()->with('success', "Currency \"{$currency->code}\" is now the default currency.");
    }
}
