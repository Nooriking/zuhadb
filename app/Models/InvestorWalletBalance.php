<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestorWalletBalance extends Model
{
    protected $table = 'investor_wallet_balances';

    protected $fillable = [
        'investor_id',
        'currency_id',
        'currency_code',
        'capital_balance_currency',
        'capital_balance_afn',
        'profit_balance_currency',
        'profit_balance_afn',
        'last_rate',
        'last_rate_at',
    ];

    protected $casts = [
        'capital_balance_currency' => 'decimal:2',
        'capital_balance_afn' => 'decimal:2',
        'profit_balance_currency' => 'decimal:2',
        'profit_balance_afn' => 'decimal:2',
        'last_rate' => 'decimal:8',
        'last_rate_at' => 'datetime',
    ];

    public function investor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function getTotalBalanceAfnAttribute(): float
    {
        return (float) $this->capital_balance_afn + (float) $this->profit_balance_afn;
    }

    public static function forInvestor(int $investorId, string $currencyCode = 'AFN'): self
    {
        $currencyCode = strtoupper(trim($currencyCode ?: 'AFN'));
        $currency = Currency::query()->notTrashed()->where('code', $currencyCode)->first();

        if (! $currency) {
            $currency = Currency::ledger()
                ?? Currency::query()->notTrashed()->where('code', 'AFN')->firstOrFail();
            $currencyCode = (string) $currency->code;
        }

        return static::query()->firstOrCreate(
            [
                'investor_id' => $investorId,
                'currency_id' => $currency->id,
            ],
            [
                'currency_code' => $currencyCode,
                'capital_balance_currency' => 0,
                'capital_balance_afn' => 0,
                'profit_balance_currency' => 0,
                'profit_balance_afn' => 0,
            ]
        );
    }

    public function adjustCapital(float $deltaCurrency, float $deltaAfn, ?float $rate = null): self
    {
        $this->capital_balance_currency = round((float) $this->capital_balance_currency + $deltaCurrency, 2);
        $this->capital_balance_afn = round((float) $this->capital_balance_afn + $deltaAfn, 2);
        $this->applyRate($rate);
        $this->save();

        return $this;
    }

    public function adjustProfit(float $deltaCurrency, float $deltaAfn, ?float $rate = null): self
    {
        $this->profit_balance_currency = round((float) $this->profit_balance_currency + $deltaCurrency, 2);
        $this->profit_balance_afn = round((float) $this->profit_balance_afn + $deltaAfn, 2);
        $this->applyRate($rate);
        $this->save();

        return $this;
    }

    private function applyRate(?float $rate): void
    {
        if ($rate === null) {
            return;
        }

        $this->last_rate = $rate;
        $this->last_rate_at = now();
    }
}
