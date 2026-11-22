<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ProductPrice;
use App\Support\FxBoard;
use App\Support\Money;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PriceResolver
{
    /** @var array<string, float> */
    private static array $rateCache = [];

    public function __construct(
        private readonly CurrencyService $currency,
        private readonly ExchangeService $exchange,
    ) {}

    /**
     * Resolve the unit sale price for a variant in the order currency.
     *
     * Priority:
     * 1. Direct active product_prices row for the order currency
     * 2. AFN product_prices converted via Central Exchange Board (exchange_rates only)
     *
     * @return array{amount: float, source: 'direct'|'converted_from_afn', currency: string}
     */
    public function resolveUnitPrice(int $variantId, string $orderCurrencyCode, int $qty = 1): array
    {
        $orderCurrencyCode = strtoupper(trim($orderCurrencyCode));
        $today = now()->toDateString();

        $direct = $this->findActivePrice($variantId, 'sale', $orderCurrencyCode, $qty, $today);

        if ($direct) {
            return [
                'amount' => round((float) $direct->amount, 4),
                'source' => 'direct',
                'currency' => $orderCurrencyCode,
            ];
        }

        $afnPrice = $this->findActivePrice($variantId, 'sale', 'AFN', $qty, $today);
        $amountAfn = (float) ($afnPrice?->amount ?? 0);

        if ($amountAfn <= 0) {
            throw ValidationException::withMessages([
                'sale_unit_amount' => "No sale price found for variant #{$variantId}.",
            ]);
        }

        if ($orderCurrencyCode === 'AFN') {
            return [
                'amount' => round($amountAfn, 4),
                'source' => 'direct',
                'currency' => 'AFN',
            ];
        }

        $board = $this->saleFxBoard();
        $eq = $board->equivalents((string) $amountAfn, 'AFN', 4);
        $converted = match ($orderCurrencyCode) {
            'USD' => (float) $eq['usd'],
            'AED' => (float) $eq['aed'],
            default => throw ValidationException::withMessages([
                'sale_unit_amount' => "Unsupported sale currency {$orderCurrencyCode}.",
            ]),
        };

        return [
            'amount' => round($converted, 4),
            'source' => 'converted_from_afn',
            'currency' => $orderCurrencyCode,
        ];
    }

    /**
     * Return how many units of $to equal 1 unit of $from.
     * Example: getTodayRate('USD', 'AFN') ≈ 56000.
     *
     * Uses the Central Exchange Board (exchange_rates) only — never silent 1:1 / catalog invent.
     */
    public function getTodayRate(string $from, string $to): float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === $to) {
            return 1.0;
        }

        $cacheKey = "{$from}->{$to}";
        if (array_key_exists($cacheKey, self::$rateCache)) {
            return self::$rateCache[$cacheKey];
        }

        if (in_array($from, ['AFN', 'USD', 'AED'], true) && in_array($to, ['AFN', 'USD', 'AED'], true)) {
            $board = $this->saleFxBoard();
            $fromToAfn = (float) $board->rateToAfn($from);
            $toToAfn = (float) $board->rateToAfn($to);

            if ($fromToAfn <= 0 || $toToAfn <= 0) {
                throw ValidationException::withMessages([
                    'exchange_rate' => 'Central exchange rate board is unavailable. Please add USD/AED rates in Exchange Rates first.',
                ]);
            }

            return self::$rateCache[$cacheKey] = $fromToAfn / $toToAfn;
        }

        // Legacy non-board currencies: operational exchange_rates row only.
        $fromCur = Currency::query()->notTrashed()->where('code', $from)->first();
        $toCur = Currency::query()->notTrashed()->where('code', $to)->first();

        if (! $fromCur || ! $toCur) {
            throw ValidationException::withMessages([
                'exchange_rate' => "Currency {$from} or {$to} is missing from the catalog.",
            ]);
        }

        if (! Schema::hasTable('exchange_rates')) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'Central exchange rate board is unavailable. Please add USD/AED rates in Exchange Rates first.',
            ]);
        }

        $at = now();
        $direct = ExchangeRate::query()
            ->notTrashed()
            ->where('from_currency_id', $fromCur->id)
            ->where('to_currency_id', $toCur->id)
            ->where('rate', '>', 0)
            ->where('effective_at', '<=', $at)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        if ($direct) {
            return self::$rateCache[$cacheKey] = (float) $direct->rate;
        }

        $inverse = ExchangeRate::query()
            ->notTrashed()
            ->where('from_currency_id', $toCur->id)
            ->where('to_currency_id', $fromCur->id)
            ->where('rate', '>', 0)
            ->where('effective_at', '<=', $at)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        if ($inverse && (float) $inverse->rate > 0) {
            return self::$rateCache[$cacheKey] = 1 / (float) $inverse->rate;
        }

        throw ValidationException::withMessages([
            'exchange_rate' => "No exchange rate for {$from} → {$to}. Post a rate on the Exchange Rates page.",
        ]);
    }

    /**
     * @param  array{variant_id:int, quantity:int, order_currency_code:string}  $data
     * @return array{
     *     sale_unit_amount: float,
     *     sale_line_amount: float,
     *     sale_price_afn: float,
     *     line_total_afn: float,
     *     source: string,
     *     currency: string
     * }
     */
    public function resolveSaleLine(array $data, ?FxBoard $board = null): array
    {
        $variantId = (int) ($data['variant_id'] ?? 0);
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $orderCurrencyCode = strtoupper((string) ($data['order_currency_code'] ?? 'AFN'));

        $resolved = $this->resolveUnitPrice($variantId, $orderCurrencyCode, $quantity);
        $board = $board ?? $this->saleFxBoard();

        $saleUnitAmount = Money::round((string) $resolved['amount'], 4);
        $saleLineAmount = Money::round(Money::mul($saleUnitAmount, (string) $quantity, 8), 2);
        $salePriceAfn = Money::round($board->equivalents($saleUnitAmount, $orderCurrencyCode, 4)['afn'], 2);
        $lineTotalAfn = Money::round(Money::mul($salePriceAfn, (string) $quantity, 8), 2);

        return [
            'sale_unit_amount' => (float) $saleUnitAmount,
            'sale_line_amount' => (float) $saleLineAmount,
            'sale_price_afn' => (float) $salePriceAfn,
            'line_total_afn' => (float) $lineTotalAfn,
            'source' => $resolved['source'],
            'currency' => $resolved['currency'],
        ];
    }

    public static function clearRateCache(): void
    {
        self::$rateCache = [];
    }

    /**
     * Sale FX board from exchange_rates only — never catalog rate_to_default.
     */
    private function saleFxBoard(): FxBoard
    {
        try {
            $current = $this->exchange->getCurrentRates();
        } catch (ValidationException) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'Central exchange rate board is unavailable. Please add USD/AED rates in Exchange Rates first.',
            ]);
        }

        $usdToAfn = (float) ($current['usd_to_afn'] ?? 0);
        $aedToAfn = (float) ($current['aed_to_afn'] ?? 0);

        if ($usdToAfn <= 0 || $aedToAfn <= 0) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'Central exchange rate board is unavailable. Please add USD/AED rates in Exchange Rates first.',
            ]);
        }

        return FxBoard::fromArray([
            'usd_to_afn' => (string) $current['usd_to_afn'],
            'aed_to_afn' => (string) $current['aed_to_afn'],
            'usd_to_aed' => (string) ($current['usd_to_aed'] ?? ''),
            'effective_at' => $current['effective_at'] ?? now()->toDateTimeString(),
            'source' => (string) ($current['source'] ?? 'exchange_rates'),
            'kind' => 'operational',
        ]);
    }

    private function findActivePrice(
        int $variantId,
        string $priceType,
        string $currencyCode,
        int $qty,
        string $today,
    ): ?ProductPrice {
        if (! Schema::hasTable('product_prices')) {
            return null;
        }

        return ProductPrice::query()
            ->where('variant_id', $variantId)
            ->where('price_type', $priceType)
            ->where('currency_code', strtoupper($currencyCode))
            ->where('is_active', true)
            ->where('min_qty', '<=', $qty)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today);
            })
            ->orderByDesc('min_qty')
            ->first();
    }
}
