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
        'return_currency_code',
        'unit_price_currency',
        'total_currency',
        'unit_price_afn',
        'total_afn',
        'cost_price_afn',
        'cogs_reversal_afn',
        'exchange_rate_to_afn',
        'condition',
        'reason',
        'expense_id',
        'expense_amount_afn',
        'expense_bearer',
    ];

    protected $casts = [
        'quantity'               => 'integer',
        'unit_price_currency'    => 'decimal:4',
        'total_currency'         => 'decimal:2',
        'unit_price_afn'         => 'decimal:2',
        'total_afn'              => 'decimal:2',
        'cost_price_afn'         => 'decimal:2',
        'cogs_reversal_afn'      => 'decimal:2',
        'exchange_rate_to_afn'   => 'decimal:8',
        'expense_amount_afn'     => 'decimal:2',
        'created_at'             => 'datetime',
        'updated_at'             => 'datetime',
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

    const BEARER_SHOP     = 'shop';
    const BEARER_CUSTOMER = 'customer';

    const BEARERS = [
        'shop'     => 'Shop',
        'customer' => 'Customer',
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

    public function isShopExpense(): bool
    {
        return $this->expense_bearer === self::BEARER_SHOP;
    }

    /**
     * Expense amount for this line (capped at the line total)
     */
    public function effectiveExpenseAmount(): float
    {
        $line = (float) $this->total_afn;

        if ($this->isUsed()) {
            return $line;
        }

        return min($line, max(0, (float) $this->expense_amount_afn));
    }

    /**
     * Amount credited back to the customer / deducted from their due.
     * Shop expense → full line to customer; customer expense → subtract customer portion.
     */
    public function customerCreditAmount(): float
    {
        $line = (float) $this->total_afn;

        return $this->isShopExpense()
            ? $line
            : max(0, $line - $this->effectiveExpenseAmount());
    }

    /**
     * Should this item return to stock?
     * Only when condition is good.
     */
    public function shouldReturnToStock(): bool
    {
        return $this->isGood();
    }

    /**
     * Reverse original sale COGS (unit cost × return qty) — not just sale revenue.
     * Uses the snapshot cost on the original sale_item; never live batch rates.
     */
    public function calculateFromOriginalSaleItem(): void
    {
        $original = SaleItem::findOrFail($this->sale_item_id);
        $this->cost_price_afn = $original->cost_price_afn;
        $this->cogs_reversal_afn = bcmul(
            (string) $original->cost_price_afn,
            (string) $this->quantity,
            2
        );
    }
}