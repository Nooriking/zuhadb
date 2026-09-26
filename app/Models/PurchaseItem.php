<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_items';

    protected $fillable = [
        'purchase_id',
        'variant_id',
        'investor_id',
        'quantity',
        'unit_cost_afn',
        'discount_afn',
        'total_cost_afn',
        'profit_per_unit_afn',
    ];

    protected $casts = [
        'quantity'             => 'integer',
        'unit_cost_afn'        => 'decimal:2',
        'discount_afn'         => 'decimal:2',
        'total_cost_afn'       => 'decimal:2',
        'profit_per_unit_afn'  => 'decimal:2',
        'created_at'                => 'datetime',
        'updated_at'                => 'datetime',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'purchase_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class, 'investor_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class, 'purchase_item_id');
    }

    /* =========================================================
     |  Helpers
     ========================================================= */

    /**
     * آیا این آیتم از پول سرمایه‌گذار خریداری شده؟
     */
    public function isInvestorFunded(): bool
    {
        return ! is_null($this->investor_id);
    }

    /**
     * سود کل سرمایه‌گذار برای این آیتم
     */
    public function getTotalInvestorProfitAfnAttribute(): float
    {
        return (float) $this->profit_per_unit_afn * $this->quantity;
    }
}