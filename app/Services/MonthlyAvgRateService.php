<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MonthlyAvgRateService
{
    /**
     * Average rates for a given month (USD→AFN, AED→AFN, USD→AED)
     */
    public function forMonth(string $yearMonth): array
    {
        $start = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $usdId = Currency::where('code', 'USD')->value('id');
        $aedId = Currency::where('code', 'AED')->value('id');
        $afnId = Currency::where('code', 'AFN')->value('id');

        $rates = ExchangeRate::query()
            ->where('trash', 0)
            ->whereBetween('effective_at', [$start, $end])
            ->get();

        $usdSamples = $rates->filter(fn ($r) =>
            (int) $r->from_currency_id === (int) $usdId && (int) $r->to_currency_id === (int) $afnId
        );
        $aedSamples = $rates->filter(fn ($r) =>
            (int) $r->from_currency_id === (int) $aedId && (int) $r->to_currency_id === (int) $afnId
        );
        $usdAedSamples = $rates->filter(fn ($r) =>
            (int) $r->from_currency_id === (int) $usdId && (int) $r->to_currency_id === (int) $aedId
        );

        $usdToAfn = (float) ($usdSamples->avg('rate') ?? 0);
        $aedToAfn = (float) ($aedSamples->avg('rate') ?? 0);
        $usdToAed = (float) ($usdAedSamples->avg('rate') ?? 0);

        // fallback: if no direct USD→AED, derive from USD→AFN / AED→AFN
        if ($usdToAed <= 0 && $usdToAfn > 0 && $aedToAfn > 0) {
            $usdToAed = $usdToAfn / $aedToAfn;
        }

        $usdToAfnAvg = round($usdToAfn, 4);
        $aedToAfnAvg = round($aedToAfn, 4);
        $usdToAedAvg = round($usdToAed, 4);

        return [
            'month' => $yearMonth,
            'usd_to_afn_avg' => $usdToAfnAvg,
            'aed_to_afn_avg' => $aedToAfnAvg,
            'usd_to_aed_avg' => $usdToAedAvg,
            // aliases for callers that still expect short keys
            'usd_to_afn' => $usdToAfnAvg,
            'aed_to_afn' => $aedToAfnAvg,
            'usd_to_aed' => $usdToAedAvg,
            'sample_count' => $rates->count(),
            'usd_samples' => $usdSamples->count(),
            'aed_samples' => $aedSamples->count(),
        ];
    }

    /**
     * All months present in exchange_rates (for filter dropdown)
     *
     * @return list<string>
     */
    public function availableMonths(): array
    {
        $driver = DB::connection()->getDriverName();

        $monthExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m', effective_at)",
            default => "DATE_FORMAT(effective_at, '%Y-%m')",
        };

        return ExchangeRate::query()
            ->where('trash', 0)
            ->selectRaw("{$monthExpr} as month")
            ->groupBy('month')
            ->orderByDesc('month')
            ->pluck('month')
            ->filter()
            ->values()
            ->toArray();
    }
}
