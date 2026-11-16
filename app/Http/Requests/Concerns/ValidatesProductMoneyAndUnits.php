<?php

namespace App\Http\Requests\Concerns;

use App\Models\Currency;
use App\Services\CurrencyService;
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
        $ledger = Currency::ledger();

        foreach ($this->input('variants', []) as $index => $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $purchaseEntered = (float) ($variant['purchase_amount'] ?? $variant['purchase_price_afn'] ?? 0);
            $saleEntered = (float) ($variant['sale_amount'] ?? $variant['sale_price_afn'] ?? 0);

            $purchaseAfn = $this->toLedgerOrError($service, $purchaseEntered, $variant['purchase_currency_id'] ?? null, "variants.{$index}.purchase_currency_id", $validator);
            $saleAfn = $this->toLedgerOrError($service, $saleEntered, $variant['sale_currency_id'] ?? null, "variants.{$index}.sale_currency_id", $validator);

            if ($purchaseAfn !== null && $saleAfn !== null && $saleAfn + 0 < $purchaseAfn + 0) {
                $validator->errors()->add("variants.{$index}.sale_price_afn", 'Sale price cannot be less than the purchase price after converting both to the ledger currency.');
            }

            if (! empty($variant['wholesale_currency_id']) || ($variant['wholesale_amount'] ?? '') !== '') {
                $this->toLedgerOrError(
                    $service,
                    (float) ($variant['wholesale_amount'] ?? $variant['wholesale_price_afn'] ?? 0),
                    $variant['wholesale_currency_id'] ?? null,
                    "variants.{$index}.wholesale_currency_id",
                    $validator
                );
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
                    $validator
                );
            }
        }
    }

    private function toLedgerOrError(CurrencyService $service, float $amount, mixed $currencyId, string $field, Validator $validator): ?string
    {
        if ($amount < 0) {
            $validator->errors()->add($field, 'Amount cannot be negative.');

            return null;
        }

        if (empty($currencyId)) {
            return number_format($amount, 2, '.', '');
        }

        try {
            $currency = $service->requireActive((int) $currencyId, $field);

            return $service->toLedger((string) $amount, $currency);
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
