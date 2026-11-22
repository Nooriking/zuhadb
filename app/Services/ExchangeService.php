<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Support\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for FX rates: the exchange_rates table only.
 * Transaction callers should snapshot() at post time — never re-read live rates later.
 */
class ExchangeService
{
    /**
     * Units of $to equal to 1 unit of $from at (or before) $at.
     * Uses BCMath via Money; returns float only at the API boundary.
     */
    public function getRate(string $from, string $to, ?Carbon $at = null): float
    {
        $resolved = $this->resolvePair($from, $to, $at);

        return (float) $resolved['rate'];
    }

    /**
     * Convert $amount from one currency to another using the rate effective at $at.
     *
     * @throws \RuntimeException when no rate exists for the pair
     */
    public function convert(string $amount, string $from, string $to, ?Carbon $at = null): string
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === $to) {
            return $amount;
        }

        try {
            $rate = $this->getRate($from, $to, $at);
        } catch (ValidationException $e) {
            throw new \RuntimeException("Rate not found: {$from}→{$to}", 0, $e);
        }

        if ($rate <= 0) {
            throw new \RuntimeException("Rate not found: {$from}→{$to}");
        }

        return bcmul($amount, Money::round((string) $rate, 8), 8);
    }

    /**
     * Current board pairs derived solely from exchange_rates.
     * Throws when USD→AFN or AED→AFN is missing — never returns a silent 1:1.
     *
     * @return array{usd_to_afn: string, aed_to_afn: string, usd_to_aed: string, source: string, effective_at: string|null}
     */
    public function getCurrentRates(): array
    {
        $usdToAfn = $this->resolvePair('USD', 'AFN', null);
        $aedToAfn = $this->resolvePair('AED', 'AFN', null);
        $usdToAed = Money::round(
            Money::div($usdToAfn['rate'], $aedToAfn['rate'], 12),
            8
        );

        return [
            'usd_to_afn' => $usdToAfn['rate'],
            'aed_to_afn' => $aedToAfn['rate'],
            'usd_to_aed' => $usdToAed,
            'source' => (string) ($usdToAfn['source'] ?? 'exchange_rates'),
            'effective_at' => $usdToAfn['effective_at'] ?? $aedToAfn['effective_at'] ?? null,
        ];
    }

    /**
     * Snapshot for storing on a transaction row.
     *
     * @return array{rate: string, effective_at: string|null, source: string|null}
     */
    public function snapshot(string $from, string $to): array
    {
        $resolved = $this->resolvePair($from, $to, null);

        return [
            'rate' => $resolved['rate'],
            'effective_at' => $resolved['effective_at'],
            'source' => $resolved['source'],
        ];
    }

    /**
     * @return array{rate: string, effective_at: string|null, source: string|null, exchange_rate_id: int|null}
     */
    private function resolvePair(
        string $fromCode,
        string $toCode,
        CarbonInterface|\DateTimeInterface|string|null $at,
    ): array {
        $fromCode = strtoupper(trim($fromCode));
        $toCode = strtoupper(trim($toCode));

        if ($fromCode === $toCode) {
            return [
                'rate' => '1.00000000',
                'effective_at' => null,
                'source' => 'identity',
                'exchange_rate_id' => null,
            ];
        }

        if (! Schema::hasTable('exchange_rates')) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'Exchange rates table is not available.',
            ]);
        }

        $from = Currency::query()->notTrashed()->where('code', $fromCode)->first();
        $to = Currency::query()->notTrashed()->where('code', $toCode)->first();

        if (! $from || ! $to) {
            throw ValidationException::withMessages([
                'exchange_rate' => "Currency {$fromCode} or {$toCode} is missing from the catalog.",
            ]);
        }

        $atCarbon = $at ? Carbon::parse($at) : now();

        $row = ExchangeRate::query()
            ->notTrashed()
            ->where('from_currency_id', $from->id)
            ->where('to_currency_id', $to->id)
            ->where('effective_at', '<=', $atCarbon)
            ->where('rate', '>', 0)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        if ($row) {
            return [
                'rate' => Money::round((string) $row->rate, 8),
                'effective_at' => optional($row->effective_at)?->toDateTimeString(),
                'source' => $row->source ?: 'exchange_rates',
                'exchange_rate_id' => $row->id,
            ];
        }

        $inverse = ExchangeRate::query()
            ->notTrashed()
            ->where('from_currency_id', $to->id)
            ->where('to_currency_id', $from->id)
            ->where('effective_at', '<=', $atCarbon)
            ->where('rate', '>', 0)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        if ($inverse && (float) $inverse->rate > 0) {
            return [
                'rate' => Money::round(Money::div('1', (string) $inverse->rate, 12), 8),
                'effective_at' => optional($inverse->effective_at)?->toDateTimeString(),
                'source' => $inverse->source ?: 'operational_inverse',
                'exchange_rate_id' => $inverse->id,
            ];
        }

        throw ValidationException::withMessages([
            'exchange_rate' => "No exchange rate for {$fromCode} → {$toCode} at {$atCarbon->toDateTimeString()}. Post a rate on the Exchange Rates page.",
        ]);
    }
}
