<?php

namespace App\Support;

use App\Models\Sale;
use App\Models\SaleReturn;

/**
 * Shared original-transaction currency helpers for Sales / Sales Returns.
 *
 * Amounts are never cross-added across AFN / USD / AED.
 * Unknown/empty sale currency is never silently remapped to another code.
 */
final class OriginalCurrencyMoney
{
    public const CODES = ['AFN', 'USD', 'AED'];

    public const UNKNOWN = 'UNKNOWN';

    /**
     * @return array{code: string|null, symbol: string|null, known: bool, label: string}
     */
    public static function resolveCode(?string $code, ?string $symbol = null): array
    {
        $normalized = strtoupper(trim((string) $code));

        if ($normalized === '' || $normalized === self::UNKNOWN) {
            return [
                'code' => null,
                'symbol' => $symbol,
                'known' => false,
                'label' => 'Unknown',
            ];
        }

        return [
            'code' => $normalized,
            'symbol' => $symbol,
            'known' => true,
            'label' => self::label($normalized),
        ];
    }

    public static function label(string $code): string
    {
        return match (strtoupper($code)) {
            'AFN' => 'AFN (Afghani)',
            'USD' => 'USD (Dollar)',
            'AED' => 'AED (Dirham)',
            self::UNKNOWN => 'Unknown',
            default => strtoupper($code),
        };
    }

    public static function shortLabel(string $code): string
    {
        return match (strtoupper($code)) {
            'AFN' => 'Afghani',
            'USD' => 'Dollar',
            'AED' => 'Dirham',
            self::UNKNOWN => 'Unknown',
            default => strtoupper($code),
        };
    }

    /**
     * Empty bucket map for the three supported currencies (+ optional unknown).
     *
     * @return array<string, array{code: string, label: string, short_label: string, symbol: string|null, total: float}>
     */
    public static function emptyBuckets(bool $includeUnknown = true): array
    {
        $buckets = [];

        foreach (self::CODES as $code) {
            $buckets[$code] = [
                'code' => $code,
                'label' => self::label($code),
                'short_label' => self::shortLabel($code),
                'symbol' => null,
                'total' => 0.0,
            ];
        }

        if ($includeUnknown) {
            $buckets[self::UNKNOWN] = [
                'code' => self::UNKNOWN,
                'label' => self::label(self::UNKNOWN),
                'short_label' => self::shortLabel(self::UNKNOWN),
                'symbol' => null,
                'total' => 0.0,
            ];
        }

        return $buckets;
    }

    /**
     * @param  array<string, array{code: string, label: string, short_label: string, symbol: string|null, total: float}>  $buckets
     * @return list<array{code: string, label: string, short_label: string, symbol: string|null, total: float}>
     */
    public static function presentBuckets(array $buckets, bool $keepZeroKnown = true): array
    {
        $ordered = [];

        foreach (self::CODES as $code) {
            if (! isset($buckets[$code])) {
                continue;
            }
            if ($keepZeroKnown || (float) $buckets[$code]['total'] != 0.0) {
                $ordered[] = $buckets[$code];
            }
        }

        if (isset($buckets[self::UNKNOWN]) && (float) $buckets[self::UNKNOWN]['total'] != 0.0) {
            $ordered[] = $buckets[self::UNKNOWN];
        }

        return $ordered;
    }

    /**
     * Display money for a sale row from the sale's own currency fields.
     *
     * @return array{
     *     code: string|null,
     *     symbol: string|null,
     *     known: bool,
     *     label: string,
     *     amount: float|null,
     *     ledger_afn: float
     * }
     */
    public static function forSale(Sale $sale, ?float $ledgerAfnOverride = null): array
    {
        $meta = self::resolveCode($sale->sale_currency_code, $sale->sale_currency_symbol);
        $ledger = $ledgerAfnOverride !== null
            ? round($ledgerAfnOverride, 2)
            : round((float) ($sale->total_afn ?? 0), 2);

        if (! $meta['known']) {
            return [
                ...$meta,
                'amount' => null,
                'ledger_afn' => $ledger,
            ];
        }

        $amount = $sale->sale_amount_currency;
        if ($amount !== null && $amount !== '') {
            return [
                ...$meta,
                'amount' => round((float) $amount, 2),
                'ledger_afn' => $ledger,
            ];
        }

        // Known code but missing original amount: derive from ledger via sale FX snapshot only.
        $converted = self::fromLedgerAfn($ledger, $meta['code'], $sale);

        return [
            ...$meta,
            'amount' => $converted,
            'ledger_afn' => $ledger,
        ];
    }

    /**
     * Convert a ledger AFN amount into the sale's original currency using that sale's rates only.
     * Returns null when conversion is not possible (never invents a rate).
     */
    public static function fromLedgerAfn(float $ledgerAfn, ?string $code, Sale $sale): ?float
    {
        $meta = self::resolveCode($code ?? $sale->sale_currency_code);

        if (! $meta['known'] || $meta['code'] === null) {
            return null;
        }

        $ledgerAfn = round($ledgerAfn, 2);

        if ($meta['code'] === 'AFN') {
            return $ledgerAfn;
        }

        $rate = match ($meta['code']) {
            'USD' => self::positiveRate($sale->usd_to_afn_rate ?? $sale->usd_to_ledger_rate),
            'AED' => self::positiveRate($sale->aed_to_afn_rate),
            default => null,
        };

        if ($rate === null) {
            return null;
        }

        return round((float) Money::div((string) $ledgerAfn, $rate, 12), 2);
    }

