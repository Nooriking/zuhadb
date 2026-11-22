<?php

namespace App\Support;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;

class CurrencyConverter
{
    public static function rateToAfn(string $code): float
    {
        $code = strtoupper($code);
        if ($code === 'AFN') {
            return 1.0;
        }

        return Cache::remember("rate_to_afn_{$code}_" . now()->toDateString(), 21600, function () use ($code) {
            $from = Currency::where('code', $code)->first();
            $afn = Currency::where('code', 'AFN')->first();
            if ($from && $afn) {
                $rate = ExchangeRate::where('from_currency_id', $from->id)
                    ->where('to_currency_id', $afn->id)->where('trash', 0)
                    ->orderByDesc('effective_at')->value('rate');
                if ($rate) {
                    return (float) $rate;
                }
                if ($from->rate_to_default > 0) {
                    return (float) $from->rate_to_default;
                }
            }

            return 1.0;
        });
    }

    public static function toAfn(float $amount, string $code): float
    {
        return round($amount * self::rateToAfn($code), 2);
    }

    public static function fromAfn(float $amountAfn, string $code): float
    {
        $rate = self::rateToAfn($code);

        return $rate > 0 ? round($amountAfn / $rate, 4) : 0.0;
    }

    /**
     * @return array{afn: float, usd: float, aed: float}
     */
    public static function threeWays(float $amountAfn): array
    {
        return [
            'afn' => round($amountAfn, 2),
            'usd' => self::fromAfn($amountAfn, 'USD'),
            'aed' => self::fromAfn($amountAfn, 'AED'),
        ];
    }

    /**
     * @return array{usd_to_afn: float, aed_to_afn: float, usd_to_aed: float}
     */
    public static function currentRates(): array
    {
        $usd = self::rateToAfn('USD');
        $aed = self::rateToAfn('AED');

        return [
            'usd_to_afn' => $usd,
            'aed_to_afn' => $aed,
            'usd_to_aed' => $aed > 0 ? round($usd / $aed, 8) : 0,
        ];
    }
}
