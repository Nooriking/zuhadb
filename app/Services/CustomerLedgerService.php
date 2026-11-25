<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerBalance;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;

/**
 * Applies customer AR changes to per-currency customer_balances
 * (and denormalized current_due_afn via Customer::adjustBalance).
 */
class CustomerLedgerService
{
    public function recordSale(Sale $sale): void
    {
        // SaleService::createSale owns the balance write (new sale + payments).
    }

    public function recordPayment(SalePayment $payment): void
    {
        if (! $payment->customer_id) {
            return;
        }

        $customer = Customer::query()->whereKey($payment->customer_id)->lockForUpdate()->first();
        if (! $customer) {
            return;
        }

        $payCode = strtoupper((string) ($payment->currency_code ?: 'AFN'));
        $payCurrency = round((float) ($payment->amount_currency ?? $payment->amount_afn ?? 0), 2);
        $payAfn = round((float) ($payment->amount_afn ?? 0), 2);
        $payRate = $payment->rate_to_ledger !== null ? (float) $payment->rate_to_ledger : null;

        if (abs($payCurrency) < 0.009 && abs($payAfn) < 0.009) {
            return;
        }

        $this->applyCreditToBalances($customer, $payCode, $payCurrency, $payAfn, $payRate);
    }

    public function recordReturn(SaleReturn $return): void
    {
        if (! $return->customer_id) {
            return;
        }

        $customer = Customer::query()->whereKey($return->customer_id)->lockForUpdate()->first();
        if (! $customer) {
            return;
        }

        $amountAfn = round((float) ($return->total_afn ?? 0), 2);
        if (abs($amountAfn) < 0.009) {
            return;
        }

        $code = strtoupper((string) (
            $return->return_currency_code
            ?? $return->sale?->sale_currency_code
            ?? 'AFN'
        ));
        $rate = $return->exchange_rate_to_afn !== null ? (float) $return->exchange_rate_to_afn : null;
        $amountCurrency = $return->return_amount_currency !== null
            ? round((float) $return->return_amount_currency, 2)
            : $amountAfn;

        if ($rate === null || $rate <= 0) {
            $sale = $return->relationLoaded('sale') ? $return->sale : $return->sale()->first();
            $rate = $sale?->rateToAfn();
        }
        if ($return->return_amount_currency === null && $code !== 'AFN' && $rate && $rate > 0) {
            $amountCurrency = round($amountAfn / $rate, 2);
        }

        $this->applyCreditToBalances($customer, $code, $amountCurrency, $amountAfn, $rate);
    }

    /**
     * Manual AR adjustment (e.g. used-item credit on a return path).
     * Positive deltaAfn increases customer due.
     *
     * Call signature matches SaleReturnController used-item path:
     * recordAdjustment($customer, $deltaAfn, $newDueAfn, $notes, $currencyCode, $deltaCurrency, $rate)
     */
    public function recordAdjustment(
        Customer $customer,
        float $deltaAfn,
        ?float $newDueAfn = null,
        ?string $notes = null,
        string $currencyCode = 'AFN',
        ?float $deltaCurrency = null,
        ?float $rate = null,
    ): void {
        unset($notes, $newDueAfn);

        if (abs($deltaAfn) < 0.009 && ($deltaCurrency === null || abs($deltaCurrency) < 0.009)) {
            return;
        }

        $code = strtoupper(trim($currencyCode) ?: 'AFN');
        $currencyDelta = $deltaCurrency ?? $deltaAfn;
        $customer->adjustBalance($code, $currencyDelta, $deltaAfn, $rate);
    }

    public function recalculate(int $customerId): void
    {
        $customer = Customer::query()->find($customerId);
        if ($customer) {
            $customer->syncCurrentDueFromBalances();
        }
    }

    /**
     * Reduce customer due using a payment/credit.
     * 1) Prefer the matching currency bucket.
     * 2) Apply leftover AFN across other positive buckets (oldest currency code order).
     */
    private function applyCreditToBalances(
        Customer $customer,
        string $payCode,
        float $payCurrency,
        float $payAfn,
        ?float $payRate,
    ): void {
        $leftAfn = round($payAfn, 2);
        $leftCurrency = round($payCurrency, 2);

        $balances = CustomerBalance::query()
            ->where('customer_id', $customer->id)
            ->orderByRaw("CASE WHEN UPPER(currency_code) = ? THEN 0 ELSE 1 END", [$payCode])
            ->orderBy('currency_code')
            ->lockForUpdate()
            ->get();

        foreach ($balances as $balance) {
            if ($leftAfn <= 0.009) {
                break;
            }

            $balCurrency = round((float) $balance->balance_currency, 2);
            $balAfn = round((float) $balance->balance_afn, 2);
            if ($balCurrency <= 0.009 && $balAfn <= 0.009) {
                continue;
            }

            $code = strtoupper((string) $balance->currency_code);
            $isMatch = $code === $payCode;

            if ($isMatch) {
                $takeCurrency = round(min($leftCurrency, $balCurrency), 2);
                $takeAfn = $balCurrency > 0
                    ? round(min($leftAfn, $balAfn, $balAfn * ($takeCurrency / max($balCurrency, 0.01))), 2)
                    : round(min($leftAfn, $balAfn), 2);
                // Prefer exact currency take when rates align.
                if ($takeCurrency > 0.009 && $balCurrency > 0.009) {
                    $takeAfn = round(min($leftAfn, ($takeCurrency / $balCurrency) * $balAfn), 2);
                }
                $rate = $payRate ?? ($balance->last_rate !== null ? (float) $balance->last_rate : null);
                $customer->adjustBalance($code, -1 * $takeCurrency, -1 * $takeAfn, $rate);
                $leftCurrency = round($leftCurrency - $takeCurrency, 2);
                $leftAfn = round($leftAfn - $takeAfn, 2);
                continue;
            }

            // Cross-currency: consume by AFN weight of this bucket.
            $takeAfn = round(min($leftAfn, $balAfn), 2);
            if ($takeAfn <= 0.009) {
                continue;
            }
            $takeCurrency = $balAfn > 0
                ? round(($takeAfn / $balAfn) * $balCurrency, 2)
                : $takeAfn;
            $rate = $balance->last_rate !== null ? (float) $balance->last_rate : null;
            $customer->adjustBalance($code, -1 * $takeCurrency, -1 * $takeAfn, $rate);
            $leftAfn = round($leftAfn - $takeAfn, 2);
        }

        // Overpayment / no buckets: park remainder on the payment currency (may go negative credit).
        if ($leftAfn > 0.009 || $leftCurrency > 0.009) {
            $customer->adjustBalance($payCode, -1 * max(0, $leftCurrency), -1 * max(0, $leftAfn), $payRate);
        }
    }
}
