<?php

namespace App\Services;

use App\Models\Currency;
use App\Support\FxBoard;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative catalog (reference) price conversion into AFN / USD / AED.
 *
 * Uses the same FxBoard architecture as purchases/sales, but results are
 * catalog estimates only — never historical transaction snapshots.
 *
 * Purchase, sale, and wholesale may each use an independent currency;
 * one shared board snapshot is applied for all fields in a set.
 */
class CatalogPriceConverter
{
    public const SUPPORTED = ['AFN', 'USD', 'AED'];

    public function __construct(private readonly CurrencyService $currencies) {}

    /**
     * Convert one catalog amount into AFN/USD/AED using a single FX board.
     *
     * @param  array{usd_to_afn?: string|float|null, usd_to_aed?: string|float|null, aed_to_afn?: string|float|null, source?: string|null}  $rateOverrides
     * @return array{
     *     original_amount: string,
     *     original_currency_code: string,
     *     original_currency_id: int|null,
     *     afn: string,
     *     usd: string,
     *     aed: string,
     *     board: FxBoard
     * }
     */
    public function convert(
        string $amount,
        Currency|string|null $currency,
        string $field = 'catalog_currency_id',
        array $rateOverrides = [],
        int $decimals = 2,
    ): array {
        $currencyModel = $this->resolveCurrency($currency, $field);
        $code = strtoupper((string) $currencyModel->code);

        if (! in_array($code, self::SUPPORTED, true)) {
            throw ValidationException::withMessages([
                $field => 'Catalog pricing currency must be AFN, USD, or AED.',
            ]);
        }

        $normalized = Money::round($amount === '' ? '0' : $amount, 8);
        if ((float) $normalized < 0) {
            throw ValidationException::withMessages([
                $field => 'Catalog price amounts cannot be negative.',
            ]);
        }

        $board = $this->board($rateOverrides);
        $eq = $board->equivalents($normalized, $code, $decimals);

        return [
            'original_amount' => Money::round($normalized, $decimals),
            'original_currency_code' => $code,
            'original_currency_id' => $currencyModel->id,
            'afn' => $eq['afn'],
            'usd' => $eq['usd'],
            'aed' => $eq['aed'],
            'board' => $board,
        ];
    }

    /**
     * Convert purchase / sale / wholesale with independent currencies and one shared board.
     *
     * @param  array{
     *     purchase?: array{amount?: string|float|null, currency?: Currency|string|int|null},
     *     sale?: array{amount?: string|float|null, currency?: Currency|string|int|null},
     *     wholesale?: array{amount?: string|float|null, currency?: Currency|string|int|null}
     * }  $fields
     * @param  array{usd_to_afn?: string|float|null, usd_to_aed?: string|float|null, aed_to_afn?: string|float|null, source?: string|null}  $rateOverrides
     * @return array{
     *     purchase: array{amount: string, currency_id: int, currency_code: string, afn: string, usd: string, aed: string},
     *     sale: array{amount: string, currency_id: int, currency_code: string, afn: string, usd: string, aed: string},
     *     wholesale: array{amount: string, currency_id: int, currency_code: string, afn: string, usd: string, aed: string},
     *     board: FxBoard,
     *     snapshot: array<string, mixed>
     * }
     */
    public function convertIndependentSet(
        array $fields,
        array $rateOverrides = [],
        int $decimals = 2,
        string $fallbackField = 'catalog_currency_id',
    ): array {
        $board = $this->board($rateOverrides);
        $result = [
            'board' => $board,
            // Neutral FX snapshot only — never variant/product column names.
            'snapshot' => [
                'usd_to_afn' => $board->usdToAfn,
                'aed_to_afn' => $board->aedToAfn,
                'usd_to_aed' => $board->usdToAed,
                'effective_at' => $board->effectiveAt,
                'converted_at' => now()->toDateTimeString(),
                'source' => $board->source,
            ],
            'fx' => [
                'usd_to_afn' => $board->usdToAfn,
                'aed_to_afn' => $board->aedToAfn,
                'usd_to_aed' => $board->usdToAed,
                'effective_at' => $board->effectiveAt,
                'converted_at' => now()->toDateTimeString(),
                'source' => $board->source,
            ],
        ];

        foreach (['purchase', 'sale', 'wholesale'] as $type) {
            $entry = is_array($fields[$type] ?? null) ? $fields[$type] : [];
            $fieldKey = "{$fallbackField}.{$type}";
            $currencyModel = $this->resolveCurrency(
                $entry['currency'] ?? null,
                $type === 'sale' ? $fallbackField : "{$type}_currency_id",
            );
            $code = strtoupper((string) $currencyModel->code);

            if (! in_array($code, self::SUPPORTED, true)) {
                throw ValidationException::withMessages([
                    $fieldKey => 'Catalog pricing currency must be AFN, USD, or AED.',
                ]);
            }

            $raw = $entry['amount'] ?? '0';
            $normalized = Money::round(($raw === '' || $raw === null) ? '0' : (string) $raw, 8);
            if ((float) $normalized < 0) {
                throw ValidationException::withMessages([
                    $fieldKey => 'Catalog price amounts cannot be negative.',
                ]);
            }

            $eq = $board->equivalents($normalized, $code, $decimals);
            $result[$type] = [
                'amount' => Money::round($normalized, $decimals),
                'currency_id' => $currencyModel->id,
                'currency_code' => $code,
                'afn' => $eq['afn'],
                'usd' => $eq['usd'],
                'aed' => $eq['aed'],
            ];
        }

        return $result;
    }

