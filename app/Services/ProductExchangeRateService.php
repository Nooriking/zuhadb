<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductExchangeRate;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

class ProductExchangeRateService
{
    public const USD = 'USD';

    public const AFN = 'AFN';

    public const AED = 'AED';

    public function __construct(private readonly CurrencyService $currencies) {}

    /**
     * Catalog (system-wide) pair hints plus currency rows resolved by code.
     * Product-level rates are stored separately and never overwrite catalog
     * rates, purchase snapshots, or completed sales/payments.
     *
     * @return array<string, mixed>
     */
    public function formContext(): array
    {
        $usd = $this->byCode(self::USD);
        $afn = $this->byCode(self::AFN);
        $aed = $this->byCode(self::AED);

        return [
            'usd' => $usd ? Currency::toShared($usd) : null,
            'afn' => $afn ? Currency::toShared($afn) : null,
            'aed' => $aed ? Currency::toShared($aed) : null,
            'catalog_usd_to_afn' => $this->catalogUnitsPerUsd($usd, $afn),
            'catalog_usd_to_aed' => $this->catalogUnitsPerUsd($usd, $aed),
            'rate_definition' => 'Enter how many units of the target currency equal 1 US dollar. Example: 1 USD = 70 AFN is stored as 70. Product rates are independent of catalog currencies.rate_to_default and of historical purchase/sale snapshots.',
        ];
    }

    /**
     * @return array{usd_to_afn_rate: string|null, usd_to_aed_rate: string|null}
     */
    public function payloadFor(Product $product): array
    {
        return [
            'usd_to_afn_rate' => $this->rate($product, self::USD, self::AFN),
            'usd_to_aed_rate' => $this->rate($product, self::USD, self::AED),
        ];
    }

    public function rate(Product $product, string $fromCode, string $toCode): ?string
    {
        $from = $this->byCode($fromCode);
        $to = $this->byCode($toCode);
        if (! $from || ! $to) {
            return null;
        }

        $row = ProductExchangeRate::query()
            ->where('product_id', $product->id)
            ->where('from_currency_id', $from->id)
            ->where('to_currency_id', $to->id)
            ->first();

        if (! $row) {
            return null;
        }

        return $this->formatRate((string) $row->rate);
    }

    public function sync(Product $product, mixed $usdToAfn, mixed $usdToAed): void
    {
        $this->upsertPair($product, self::USD, self::AFN, $usdToAfn, 'usd_to_afn_rate');
        $this->upsertPair($product, self::USD, self::AED, $usdToAed, 'usd_to_aed_rate');
    }

    /**
     * Convert a USD amount using this product's stored pair rate.
     * Returns null when the product has no rate — callers must not invent one.
     */
    public function convertFromUsd(Product $product, string $usdAmount, string $toCode): ?string
    {
        $rate = $this->rate($product, self::USD, $toCode);
        if ($rate === null) {
            return null;
        }

        $currency = $this->byCode($toCode);
        $decimals = (int) ($currency?->decimal_places ?? 2);

        return Money::round(Money::mul($usdAmount, $rate, 12), $decimals);
    }

    public function requirePairCurrencies(string $field): array
    {
        $map = [
            'usd_to_afn_rate' => [self::USD, self::AFN],
            'usd_to_aed_rate' => [self::USD, self::AED],
        ];

        [$fromCode, $toCode] = $map[$field] ?? [null, null];
        $from = $fromCode ? $this->byCode($fromCode) : null;
        $to = $toCode ? $this->byCode($toCode) : null;

        if (! $from || ! $to) {
            throw ValidationException::withMessages([
                $field => 'USD, AFN, and AED must exist in the currencies catalog before a product rate can be saved. Do not create duplicate currency rows.',
            ]);
        }

        return [$from, $to];
    }

    private function upsertPair(Product $product, string $fromCode, string $toCode, mixed $rate, string $field): void
    {
        $normalized = $this->normalizeRate($rate);

        if ($normalized === null) {
            $from = $this->byCode($fromCode);
            $to = $this->byCode($toCode);
            if ($from && $to) {
                ProductExchangeRate::query()
                    ->where('product_id', $product->id)
                    ->where('from_currency_id', $from->id)
                    ->where('to_currency_id', $to->id)
                    ->delete();
            }

            return;
        }

        [$from, $to] = $this->requirePairCurrencies($field);

        ProductExchangeRate::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'from_currency_id' => $from->id,
                'to_currency_id' => $to->id,
            ],
            ['rate' => $normalized]
        );
    }

    private function normalizeRate(mixed $rate): ?string
    {
        if ($rate === null || $rate === '') {
            return null;
        }

        $formatted = $this->formatRate((string) $rate);
        if ((float) $formatted <= 0) {
            throw ValidationException::withMessages([
                'usd_to_afn_rate' => 'Exchange rates must be greater than zero.',
            ]);
        }

        return $formatted;
    }

    private function formatRate(string $rate): string
    {
        return rtrim(rtrim(Money::round($rate, 8), '0'), '.') ?: '0';
    }

    private function catalogUnitsPerUsd(?Currency $usd, ?Currency $target): ?string
    {
        if (! $usd || ! $target || ! $this->currencies->canConvert($usd, $target)) {
            return null;
        }

        try {
            return $this->formatRate($this->currencies->convert('1', $usd, $target, 8));
        } catch (\Throwable) {
            return null;
        }
    }

    private function byCode(string $code): ?Currency
    {
        return Currency::query()->notTrashed()->where('code', $code)->first();
    }
}
