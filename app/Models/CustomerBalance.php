<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Rebuild per-currency balances from customer_transactions (source of truth for debt).
     * Also syncs the denormalized customers.current_due_afn cache.
     *
     * Note: payment/return rows store signed (negative) amount_afn / amount_currency,
     * so ABS() is used before subtracting from sales.
     */
    public static function recalculateFor(int $customerId): void
    {
        if (! Schema::hasTable('customer_balances') || ! Schema::hasTable('customer_transactions')) {
            return;
        }

        $afn = Currency::query()->notTrashed()->where('code', 'AFN')->first()
            ?? Currency::ledger();

        $balances = CustomerTransaction::query()
            ->where('customer_id', $customerId)
            ->selectRaw("
                currency_id,
                currency_code,
                SUM(CASE WHEN type = 'sale' THEN amount_afn ELSE 0 END) as total_sales,
                SUM(CASE WHEN type IN ('payment', 'return') THEN ABS(amount_afn) ELSE 0 END) as total_payments,
                SUM(CASE WHEN type = 'adjustment' THEN amount_afn ELSE 0 END) as total_adjustments,
                SUM(CASE WHEN type = 'sale' THEN amount_currency ELSE 0 END) as total_sales_currency,
                SUM(CASE WHEN type IN ('payment', 'return') THEN ABS(amount_currency) ELSE 0 END) as total_payments_currency,
                SUM(CASE WHEN type = 'adjustment' THEN amount_currency ELSE 0 END) as total_adjustments_currency,
                MAX(exchange_rate_to_afn) as last_rate
            ")
            ->groupBy('currency_id', 'currency_code')
            ->get();

        $seenCurrencyIds = [];

        foreach ($balances as $b) {
            $currencyCode = strtoupper(trim((string) ($b->currency_code ?: 'AFN')));
            $currencyId = $b->currency_id ? (int) $b->currency_id : (int) ($afn?->id ?? 0);

            if ($currencyId <= 0) {
                $resolved = Currency::query()->notTrashed()->where('code', $currencyCode)->first() ?? $afn;
                $currencyId = (int) ($resolved?->id ?? 0);
                $currencyCode = strtoupper(trim((string) ($resolved?->code ?? $currencyCode)));
            }

            if ($currencyId <= 0) {
                continue;
            }

            $balanceAfn = bcadd(
                bcsub((string) $b->total_sales, (string) $b->total_payments, 2),
                (string) $b->total_adjustments,
                2
            );

            $balanceCurrency = bcadd(
                bcsub((string) ($b->total_sales_currency ?? '0'), (string) ($b->total_payments_currency ?? '0'), 2),
                (string) ($b->total_adjustments_currency ?? '0'),
                2
            );

            $lastRate = $currencyCode === 'AFN'
                ? '1'
                : (string) (($b->last_rate !== null && (float) $b->last_rate > 0) ? $b->last_rate : 1);

            static::query()->updateOrCreate(
                [
                    'customer_id' => $customerId,
                    'currency_id' => $currencyId,
                ],
                [
                    'currency_code' => $currencyCode,
                    'balance_currency' => $balanceCurrency,
                    'balance_afn' => $balanceAfn,
                    'last_rate' => $lastRate,
                    'last_rate_at' => now(),
                ]
            );

            $seenCurrencyIds[] = $currencyId;
        }

        $orphanQuery = static::query()->where('customer_id', $customerId);
        if ($seenCurrencyIds !== []) {
            $orphanQuery->whereNotIn('currency_id', array_values(array_unique($seenCurrencyIds)));
        }
        $orphanQuery->delete();

        $totalDueAfn = (string) (static::query()
            ->where('customer_id', $customerId)
            ->sum('balance_afn') ?: '0');

        if (bccomp($totalDueAfn, '0', 2) < 0) {
            $totalDueAfn = '0';
        }

        Customer::query()->where('id', $customerId)->update([
            'current_due_afn' => $totalDueAfn,
        ]);
    }
}