    /**
     * Convert purchase / sale / wholesale with one shared currency and board snapshot.
     * Prefer convertIndependentSet when fields may use different currencies.
     *
     * @param  array{
     *     purchase?: string|float|null,
     *     sale?: string|float|null,
     *     wholesale?: string|float|null
     * }  $amounts
     * @param  array{usd_to_afn?: string|float|null, usd_to_aed?: string|float|null, aed_to_afn?: string|float|null, source?: string|null}  $rateOverrides
     * @return array{
     *     currency_id: int,
     *     currency_code: string,
     *     purchase: array{amount: string, afn: string, usd: string, aed: string},
     *     sale: array{amount: string, afn: string, usd: string, aed: string},
     *     wholesale: array{amount: string, afn: string, usd: string, aed: string},
     *     board: FxBoard,
     *     snapshot: array<string, mixed>
     * }
     */
    public function convertCatalogSet(
        array $amounts,
        Currency|string|int|null $currency,
        string $field = 'catalog_currency_id',
        array $rateOverrides = [],
        int $decimals = 2,
    ): array {
        $currencyModel = $this->resolveCurrency($currency, $field);
        $independent = $this->convertIndependentSet(
            [
                'purchase' => ['amount' => $amounts['purchase'] ?? '0', 'currency' => $currencyModel],
                'sale' => ['amount' => $amounts['sale'] ?? '0', 'currency' => $currencyModel],
                'wholesale' => ['amount' => $amounts['wholesale'] ?? '0', 'currency' => $currencyModel],
            ],
            $rateOverrides,
            $decimals,
            $field,
        );

        return [
            'currency_id' => $currencyModel->id,
            'currency_code' => strtoupper((string) $currencyModel->code),
            'purchase' => [
                'amount' => $independent['purchase']['amount'],
                'afn' => $independent['purchase']['afn'],
                'usd' => $independent['purchase']['usd'],
                'aed' => $independent['purchase']['aed'],
            ],
            'sale' => [
                'amount' => $independent['sale']['amount'],
                'afn' => $independent['sale']['afn'],
                'usd' => $independent['sale']['usd'],
                'aed' => $independent['sale']['aed'],
            ],
            'wholesale' => [
                'amount' => $independent['wholesale']['amount'],
                'afn' => $independent['wholesale']['afn'],
                'usd' => $independent['wholesale']['usd'],
                'aed' => $independent['wholesale']['aed'],
            ],
            'board' => $independent['board'],
            'snapshot' => $independent['snapshot'],
        ];
    }

    /**
     * @param  array{usd_to_afn?: string|float|null, usd_to_aed?: string|float|null, aed_to_afn?: string|float|null, source?: string|null}  $rateOverrides
     */
    public function board(array $rateOverrides = []): FxBoard
    {
        // Catalog estimates use the same exchange_rates board; never invent a silent 1:1.
        return $this->currencies->boardAt(null, $rateOverrides, allowCatalogFallback: false);
    }

    private function resolveCurrency(Currency|string|int|null $currency, string $field): Currency
    {
        if ($currency instanceof Currency) {
            return $currency;
        }

        if (is_int($currency) || (is_string($currency) && ctype_digit($currency))) {
            return $this->currencies->requireActive((int) $currency, $field);
        }

        if (is_string($currency) && $currency !== '') {
            $code = strtoupper(trim($currency));
            $row = Currency::query()->notTrashed()->where('code', $code)->where('status', 'active')->first();
            if ($row) {
                return $row;
            }
        }

        $ledger = Currency::ledger();
        if ($ledger) {
            return $ledger;
        }

        throw ValidationException::withMessages([
            $field => 'A catalog pricing currency is required.',
        ]);
    }
}
