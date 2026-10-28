<?php

namespace App\Support;

use App\Models\Currency;
use App\Services\CurrencyService;

class PurchaseValuation
{
    public function __construct(private readonly CurrencyService $currencies) {}

    /**
     * Snapshot original purchase money and the ledger (AFN) cost used for stock.
     *
     * Direction of rate_to_ledger: 1 unit of the purchase currency = X units of the ledger (AFN).
     *
     * @return array{
     *     purchase_unit_amount: string,
     *     purchase_line_amount: string,
     *     purchase_currency_id: int|null,
     *     purchase_currency_code: string|null,
     *     purchase_currency_symbol: string|null,
     *     rate_to_ledger: string,
     *     rate_kind: string,
     *     unit_cost_afn: string,
     *     line_cost_afn: string,
     *     exchange_rate: string,
     *     purchase_price_usd: string
     * }
     */
    public function snapshot(
        string $unitAmountOriginal,
        int $qty,
        ?Currency $purchaseCurrency,
        ?string $transactionRateToLedger = null,
    ): array {
        $qty = max(1, $qty);
        $ledger = Currency::ledger();
        $unitOriginal = Money::round($unitAmountOriginal === '' ? '0' : $unitAmountOriginal, 2);
        $lineOriginal = Money::round(Money::mul($unitOriginal, (string) $qty, 8), 2);

        $isLedger = $purchaseCurrency === null
            || ($ledger && $purchaseCurrency->id === $ledger->id)
            || ($purchaseCurrency->code === (string) config('business.default_currency', 'AFN'));

        $enteredRate = $transactionRateToLedger !== null && (float) $transactionRateToLedger > 0
            ? Money::round((string) $transactionRateToLedger, 8)
            : null;

        if ($isLedger) {
            $rate = '1.00000000';
            $kind = 'ledger';
            $unitAfn = $unitOriginal;
        } elseif ($enteredRate !== null) {
            $rate = $enteredRate;
            $kind = 'transaction';
            $unitAfn = Money::round(Money::mul($unitOriginal, $rate, 12), 2);
        } else {
            if (! $purchaseCurrency) {
                $rate = '1.00000000';
                $kind = 'ledger';
                $unitAfn = $unitOriginal;
            } else {
                $rate = $this->currencies->rateToLedger($purchaseCurrency);
                $kind = 'catalog';
                $unitAfn = $this->currencies->toLedger($unitOriginal, $purchaseCurrency);
            }
        }

        $lineAfn = Money::round(Money::mul($unitAfn, (string) $qty, 8), 2);

        $usdLine = '0.00';
        if ($purchaseCurrency && strtoupper($purchaseCurrency->code) === 'USD') {
            $usdLine = $lineOriginal;
        }

        return [
            'purchase_unit_amount' => $unitOriginal,
            'purchase_line_amount' => $lineOriginal,
            'purchase_currency_id' => $purchaseCurrency?->id,
            'purchase_currency_code' => $purchaseCurrency?->code,
            'purchase_currency_symbol' => $purchaseCurrency?->symbol,
            'rate_to_ledger' => $rate,
            'rate_kind' => $kind,
            'unit_cost_afn' => $unitAfn,
            'line_cost_afn' => $lineAfn,
            // Existing column: keep the applied 1-currency-to-AFN rate for history.
            'exchange_rate' => $rate,
            'purchase_price_usd' => $usdLine,
        ];
    }
}
