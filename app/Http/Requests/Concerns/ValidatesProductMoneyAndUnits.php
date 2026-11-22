<?php

namespace App\Http\Requests\Concerns;

use App\Models\Currency;
use App\Services\CatalogPriceConverter;
use App\Services\CurrencyService;
use App\Services\ExchangeService;
use App\Support\Money;
use App\Support\Validation\SafeData;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesProductMoneyAndUnits
{
    /**
     * Product-level pair rates: how many units of the target equal 1 USD.
     * Nullable so older products without rates remain valid.
     *
     * @return array<string, mixed>
     */
    protected function productExchangeRateRules(): array
    {
        return [
            'usd_to_afn_rate' => $this->positivePairRateRules(),
            'usd_to_aed_rate' => $this->positivePairRateRules(),
        ];
    }

    /**
     * @return list<string>
     */
    private function positivePairRateRules(): array
    {
        return [
            'nullable',
            'numeric',
            'gt:0',
            'max:999999.99999999',
            'regex:/^\d+(\.\d{1,8})?$/',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function variantMoneyRules(): array
    {
        $activeCurrency = Rule::exists('currencies', 'id')->where(
            fn ($query) => $query->where('trash', false)->where('status', 'active')->whereNull('deleted_at')
        );

        return [
            'variants.*.catalog_currency_id' => ['nullable', 'integer', $activeCurrency],
            'variants.*.purchase_amount' => SafeData::money(false, 0),
            'variants.*.sale_amount' => SafeData::money(false, 0),
            'variants.*.wholesale_amount' => SafeData::money(false, 0),
            'variants.*.purchase_currency_id' => ['nullable', 'integer', $activeCurrency],
            'variants.*.sale_currency_id' => ['nullable', 'integer', $activeCurrency],
            'variants.*.wholesale_currency_id' => ['nullable', 'integer', $activeCurrency],
            'purchases.*.profit_currency_id' => ['nullable', 'integer', $activeCurrency],
        ];
    }

    protected function validateProductMoneyAndUnits(Validator $validator): void
    {
        $service = app(CurrencyService::class);
        $converter = app(CatalogPriceConverter::class);
        $ledger = Currency::ledger();
        // Central Exchange Board (exchange_rates) only — never invent a silent 1:1.
        $rateOverrides = [];
        try {
            $exchange = app(ExchangeService::class);
            $usd = $exchange->snapshot('USD', 'AFN');
            $aed = $exchange->snapshot('AED', 'AFN');
            $current = $exchange->getCurrentRates();
            $rateOverrides = [
                'usd_to_afn' => (string) $usd['rate'],
                'aed_to_afn' => (string) $aed['rate'],
                'usd_to_aed' => (string) $current['usd_to_aed'],
                'source' => (string) ($usd['source'] ?? 'exchange_rates'),
            ];
        } catch (\Throwable) {
            // Left empty; convertIndependentSet / boardAt will surface a clear validation error.
        }

        $needsForeignFx = false;
        $supportedIds = Currency::query()
            ->notTrashed()
            ->whereIn('code', CatalogPriceConverter::SUPPORTED)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($this->input('variants', []) as $index => $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $purchaseCurrencyId = $variant['purchase_currency_id']
                ?? $variant['catalog_currency_id']
                ?? $ledger?->id;
            $saleCurrencyId = $variant['sale_currency_id']
                ?? $variant['catalog_currency_id']
                ?? $ledger?->id;
            $wholesaleCurrencyId = $variant['wholesale_currency_id']
                ?? $variant['catalog_currency_id']
                ?? $saleCurrencyId
                ?? $ledger?->id;

            foreach (
                [
                    'purchase_currency_id' => $purchaseCurrencyId,
                    'sale_currency_id' => $saleCurrencyId,
                    'wholesale_currency_id' => $wholesaleCurrencyId,
                ] as $field => $currencyId
            ) {
                if (empty($currencyId)) {
                    $validator->errors()->add(
                        "variants.{$index}.{$field}",
                        'Select a pricing currency (AFN, USD, or AED).'
                    );
                    continue 2;
                }

                if (! in_array((int) $currencyId, $supportedIds, true)) {
                    $validator->errors()->add(
                        "variants.{$index}.{$field}",
                        'Pricing currency must be AFN, USD, or AED.'
                    );
                    continue 2;
                }
            }

            $purchaseEntered = (float) ($variant['purchase_amount'] ?? $variant['purchase_price_afn'] ?? 0);
            $saleEntered = (float) ($variant['sale_amount'] ?? $variant['sale_price_afn'] ?? 0);
            $wholesaleEntered = (float) ($variant['wholesale_amount'] ?? $variant['wholesale_price_afn'] ?? 0);

            if ($purchaseEntered < 0 || $saleEntered < 0 || $wholesaleEntered < 0) {
                $validator->errors()->add(
                    "variants.{$index}.sale_amount",
                    'Catalog price amounts cannot be negative.'
                );
                continue;
            }

            try {
                $converted = $converter->convertIndependentSet(
                    [
                        'purchase' => [
                            'amount' => $purchaseEntered,
                            'currency' => (int) $purchaseCurrencyId,
                        ],
                        'sale' => [
                            'amount' => $saleEntered,
                            'currency' => (int) $saleCurrencyId,
                        ],
                        'wholesale' => [
                            'amount' => $wholesaleEntered,
                            'currency' => (int) $wholesaleCurrencyId,
                        ],
                    ],
                    $rateOverrides,
                    2,
                    "variants.{$index}",
                );

                $purchaseAfn = $converted['purchase']['afn'];
                $saleAfn = $converted['sale']['afn'];

                if ($purchaseAfn !== null && $saleAfn !== null && $saleAfn + 0 < $purchaseAfn + 0) {
                    $validator->errors()->add(
                        "variants.{$index}.sale_price_afn",
                        'Sale price cannot be less than the purchase price after converting both to the ledger currency.'
                    );
                }
            } catch (\Illuminate\Validation\ValidationException $e) {
                $needsForeignFx = true;
                foreach ($e->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add(
                            str_contains((string) $key, 'variants.') ? $key : "variants.{$index}.sale_currency_id",
                            $message
                        );
                    }
                }
            }
        }

        foreach ($this->input('purchases', []) as $index => $purchase) {
            if (! is_array($purchase)) {
                continue;
            }

            if (! empty($purchase['profit_currency_id']) && $ledger) {
                $this->toLedgerOrError(
                    $service,
                    (float) ($purchase['profit_per_unit'] ?? 0),
                    $purchase['profit_currency_id'],
                    "purchases.{$index}.profit_currency_id",
                    $validator,
                    $rateOverrides,
                    $needsForeignFx,
                );
            }
        }

        if ($needsForeignFx) {
            $board = $service->formBoardContext(now());
            $usdOk = (float) ($board['board']['usd_to_afn_rate'] ?? 0) > 0;
            $aedOk = (float) ($board['board']['aed_to_afn_rate'] ?? 0) > 0;
            if (! $usdOk || ! $aedOk) {
                $validator->errors()->add(
                    'variants.0.sale_currency_id',
                    'Central Exchange Board rates for USD→AFN and AED→AFN are required. Configure them under Exchange Rates before saving foreign-currency amounts.'
                );
            }
        }
    }

    /**
     * @param  array{usd_to_afn?: string, usd_to_aed?: string, aed_to_afn?: string, source?: string}  $rateOverrides
     */
    private function toLedgerOrError(
        CurrencyService $service,
        float $amount,
        mixed $currencyId,
        string $field,
        Validator $validator,
        array $rateOverrides = [],
        bool &$needsForeignFx = false,
    ): ?string {
        if ($amount < 0) {
            $validator->errors()->add($field, 'Amount cannot be negative.');

            return null;
        }

        if (empty($currencyId)) {
            return number_format($amount, 2, '.', '');
        }

        try {
            $currency = $service->requireActive((int) $currencyId, $field);
            $ledger = Currency::ledger();
            $code = strtoupper((string) $currency->code);

            if ($ledger && $currency->id === $ledger->id) {
                return Money::round((string) $amount, (int) ($ledger->decimal_places ?? 2));
            }

            try {
                // Central Exchange Board / catalog rates only — no product reference fallback.
                return $service->toLedger((string) $amount, $currency);
            } catch (\Illuminate\Validation\ValidationException $catalogError) {
                if (in_array($code, ['USD', 'AED'], true)) {
                    $needsForeignFx = true;
                }

                if ($rateOverrides === [] || empty($rateOverrides['usd_to_afn'])) {
                    throw $catalogError;
                }

                $board = $service->boardAt(null, $rateOverrides, allowCatalogFallback: false);
                $eq = $board->equivalents(Money::round((string) $amount, 4), $code, (int) ($ledger?->decimal_places ?? 2));

                return $eq['afn'];
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($field, $message);
                }
            }

            return null;
        }
    }
}
