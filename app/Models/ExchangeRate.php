<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $table = 'exchange_rates';

    protected $fillable = [
        'from_currency_id',
        'to_currency_id',
        'rate',
        'effective_at',
        'created_by',
        'trash',
    ];

    protected $casts = [
        'rate'         => 'decimal:6',
        'effective_at' => 'datetime',
        'trash'        => 'boolean',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function fromCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_currency_id');
    }

    public function toCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* =========================================================
     |  Scopes
     ========================================================= */

    public function scopeNotTrashed($query)
    {
        return $query->where('trash', false);
    }

    public function scopeOnlyTrashed($query)
    {
        return $query->where('trash', true);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('effective_at');
    }

    /* =========================================================
     |  Static Helpers
     ========================================================= */

    /**
     * نرخ تبدیل جاری بین دو ارز را برمی‌گرداند.
     * اگر نرخی ثبت نشده باشد، null برمی‌گرداند.
     */
    public static function getRate(int $fromId, int $toId): ?float
    {
        if ($fromId === $toId) {
            return 1.0;
        }

        $rate = self::query()
            ->where('from_currency_id', $fromId)
            ->where('to_currency_id', $toId)
            ->where('trash', false)
            ->where('effective_at', '<=', now())
            ->orderByDesc('effective_at')
            ->value('rate');

        if ($rate !== null) {
            return (float) $rate;
        }

        // اگر نرخ معکوس ثبت شده بود، معکوسش را برگردان
        $inverse = self::query()
            ->where('from_currency_id', $toId)
            ->where('to_currency_id', $fromId)
            ->where('trash', false)
            ->where('effective_at', '<=', now())
            ->orderByDesc('effective_at')
            ->value('rate');

        return $inverse && (float) $inverse > 0 ? (1 / (float) $inverse) : null;
    }

    /**
     * نرخ جاری بین AFN و ارز دیگر.
     */
    public static function rateToBase(int $currencyId): ?float
    {
        $base = Currency::getBase();

        if (! $base || $base->id === $currencyId) {
            return 1.0;
        }

        return self::getRate($currencyId, $base->id);
    }

    /**
     * تبدیل مبلغ به ارز پایه (AFN).
     */
    public static function convertToBase(float $amount, int $fromCurrencyId): ?float
    {
        $rate = self::rateToBase($fromCurrencyId);

        return $rate !== null ? $amount * $rate : null;
    }

    /* =========================================================
     |  Accessors
     ========================================================= */

    public function getFormattedRateAttribute(): string
    {
        return rtrim(rtrim(number_format((float) $this->rate, 6, '.', ''), '0'), '.');
    }
}