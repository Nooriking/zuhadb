<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_variants';

    protected $fillable = [
        'product_id',
        // ❌ 'description' حذف شد
        'image',
        'volume_ml',
        'concentration',
        'name',
        'sku',

        // Prices
        'purchase_price_afn',
        'purchase_amount',
        'purchase_currency_id',
        'sale_price_afn',
        'sale_amount',
        'sale_currency_id',
        'wholesale_price_afn',
        'wholesale_amount',
        'wholesale_currency_id',
        'wholesale_min_qty',
        'profit_per_unit',
        'stock_quantity',
        'minimum_stock',

        'trash',
        'user_id',
    ];

    protected $casts = [
        'volume_ml'            => 'integer',
        'purchase_price_afn'   => 'decimal:2',
        'purchase_amount'      => 'decimal:2',
        'sale_price_afn'       => 'decimal:2',
        'sale_amount'          => 'decimal:2',
        'wholesale_price_afn'  => 'decimal:2',
        'wholesale_amount'     => 'decimal:2',
        'wholesale_min_qty'    => 'integer',
        'profit_per_unit'      => 'decimal:2',
        'stock_quantity'       => 'integer',
        'minimum_stock'        => 'integer',
        'trash'                => 'boolean',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
        'deleted_at'           => 'datetime',
    ];

    public const CONCENTRATIONS = [
        'parfum'     => 'Parfum',
        'edp'        => 'Eau de Parfum (EDP)',
        'edt'        => 'Eau de Toilette (EDT)',
        'edc'        => 'Eau de Cologne (EDC)',
        'edt_legere' => 'EDT Légère',
        'body_mist'  => 'Body Mist',
        'attar'      => 'Attar',
        'oud'        => 'Oud',
        'other'      => 'Other',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function purchaseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'purchase_currency_id');
    }

    public function saleCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'sale_currency_id');
    }

    public function wholesaleCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'wholesale_currency_id');
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class, 'variant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Purchases (each Product row with this variant_id is a purchase batch).
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Product::class, 'variant_id');
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

    /* =========================================================
     |  Accessors
     ========================================================= */

    public function getFullNameAttribute(): string
    {
        $parts = [];

        if ($this->product?->name) {
            $parts[] = $this->product->name;
        }
        if ($this->volume_ml) {
            $parts[] = $this->volume_ml . 'ml';
        }
        if ($this->concentration) {
            $parts[] = strtoupper($this->concentration);
        }

        return implode(' · ', $parts) ?: 'Variant #' . $this->id;
    }

    public function getTotalStockAttribute(): int
    {
        return (int) $this->purchases()
            ->where('trash', false)
            ->sum('remaining_quantity');
    }

    public function getPrimaryBarcodeAttribute(): ?ProductBarcode
    {
        return $this->barcodes()->where('is_primary', true)->first()
            ?? $this->barcodes()->first();
    }

    public function getPriceForQuantity(int $qty): float
    {
        if (
            $this->wholesale_min_qty > 0
            && $qty >= $this->wholesale_min_qty
            && $this->wholesale_price_afn > 0
        ) {
            return (float) $this->wholesale_price_afn;
        }

        return (float) $this->sale_price_afn;
    }

    public function hasWholesalePrice(): bool
    {
        return $this->wholesale_price_afn > 0 && $this->wholesale_min_qty > 0;
    }
}