<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Board-facing FX rates from the exchange_rates table.
 * Cached briefly for header / shared Inertia props; transactional code should
 * prefer ExchangeService::snapshot() at post time.
 */
class ExchangeRateService
{
    public function __construct(
        private readonly ExchangeService $exchange,
    ) {}

    /**
     * Latest board snapshot (nullable pairs when missing).
     *
     * @return array{
     *     usd_to_afn: array{rate: float, effective_at: string|null, source: string|null}|null,
     *     aed_to_afn: array{rate: float, effective_at: string|null, source: string|null}|null,
     *     usd_to_aed: array{rate: float, effective_at: string|null, source: string|null}|null,
     *     updated_at: string
     * }
     */
    public function board(): array
    {
        return Cache::remember('exchange_rate_board', now()->addMinutes(5), function () {
            $afn = Currency::query()->notTrashed()->where('code', 'AFN')->first();
            $usd = Currency::query()->notTrashed()->where('code', 'USD')->first();
            $aed = Currency::query()->notTrashed()->where('code', 'AED')->first();

            if (! $afn || ! $usd || ! $aed) {
                return $this->emptyBoard();
            }

            $usdToAfn = $this->latest((int) $usd->id, (int) $afn->id);
            $aedToAfn = $this->latest((int) $aed->id, (int) $afn->id);
            $usdToAed = $this->latest((int) $usd->id, (int) $aed->id);

            $usdToAedPayload = null;
            if ($usdToAed) {
                $usdToAedPayload = [
                    'rate' => (float) $usdToAed->rate,
                    'effective_at' => $usdToAed->effective_at?->toIso8601String(),
                    'source' => $usdToAed->source ?: 'manual',
                ];
            } elseif ($usdToAfn && $aedToAfn && (float) $aedToAfn->rate > 0) {
                $usdToAedPayload = [
                    'rate' => (float) $usdToAfn->rate / (float) $aedToAfn->rate,
                    'effective_at' => $usdToAfn->effective_at?->toIso8601String(),
                    'source' => 'derived',
                ];
            }

            return [
                'usd_to_afn' => $usdToAfn ? [
                    'rate' => (float) $usdToAfn->rate,
                    'effective_at' => $usdToAfn->effective_at?->toIso8601String(),
                    'source' => $usdToAfn->source,
                ] : null,
                'aed_to_afn' => $aedToAfn ? [
                    'rate' => (float) $aedToAfn->rate,
                    'effective_at' => $aedToAfn->effective_at?->toIso8601String(),
                    'source' => $aedToAfn->source,
                ] : null,
                'usd_to_aed' => $usdToAedPayload,
                'updated_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Flat board shape for shared Inertia props / useExchange().
     *
     * @return array{
     *     usd_to_afn: float|null,
     *     aed_to_afn: float|null,
     *     usd_to_aed: float|null,
     *     effective_at: string|null,
     *     source: string|null,
     *     updated_at: string|null
     * }
     */
    public function flatBoard(): array
    {
        $board = $this->board();

        return [
            'usd_to_afn' => $board['usd_to_afn']['rate'] ?? null,
            'aed_to_afn' => $board['aed_to_afn']['rate'] ?? null,
            'usd_to_aed' => $board['usd_to_aed']['rate'] ?? null,
            'effective_at' => $board['usd_to_afn']['effective_at']
                ?? $board['aed_to_afn']['effective_at']
                ?? null,
            'source' => $board['usd_to_afn']['source']
                ?? $board['aed_to_afn']['source']
                ?? null,
            'updated_at' => $board['updated_at'] ?? null,
        ];
    }

    private function latest(int $fromId, int $toId): ?ExchangeRate
    {
        return ExchangeRate::query()
            ->notTrashed()
            ->where('from_currency_id', $fromId)
            ->where('to_currency_id', $toId)
            ->where('effective_at', '<=', now())
            ->where('rate', '>', 0)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{
     *     usd_to_afn: null,
     *     aed_to_afn: null,
     *     usd_to_aed: null,
     *     updated_at: string
     * }
     */
    private function emptyBoard(): array
    {
        return [
            'usd_to_afn' => null,
            'aed_to_afn' => null,
            'usd_to_aed' => null,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    public function forget(): void
    {
        Cache::forget('exchange_rate_board');
    }

    public function getLatestRate(string $from, string $to = 'AFN'): float
    {
        return $this->exchange->getRate($from, $to);
    }

    /**
     * Convert an AFN ledger amount to AFN/USD/AED using today's board rates.
     *
     * @return array{afn: float, usd: float|null, aed: float|null}
     */
    public function threeFromAfn(float $afn): array
    {
        $board = $this->flatBoard();
        $usdRate = $board['usd_to_afn'];
        $aedRate = $board['aed_to_afn'];

        return [
            'afn' => round($afn, 2),
            'usd' => ($usdRate !== null && $usdRate > 0) ? round($afn / $usdRate, 2) : null,
            'aed' => ($aedRate !== null && $aedRate > 0) ? round($afn / $aedRate, 2) : null,
        ];
    }

    /**
     * Weighted-average investment FX per original currency:
     * SUM(amount_afn) / SUM(amount_currency)
     *
     * Compared against today's board rate so the UI can show FX gain/loss vs cost basis.
     *
     * @return list<array{
     *     currency_code: string,
     *     currency_id: int|null,
     *     total_currency: float,
     *     total_afn: float,
     *     investments_count: int,
     *     weighted_avg_rate: float|null,
     *     board_rate: float|null,
     *     rate_delta: float|null,
     *     rate_delta_pct: float|null
     * }>
     */
    public function investmentWeightedAverages(?int $investorId = null): array
    {
        $board = $this->flatBoard();

        $rows = DB::table('investments')
            ->leftJoin('currencies', 'currencies.id', '=', 'investments.currency_id')
            ->where('investments.trash', false)
            ->whereNull('investments.deleted_at')
            ->where('investments.amount_currency', '>', 0)
            ->when($investorId, fn ($q) => $q->where('investments.investor_id', $investorId))
            ->groupBy('investments.currency_id', 'currencies.code')
            ->orderBy('currencies.code')
            ->selectRaw("
                investments.currency_id,
                COALESCE(currencies.code, 'AFN') as currency_code,
                COUNT(*) as investments_count,
                SUM(investments.amount_currency) as total_currency,
                SUM(investments.amount_afn) as total_afn,
                SUM(investments.amount_afn) / SUM(investments.amount_currency) as weighted_avg_rate
            ")
            ->get();

        return $rows->map(function ($row) use ($board) {
            $code = strtoupper((string) $row->currency_code);
            $avg = $row->weighted_avg_rate !== null ? (float) $row->weighted_avg_rate : null;
            $boardRate = match ($code) {
                'AFN' => 1.0,
                'USD' => $board['usd_to_afn'],
                'AED' => $board['aed_to_afn'],
                default => null,
            };
            if ($boardRate !== null) {
                $boardRate = (float) $boardRate;
            }

            $delta = ($avg !== null && $boardRate !== null) ? round($boardRate - $avg, 8) : null;
            $deltaPct = ($avg !== null && $avg > 0 && $delta !== null)
                ? round(($delta / $avg) * 100, 4)
                : null;

            return [
                'currency_code' => $code,
                'currency_id' => $row->currency_id !== null ? (int) $row->currency_id : null,
                'total_currency' => round((float) $row->total_currency, 2),
                'total_afn' => round((float) $row->total_afn, 2),
                'investments_count' => (int) $row->investments_count,
                'weighted_avg_rate' => $avg !== null ? round($avg, 8) : null,
                'board_rate' => $boardRate !== null ? round($boardRate, 8) : null,
                'rate_delta' => $delta,
                'rate_delta_pct' => $deltaPct,
            ];
        })->values()->all();
    }
}
