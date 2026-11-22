<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerBalance extends Model
{
    protected $table = 'customer_balances';

    protected $fillable = [
        'customer_id',
        'currency_id',
        'currency_code',
        'balance_currency',
        'balance_afn',
        'last_rate',
        'last_rate_at',
    ];

    protected $casts = [
        'balance_currency' => 'decimal:2',
        'balance_afn' => 'decimal:2',
        'last_rate' => 'decimal:8',
        'last_rate_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public static function forCustomer(int $customerId, string $currencyCode = 'AFN'): self
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
                'customer_id' => $customerId,
                'currency_id' => $currency->id,
            ],
            [
                'currency_code' => $currencyCode,
                'balance_currency' => 0,
                'balance_afn' => 0,
            ]
        );
    }

    public function adjust(float $deltaCurrency, float $deltaAfn, ?float $rate = null): self
    {
        $this->balance_currency = round((float) $this->balance_currency + $deltaCurrency, 2);
        $this->balance_afn = round((float) $this->balance_afn + $deltaAfn, 2);

        if ($rate !== null) {
            $this->last_rate = $rate;
            $this->last_rate_at = now();
        }

        $this->save();

        return $this;
    }
}
