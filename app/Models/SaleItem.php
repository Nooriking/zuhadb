<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    protected $table = 'sale_items';

    protected $fillable = [
        'sale_id',
        'variant_id',
        'batch_id',
        'product_name_snapshot',
        'sku_snapshot',
        'barcode_snapshot',
        'quantity',
        'cost_price_afn',
        'sale_price_afn',
        'discount_afn',
        'line_total_afn',
        'store_profit_afn',
        'investor_profit_afn',
        'cost_breakdown',   // ⭐ FIFO breakdown as JSON
    ];

    protected $casts = [
        'quantity'             => 'integer',
        'cost_price_afn'       => 'decimal:2',
        'sale_price_afn'       => 'decimal:2',
        'discount_afn'         => 'decimal:2',
        'line_total_afn'       => 'decimal:2',
        'store_profit_afn'     => 'decimal:2',
        'investor_profit_afn'  => 'decimal:2',
        'cost_breakdown'       => 'array',
        'created_at'                => 'datetime',
        'updated_at'                => 'datetime',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    /* =========================================================
     |  Helpers
     ========================================================= */

    public function getTotalProfitAfnAttribute(): float
    {
        return (float) $this->store_profit_afn + (float) $this->investor_profit_afn;
    }

    public function getIsFromInvestorAttribute(): bool
    {
        return (float) $this->investor_profit_afn > 0;
    }
}