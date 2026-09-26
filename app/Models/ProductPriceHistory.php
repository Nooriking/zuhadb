<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ProductPriceHistory extends Model
{
    use HasFactory;

    protected $table = 'product_price_histories';

    protected $fillable = [
        'variant_id',
        'old_purchase_price_afn',
        'new_purchase_price_afn',
        'old_sale_price_afn',
        'new_sale_price_afn',
        'change_type',
        'reason',
        'changed_by',
    ];

    protected $casts = [
        'old_purchase_price_afn' => 'decimal:2',
        'new_purchase_price_afn' => 'decimal:2',
        'old_sale_price_afn'     => 'decimal:2',
        'new_sale_price_afn'     => 'decimal:2',
        'created_at'                  => 'datetime',
        'updated_at'                  => 'datetime',
    ];

    protected $appends = ['change_type_label'];

    public const CHANGE_TYPE_LABELS = [
        'purchase' => 'Purchase Price',
        'sale'     => 'Sale Price',
        'both'     => 'Both Prices',
    ];

    /* Relationships */

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /* Accessors */

    public function getChangeTypeLabelAttribute(): string
    {
        return self::CHANGE_TYPE_LABELS[$this->change_type] ?? $this->change_type;
    }

    public function getPurchaseChangeAfnAttribute(): float
    {
        return (float) $this->new_purchase_price_afn - (float) $this->old_purchase_price_afn;
    }

    public function getSaleChangeAfnAttribute(): float
    {
        return (float) $this->new_sale_price_afn - (float) $this->old_sale_price_afn;
    }

    /* Static helper */

    /**
     * ثبت تاریخچه تغییر قیمت.
     */
    public static function record(
        int $variantId,
        float $oldPurchaseAfn,
        float $newPurchaseAfn,
        float $oldSaleAfn,
        float $newSaleAfn,
        ?string $reason = null
    ): ?self {
        if ($oldPurchaseAfn === $newPurchaseAfn && $oldSaleAfn === $newSaleAfn) {
            return null;
        }

        $changeType = 'both';
        if ($oldPurchaseAfn !== $newPurchaseAfn && $oldSaleAfn === $newSaleAfn) {
            $changeType = 'purchase';
        } elseif ($oldPurchaseAfn === $newPurchaseAfn && $oldSaleAfn !== $newSaleAfn) {
            $changeType = 'sale';
        }

        return self::create([
            'variant_id'             => $variantId,
            'old_purchase_price_afn' => $oldPurchaseAfn,
            'new_purchase_price_afn' => $newPurchaseAfn,
            'old_sale_price_afn'     => $oldSaleAfn,
            'new_sale_price_afn'     => $newSaleAfn,
            'change_type'            => $changeType,
            'reason'                 => $reason,
            'changed_by'             => Auth::id(),
        ]);
    }
}