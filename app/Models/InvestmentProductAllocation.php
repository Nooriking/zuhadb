<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class InvestmentProductAllocation extends Model
{
    use HasFactory;

    protected $table = 'investment_product_allocations';

    protected $fillable = [
        'investment_id',
        'investment_purchase_allocation_id',
        'batch_id',
        'variant_id',
        'funded_quantity',
        'sold_quantity',
        'remaining_quantity',
        'cost_per_unit_afn',
        'profit_per_unit_afn',
        'capital_recovered_afn',
        'profit_earned_afn',
        'status',
    ];

    protected $casts = [
        'funded_quantity'              => 'integer',
        'sold_quantity'                => 'integer',
        'remaining_quantity'           => 'integer',
        'cost_per_unit_afn'      => 'decimal:2',
        'profit_per_unit_afn'    => 'decimal:2',
        'capital_recovered_afn'  => 'decimal:2',
        'profit_earned_afn'      => 'decimal:2',
        'created_at'                   => 'datetime',
        'updated_at'                   => 'datetime',
    ];

    /* =========================================================
     |  Status Labels
     ========================================================= */

    public const STATUS_LABELS = [
        'active'    => 'Active',
        'sold_out'  => 'Sold Out',
        'cancelled' => 'Cancelled',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class, 'investment_id');
    }

    public function purchaseAllocation(): BelongsTo
    {
        return $this->belongsTo(InvestmentPurchaseAllocation::class, 'investment_purchase_allocation_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /* =========================================================
     |  Scopes
     ========================================================= */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSoldOut($query)
    {
        return $query->where('status', 'sold_out');
    }

    public function scopeForInvestment($query, int $investmentId)
    {
        return $query->where('investment_id', $investmentId);
    }

    public function scopeForBatch($query, int $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    /* =========================================================
     |  Helpers
     ========================================================= */

    /**
     * هزینه‌ی کل سرمایه‌گذاری روی این تخصیص.
     */
    public function getTotalCostAfnAttribute(): float
    {
        return (float) $this->cost_per_unit_afn * $this->funded_quantity;
    }

    /**
     * مجموع سود قابل کسب (اگر تمام فروخته شود).
     */
    public function getPotentialProfitAfnAttribute(): float
    {
        return (float) $this->profit_per_unit_afn * $this->funded_quantity;
    }

    /**
     * درصد پیشرفت فروش.
     */
    public function getProgressPercentAttribute(): float
    {
        if ($this->funded_quantity <= 0) return 0;
        return round(($this->sold_quantity / $this->funded_quantity) * 100, 1);
    }

    /**
     * کاهش موجودی هنگام فروش.
     */
    public function recordSale(int $quantity): void
    {
        DB::transaction(function () use ($quantity) {
            $newSold = min($this->funded_quantity, $this->sold_quantity + $quantity);
            $newRemaining = max(0, $this->funded_quantity - $newSold);

            $this->update([
                'sold_quantity'      => $newSold,
                'remaining_quantity' => $newRemaining,
                'status'             => $newRemaining === 0 ? 'sold_out' : 'active',
            ]);
        });
    }

    /**
     * بازگرداندن موجودی هنگام برگشت از فروش.
     */
    public function recordReturn(int $quantity): void
    {
        DB::transaction(function () use ($quantity) {
            $newSold = max(0, $this->sold_quantity - $quantity);
            $newRemaining = min($this->funded_quantity, $this->remaining_quantity + $quantity);

            $this->update([
                'sold_quantity'      => $newSold,
                'remaining_quantity' => $newRemaining,
                'status'             => $newRemaining > 0 ? 'active' : 'sold_out',
            ]);
        });
    }
}