    /**
     * Sale-return money in the original sale currency.
     *
     * @return array{
     *     code: string|null,
     *     symbol: string|null,
     *     known: bool,
     *     label: string,
     *     amount: float|null,
     *     ledger_afn: float
     * }
     */
    public static function forSaleReturn(SaleReturn $saleReturn, ?Sale $sale = null): array
    {
        $sale ??= $saleReturn->relationLoaded('sale') ? $saleReturn->sale : $saleReturn->sale()->first();
        $ledger = round((float) ($saleReturn->total_afn ?? 0), 2);

        if (! $sale) {
            return [
                'code' => null,
                'symbol' => null,
                'known' => false,
                'label' => 'Unknown',
                'amount' => null,
                'ledger_afn' => $ledger,
            ];
        }

        $meta = self::resolveCode(
            $saleReturn->return_currency_code ?: $sale->sale_currency_code,
            $saleReturn->return_currency_symbol ?: $sale->sale_currency_symbol
        );

        if (! $meta['known']) {
            return [
                ...$meta,
                'amount' => null,
                'ledger_afn' => $ledger,
            ];
        }

        if ($saleReturn->return_amount_currency !== null && $saleReturn->return_amount_currency !== '') {
            return [
                ...$meta,
                'amount' => round((float) $saleReturn->return_amount_currency, 2),
                'ledger_afn' => $ledger,
            ];
        }

        return [
            ...$meta,
            'amount' => self::fromLedgerAfn($ledger, $meta['code'], $sale),
            'ledger_afn' => $ledger,
        ];
    }

    /**
     * Scale an original sale total by a ledger AFN ratio (e.g. partial due / paid).
     */
    public static function scaleOriginal(?float $originalTotal, float $ledgerPiece, float $ledgerTotal): ?float
    {
        if ($originalTotal === null) {
            return null;
        }

        if (abs($ledgerTotal) < 0.00001) {
            return round($originalTotal, 2);
        }

        return round($originalTotal * ($ledgerPiece / $ledgerTotal), 2);
    }

    /**
     * @param  iterable<int, array{code: ?string, amount: float|null, symbol?: ?string}>  $rows
     * @return array{
     *     buckets: array<string, array{code: string, label: string, short_label: string, symbol: string|null, total: float}>,
     *     list: list<array{code: string, label: string, short_label: string, symbol: string|null, total: float}>
     * }
     */
    public static function sumByCurrency(iterable $rows, bool $keepZeroKnown = true): array
    {
        $buckets = self::emptyBuckets(true);

        foreach ($rows as $row) {
            $meta = self::resolveCode($row['code'] ?? null, $row['symbol'] ?? null);
            $amount = $row['amount'] ?? null;

            if ($amount === null) {
                // Unknown original amount: keep ledger visibility only via UNKNOWN when provided as ledger fallback.
                if (array_key_exists('ledger_afn', $row) && $row['ledger_afn'] !== null) {
                    $key = self::UNKNOWN;
                    $buckets[$key]['total'] = round($buckets[$key]['total'] + (float) $row['ledger_afn'], 2);
                }

                continue;
            }

            $key = $meta['known'] && $meta['code'] ? $meta['code'] : self::UNKNOWN;
            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'code' => $key,
                    'label' => self::label($key),
                    'short_label' => self::shortLabel($key),
                    'symbol' => $meta['symbol'],
                    'total' => 0.0,
                ];
            }

            if ($meta['symbol'] && empty($buckets[$key]['symbol'])) {
                $buckets[$key]['symbol'] = $meta['symbol'];
            }

            $buckets[$key]['total'] = round($buckets[$key]['total'] + (float) $amount, 2);
        }

        return [
            'buckets' => $buckets,
            'list' => self::presentBuckets($buckets, $keepZeroKnown),
        ];
    }

    /**
     * Net = sales − returns per currency code (never cross-currency).
     *
     * @param  array<string, array{code: string, label: string, short_label: string, symbol: string|null, total: float}>  $sales
     * @param  array<string, array{code: string, label: string, short_label: string, symbol: string|null, total: float}>  $returns
     * @return list<array{code: string, label: string, short_label: string, symbol: string|null, total: float}>
     */
    public static function netBuckets(array $sales, array $returns, bool $keepZeroKnown = true): array
    {
        $net = self::emptyBuckets(true);

        foreach ($net as $code => $bucket) {
            $saleTotal = (float) ($sales[$code]['total'] ?? 0);
            $returnTotal = (float) ($returns[$code]['total'] ?? 0);
            $net[$code]['total'] = round($saleTotal - $returnTotal, 2);
            $net[$code]['symbol'] = $sales[$code]['symbol'] ?? $returns[$code]['symbol'] ?? null;
        }

        return self::presentBuckets($net, $keepZeroKnown);
    }

    private static function positiveRate(mixed $rate): ?string
    {
        if ($rate === null || $rate === '') {
            return null;
        }

        $value = (string) $rate;

        return (float) $value > 0 ? $value : null;
    }
}
