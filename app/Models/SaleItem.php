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
        'gross_profit_afn',
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
        'gross_profit_afn'     => 'decimal:2',
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

    /**
     * Calculate cost, gross profit and cost breakdown from the item's batch.
     * Uses the batch's AFN unit cost (fixed at purchase time) so profit is
     * not affected by later exchange-rate changes.
     */
    public function calculateFromBatch(): void
    {
        $batch = ProductBatch::findOrFail($this->batch_id);
        $this->cost_price_afn = $batch->unit_cost_afn;

        $quantity     = (string) $this->quantity;
        $revenueAfn   = bcmul((string) $this->sale_price_afn, $quantity, 2);
        $cogsAfn      = bcmul((string) $batch->unit_cost_afn, $quantity, 2);
        $profitAfn    = bcsub($revenueAfn, $cogsAfn, 2);

        $this->gross_profit_afn = $profitAfn;

        // `cost_breakdown` is cast to array, so assign the array (not a JSON string)
        // to avoid double-encoding.
        $this->cost_breakdown = [
            'batch_id'      => $batch->id,
            'purchase_fx'   => $batch->exchange_rate,
            'unit_cost_afn' => $batch->unit_cost_afn,
            'sale_fx'       => $this->sale?->usd_to_afn_rate,
            'revenue_afn'   => $revenueAfn,
            'cogs_afn'      => $cogsAfn,
            'profit_afn'    => $profitAfn,
        ];
    }

    public function getTotalProfitAfnAttribute(): float
    {
        // Prefer explicit gross; otherwise company (store) + investor = gross.
        if ($this->gross_profit_afn !== null) {
            return (float) $this->gross_profit_afn;
        }

        return (float) $this->store_profit_afn + (float) $this->investor_profit_afn;
    }

    public function getIsFromInvestorAttribute(): bool
    {
        return (float) $this->investor_profit_afn > 0;
    }
}