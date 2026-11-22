<?php

namespace App\Support;

use App\Models\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Infer purchase_currency_id for legacy product_batches that predate the slim schema.
 *
 * Rules (no guessing beyond historical rate snapshot):
 * - exchange_rate null / blank / ≈1 → AFN
 * - exchange_rate closest to known USD→AFN board rate → USD
 * - exchange_rate closest to known AED→AFN board rate → AED
 * - when still ambiguous, leave null (report via return stats)
 */
class ProductBatchPurchaseCurrencyBackfill
{
    /**
     * @return array{updated: int, skipped: int, by_code: array<string, int>}
     */
    public static function run(): array
    {
        if (! Schema::hasTable('product_batches') || ! Schema::hasColumn('product_batches', 'purchase_currency_id')) {
            return ['updated' => 0, 'skipped' => 0, 'by_code' => []];
        }

        $currencies = Currency::query()
            ->whereIn('code', ['AFN', 'USD', 'AED'])
            ->get(['id', 'code'])
            ->keyBy(fn ($c) => strtoupper((string) $c->code));

        if ($currencies->isEmpty()) {
            return ['updated' => 0, 'skipped' => 0, 'by_code' => []];
        }

        $usdRef = self::referenceRate('USD');
        $aedRef = self::referenceRate('AED');

        $updated = 0;
        $skipped = 0;
        $byCode = ['AFN' => 0, 'USD' => 0, 'AED' => 0];

        $rows = DB::table('product_batches')
            ->whereNull('purchase_currency_id')
            ->select(['id', 'exchange_rate', 'unit_cost_currency', 'unit_cost_afn', 'purchase_amount_currency', 'quantity'])
            ->get();

        foreach ($rows as $row) {
            $code = self::inferCode($row, $usdRef, $aedRef);
            if ($code === null || ! $currencies->has($code)) {
                $skipped++;

                continue;
            }

            $currencyId = (int) $currencies[$code]->id;
            $payload = ['purchase_currency_id' => $currencyId];

            // Fill missing AFN ledger rate for AFN rows.
            if ($code === 'AFN' && (float) ($row->exchange_rate ?? 0) <= 0) {
                $payload['exchange_rate'] = 1;
            }

            // Fill missing purchase_amount_currency from unit × qty when possible.
            if ($row->purchase_amount_currency === null) {
                $qty = max(0, (int) ($row->quantity ?? 0));
                $unit = (float) ($row->unit_cost_currency ?? 0);
                if ($unit <= 0 && $code === 'AFN') {
                    $unit = (float) ($row->unit_cost_afn ?? 0);
                }
                if ($qty > 0 && $unit > 0) {
                    $payload['purchase_amount_currency'] = round($unit * $qty, 2);
                } elseif ($code === 'AFN' && (float) ($row->unit_cost_afn ?? 0) > 0 && $qty > 0) {
                    $payload['purchase_amount_currency'] = round((float) $row->unit_cost_afn * $qty, 2);
                }
            }

            if ($code === 'AFN' && ($row->unit_cost_currency === null || (float) $row->unit_cost_currency <= 0)
                && (float) ($row->unit_cost_afn ?? 0) > 0) {
                $payload['unit_cost_currency'] = $row->unit_cost_afn;
            }

            DB::table('product_batches')->where('id', $row->id)->update($payload);
            $updated++;
            $byCode[$code]++;
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'by_code' => $byCode,
        ];
    }

    private static function referenceRate(string $code): ?float
    {
        if (! Schema::hasTable('exchange_rates') || ! Schema::hasTable('currencies')) {
            return $code === 'USD' ? 56000.0 : ($code === 'AED' ? 15250.0 : 1.0);
        }

        $fromId = Currency::query()->where('code', $code)->value('id');
        $afnId = Currency::query()->where('code', 'AFN')->value('id');
        if (! $fromId || ! $afnId) {
            return $code === 'USD' ? 56000.0 : ($code === 'AED' ? 15250.0 : 1.0);
        }

        $rate = DB::table('exchange_rates')
            ->where('from_currency_id', $fromId)
            ->where('to_currency_id', $afnId)
            ->where(function ($q) {
                if (Schema::hasColumn('exchange_rates', 'trash')) {
                    $q->where('trash', false);
                }
            })
            ->orderByDesc('effective_at')
            ->value('rate');

        if ($rate !== null && (float) $rate > 0) {
            return (float) $rate;
        }

        return $code === 'USD' ? 56000.0 : ($code === 'AED' ? 15250.0 : 1.0);
    }

    private static function inferCode(object $row, ?float $usdRef, ?float $aedRef): ?string
    {
        $rate = $row->exchange_rate !== null ? (float) $row->exchange_rate : null;

        if ($rate === null || $rate <= 0 || abs($rate - 1.0) < 0.0001) {
            return 'AFN';
        }

        // Prefer derived rate from unit costs when both sides present.
        $unitOrig = (float) ($row->unit_cost_currency ?? 0);
        $unitAfn = (float) ($row->unit_cost_afn ?? 0);
        if ($unitOrig > 0 && $unitAfn > 0) {
            $derived = $unitAfn / $unitOrig;
            if (abs($derived - 1.0) < 0.05) {
                return 'AFN';
            }
            $rate = $derived;
        }

        $candidates = [];
        if ($usdRef && $usdRef > 0) {
            $candidates['USD'] = abs($rate - $usdRef) / $usdRef;
        }
        if ($aedRef && $aedRef > 0) {
            $candidates['AED'] = abs($rate - $aedRef) / $aedRef;
        }

        asort($candidates);
        $best = array_key_first($candidates);
        if ($best !== null && $candidates[$best] <= 0.15) {
            return $best;
        }

        // Absolute fallbacks for well-known historical bands.
        if ($rate > 1000) {
            // Closer to typical USD (~50k+) vs AED (~15k)
            $distUsd = abs($rate - ($usdRef ?: 56000));
            $distAed = abs($rate - ($aedRef ?: 15250));

            return $distUsd <= $distAed ? 'USD' : 'AED';
        }

        return null;
    }
}
