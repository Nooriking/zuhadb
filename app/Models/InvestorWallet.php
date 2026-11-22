<?php

namespace App\Models;

/**
 * Compatibility façade over investor_wallet_balances.
 * The investor_wallets table has been dropped; callers keep using this API.
 */
class InvestorWallet
{
    public function __construct(
        public readonly int $investor_id,
    ) {}

    public static function forInvestor(int $investorId): self
    {
        return new self($investorId);
    }

    public function balanceFor(string $currencyCode): InvestorWalletBalance
    {
        return InvestorWalletBalance::forInvestor($this->investor_id, $currencyCode);
    }

    public function getCapitalBalanceAfnAttribute(): float
    {
        return (float) InvestorWalletBalance::query()
            ->where('investor_id', $this->investor_id)
            ->sum('capital_balance_afn');
    }

    public function getProfitBalanceAfnAttribute(): float
    {
        return (float) InvestorWalletBalance::query()
            ->where('investor_id', $this->investor_id)
            ->sum('profit_balance_afn');
    }

    public function __get(string $key): mixed
    {
        return match ($key) {
            'capital_balance_afn' => $this->getCapitalBalanceAfnAttribute(),
            'profit_balance_afn' => $this->getProfitBalanceAfnAttribute(),
            'total_invested_afn' => $this->getCapitalBalanceAfnAttribute(),
            'total_profit_earned_afn' => $this->getProfitBalanceAfnAttribute(),
            'total_withdrawn_afn' => 0.0,
            'investor_id' => $this->investor_id,
            default => null,
        };
    }

    public function getTotalBalanceAfnAttribute(): float
    {
        return $this->capital_balance_afn + $this->profit_balance_afn;
    }

    public function update(array $attributes): void
    {
        // Legacy AFN-only updates → ledger AFN wallet row.
        $row = $this->balanceFor('AFN');
        $payload = [];

        if (array_key_exists('capital_balance_afn', $attributes)) {
            $payload['capital_balance_afn'] = $attributes['capital_balance_afn'];
            $payload['capital_balance_currency'] = $attributes['capital_balance_afn'];
        }
        if (array_key_exists('profit_balance_afn', $attributes)) {
            $payload['profit_balance_afn'] = $attributes['profit_balance_afn'];
            $payload['profit_balance_currency'] = $attributes['profit_balance_afn'];
        }
        if (array_key_exists('last_transaction_at', $attributes)) {
            $payload['last_rate_at'] = $attributes['last_transaction_at'];
        }

        if ($payload !== []) {
            $row->fill($payload)->save();
        }
    }

    public function adjustCapital(
        string $currencyCode,
        float $deltaCurrency,
        float $deltaAfn,
        ?float $rate = null,
    ): InvestorWalletBalance {
        return $this->balanceFor($currencyCode)->adjustCapital($deltaCurrency, $deltaAfn, $rate);
    }

    public function adjustProfit(
        string $currencyCode,
        float $deltaCurrency,
        float $deltaAfn,
        ?float $rate = null,
    ): InvestorWalletBalance {
        return $this->balanceFor($currencyCode)->adjustProfit($deltaCurrency, $deltaAfn, $rate);
    }
}
