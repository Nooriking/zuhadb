<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    use HasFactory;

    protected $table = 'sale_return_items';

    protected $fillable = [
        'sale_return_id',
        'sale_item_id',
        'batch_id',
        'variant_id',
        'quantity',
        'unit_price_afn',
        'total_afn',
        'condition',
        'reason',
        'expense_id',
        'expense_amount_afn',
    ];

    protected $casts = [
        'quantity'            => 'integer',
        'unit_price_afn'      => 'decimal:2',
        'total_afn'           => 'decimal:2',
        'expense_amount_afn'  => 'decimal:2',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    /* =========================================================
     |  Constants
     ========================================================= */

    const CONDITION_GOOD    = 'good';
    const CONDITION_DAMAGED = 'damaged';
    const CONDITION_EXPIRED = 'expired';
    const CONDITION_OTHER   = 'other';
    const CONDITION_USED    = 'used';

    const CONDITIONS = [
        'good'    => 'Good',
        'damaged' => 'Damaged',
        'expired' => 'Expired',
        'other'   => 'Other',
        'used'    => 'Used',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class, 'sale_return_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'expense_id');
    }

    /* =========================================================
     |  Accessors
     ========================================================= */

    public function getConditionLabelAttribute(): string
    {
        return self::CONDITIONS[$this->condition]
            ?? ucfirst($this->condition ?? 'unknown');
    }

    /* =========================================================
     |  Business Helpers
     ========================================================= */

    public function isGood(): bool
    {
        return $this->condition === self::CONDITION_GOOD;
    }

    public function isDamaged(): bool
    {
        return $this->condition === self::CONDITION_DAMAGED;
    }

    public function isExpired(): bool
    {
        return $this->condition === self::CONDITION_EXPIRED;
    }

    public function isUsed(): bool
    {
        return $this->condition === self::CONDITION_USED;
    }

    /**
     * آیا باید به انبار برگردد؟
     * فقط اگر سالم باشد
     */
    public function shouldReturnToStock(): bool
    {
        return $this->isGood();
    }
}