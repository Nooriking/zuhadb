<?php

namespace App\Support;

use App\Models\Currency;
use App\Services\CurrencyService;
use Carbon\CarbonInterface;

class PurchaseValuation
{
    public function __construct(private readonly CurrencyService $currencies) {}

    /**
     * Snapshot original purchase money, FX board, and three-currency equivalents.
     *
     * Direction of rate_to_ledger: 1 unit of the purchase currency = X units of the ledger (AFN).
     *
     * @param  array{usd_to_afn?: string|float|null, aed_to_afn?: string|float|null, source?: string|null}  $rateOverrides
     * @return array<string, mixed>
     */
    public function snapshot(
        string $unitAmountOriginal,
        int $qty,
        ?Currency $purchaseCurrency,
        ?string $transactionRateToLedger = null,
        CarbonInterface|\DateTimeInterface|string|null $at = null,
        array $rateOverrides = [],
    ): array {
        $qty = max(1, $qty);
        $ledger = Currency::ledger();
        $unitOriginal = Money::round($unitAmountOriginal === '' ? '0' : $unitAmountOriginal, 2);
        $lineOriginal = Money::round(Money::mul($unitOriginal, (string) $qty, 8), 2);

        $code = strtoupper((string) (
            $purchaseCurrency?->code
            ?? ($ledger?->code)
            ?? config('business.default_currency', 'AFN')
        ));

        // Purchases freeze the exchange_rates board only — no silent catalog/1:1 fallback.
        $board = $this->currencies->boardAt($at, $rateOverrides, allowCatalogFallback: false);

        // Explicit purchase-currency→AFN override (legacy single-rate field) wins for rate_to_ledger
        // when the purchase currency is not AFN, but the board still drives the other two currencies.
        if ($transactionRateToLedger !== null && (float) $transactionRateToLedger > 0 && $code !== 'AFN') {
            if ($code === 'USD') {
                $board = FxBoard::fromArray([
                    'usd_to_afn' => (string) $transactionRateToLedger,
                    'aed_to_afn' => $board->aedToAfn,
                    'effective_at' => $board->effectiveAt,
                    'source' => $rateOverrides['source'] ?? 'transaction',
                    'kind' => 'transaction',
                ]);
            } elseif ($code === 'AED') {
                $board = FxBoard::fromArray([
                    'usd_to_afn' => $board->usdToAfn,
                    'aed_to_afn' => (string) $transactionRateToLedger,
                    'effective_at' => $board->effectiveAt,
                    'source' => $rateOverrides['source'] ?? 'transaction',
                    'kind' => 'transaction',
                ]);
            }
        }

        $unitEq = $board->equivalents($unitOriginal, $code, 4);
        $lineEq = $board->equivalents($lineOriginal, $code, 2);
        // Unit AFN at money scale 2 for stock costing.
        $unitAfn = Money::round($unitEq['afn'], 2);
        $lineAfn = Money::round(Money::mul($unitAfn, (string) $qty, 8), 2);
        $rate = $board->rateToAfn($code);

        $isLedger = $purchaseCurrency === null
            || ($ledger && $purchaseCurrency->id === $ledger->id)
            || $code === (string) config('business.default_currency', 'AFN');

        return [
            'purchase_unit_amount' => $unitOriginal,
            'purchase_line_amount' => $lineOriginal,
            'purchase_currency_id' => $purchaseCurrency?->id ?? $ledger?->id,
            'purchase_currency_code' => $purchaseCurrency?->code ?? $ledger?->code ?? 'AFN',
            'purchase_currency_symbol' => $purchaseCurrency?->symbol ?? $ledger?->symbol,
            'rate_to_ledger' => $rate,
            'rate_kind' => $isLedger ? 'ledger' : $board->kind,
            'unit_cost_afn' => $unitAfn,
            'line_cost_afn' => $lineAfn,
            'exchange_rate' => $rate,
            'unit_amount_usd' => $unitEq['usd'],
            'unit_amount_aed' => $unitEq['aed'],
            'line_amount_usd' => $lineEq['usd'],
            'line_amount_aed' => $lineEq['aed'],
            ...$board->toSnapshot(),
        ];
    }
}
