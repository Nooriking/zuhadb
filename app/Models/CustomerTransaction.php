<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomerTransaction extends Model
{
    use HasFactory;

    protected $table = 'customer_transactions';

    protected $fillable = [
        'customer_id',
        'type',
        'amount_afn',
        'balance_after',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount_afn'    => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    /* =========================================================
     |  Constants
     ========================================================= */

    const TYPE_SALE       = 'sale';
    const TYPE_PAYMENT    = 'payment';
    const TYPE_RETURN     = 'return';
    const TYPE_ADJUSTMENT = 'adjustment';

    const TYPES = [
        'sale'       => 'Sale',
        'payment'    => 'Payment',
        'return'     => 'Return',
        'adjustment' => 'Adjustment',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * رابطه پویا با منبع تراکنش (Sale, SalePayment, SaleReturn, ...)
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /* =========================================================
     |  Scopes
     ========================================================= */

    public function scopeSales($query)
    {
        return $query->where('type', self::TYPE_SALE);
    }

    public function scopePayments($query)
    {
        return $query->where('type', self::TYPE_PAYMENT);
    }

    public function scopeReturns($query)
    {
        return $query->where('type', self::TYPE_RETURN);
    }

    public function scopeAdjustments($query)
    {
        return $query->where('type', self::TYPE_ADJUSTMENT);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('id');
    }

    /* =========================================================
     |  Accessors
     ========================================================= */

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type]
            ?? ucfirst(str_replace('_', ' ', $this->type ?? 'unknown'));
    }

    public function getIsDebitAttribute(): bool
    {
        return (float) $this->amount_afn > 0;
    }

    public function getIsCreditAttribute(): bool
    {
        return (float) $this->amount_afn < 0;
    }

    public function getAmountAbsAttribute(): float
    {
        return abs((float) $this->amount_afn);
    }

    public function getFormattedAmountAttribute(): string
    {
        $amount = (float) $this->amount_afn;
        $sign   = $amount > 0 ? '+' : ($amount < 0 ? '-' : '');
        return $sign . number_format(abs($amount), 2) . ' AFN';
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format((float) $this->balance_after, 2) . ' AFN';
    }

    /* =========================================================
     |  Business Helpers
     ========================================================= */

    public function isSale(): bool
    {
        return $this->type === self::TYPE_SALE;
    }

    public function isPayment(): bool
    {
        return $this->type === self::TYPE_PAYMENT;
    }

    public function isReturn(): bool
    {
        return $this->type === self::TYPE_RETURN;
    }

    public function isAdjustment(): bool
    {
        return $this->type === self::TYPE_ADJUSTMENT;
    }

    /**
     * فقط تعدیل دستی قابل حذف است
     */
    public function canBeDeleted(): bool
    {
        return $this->isAdjustment();
    }
}