<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRateHistory extends Model
{
    use HasFactory;

    protected $table = 'exchange_rate_history';

    protected $fillable = [
        'rate_date',
        'usd_to_afn',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'rate_date'   => 'date',
        'usd_to_afn'  => 'decimal:4',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* =========================================================
     |  Scopes
     ========================================================= */

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('rate_date');
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('rate_date', now()->month)
                     ->whereYear('rate_date', now()->year);
    }

    public function scopeThisYear($query)
    {
        return $query->whereYear('rate_date', now()->year);
    }

    /* =========================================================
     |  Static Helpers
     ========================================================= */

    /**
     * آخرین نرخ ثبت‌شده
     */
    public static function latestRate(): ?self
    {
        return self::latestFirst()->first();
    }

    /**
     * نرخ یک تاریخ مشخص (یا نزدیک‌ترین قبل از آن)
     */
    public static function rateOnDate(string $date): ?self
    {
        return self::whereDate('rate_date', '<=', $date)
            ->latestFirst()
            ->first();
    }

    /**
     * میانگین نرخ یک ماه مشخص
     */
    public static function averageForMonth(int $year, int $month): float
    {
        return (float) self::whereYear('rate_date', $year)
            ->whereMonth('rate_date', $month)
            ->avg('usd_to_afn') ?? 0;
    }

    /**
     * میانگین نرخ این ماه
     */
    public static function averageThisMonth(): float
    {
        return self::averageForMonth(now()->year, now()->month);
    }

    /**
     * بالاترین و پایین‌ترین نرخ این ماه
     */
    public static function monthMinMax(): array
    {
        $query = self::thisMonth();

        return [
            'min' => (float) ($query->min('usd_to_afn') ?? 0),
            'max' => (float) ($query->max('usd_to_afn') ?? 0),
        ];
    }
}