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
        'user_id',
        'name',
        'sku',
        'image',
        'volume_ml',
        'concentration',
        'trash',
    ];

    protected $casts = [
        'volume_ml' => 'integer',
        'trash' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public const CONCENTRATIONS = [
        'parfum' => 'Parfum',
        'edp' => 'Eau de Parfum (EDP)',
        'edt' => 'Eau de Toilette (EDT)',
        'edc' => 'Eau de Cologne (EDC)',
        'edt_legere' => 'EDT Légère',
        'body_mist' => 'Body Mist',
        'attar' => 'Attar',
        'oud' => 'Oud',
        'other' => 'Other',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class, 'variant_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'variant_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class, 'variant_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'variant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
     |  Stock (inventories is the source of truth)
     ========================================================= */

    public function availableQuantity(?int $branchId = null): int
    {
        return (int) $this->inventories()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('available_quantity');
    }

    public function getAvailableQuantityAttribute(): int
    {
        return $this->availableQuantity();
    }

    public function getTotalStockAttribute(): int
    {
        return $this->availableQuantity();
    }

    /** @deprecated Use availableQuantity() — inventories is the source of truth. */
    public function getStockQuantityAttribute(): int
    {
        return $this->availableQuantity();
    }

    /* =========================================================
     |  Accessors / pricing helpers (via product_prices)
     ========================================================= */

    public function getFullNameAttribute(): string
    {
        $parts = [];

        if ($this->product?->name) {
            $parts[] = $this->product->name;
        }
        if ($this->volume_ml) {
            $parts[] = $this->volume_ml.'ml';
        }
        if ($this->concentration) {
            $parts[] = strtoupper($this->concentration);
        }

        return implode(' · ', $parts) ?: 'Variant #'.$this->id;
    }

    public function getPrimaryBarcodeAttribute(): ?ProductBarcode
    {
        return $this->barcodes()->where('is_primary', true)->first()
            ?? $this->barcodes()->first();
    }

    public function activePriceAmount(string $type = 'sale', string $currencyCode = 'AFN', int $qty = 1): float
    {
        $today = now()->toDateString();
        $code = strtoupper($currencyCode);

        $query = $this->prices()
            ->where('price_type', $type)
            ->where('currency_code', $code)
            ->where('is_active', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today);
            });

        if ($type === 'wholesale') {
            $wholesale = (clone $query)
                ->where('min_qty', '>', 0)
                ->where('min_qty', '<=', $qty)
                ->orderByDesc('min_qty')
                ->first();

            if ($wholesale) {
                return (float) $wholesale->amount;
            }
        }

        $row = $query->orderBy('min_qty')->first();

        return $row ? (float) $row->amount : 0.0;
    }

    public function getPriceForQuantity(int $qty): float
    {
        $wholesale = $this->activePriceAmount('wholesale', 'AFN', $qty);
        if ($wholesale > 0) {
            $minQty = (int) ($this->prices()
                ->where('price_type', 'wholesale')
                ->where('currency_code', 'AFN')
                ->where('is_active', true)
                ->where('min_qty', '>', 0)
                ->where('min_qty', '<=', $qty)
                ->orderByDesc('min_qty')
                ->value('min_qty') ?? 0);

            if ($minQty > 0 && $qty >= $minQty) {
                return $wholesale;
            }
        }

        return $this->activePriceAmount('sale', 'AFN', $qty);
    }

    public function hasWholesalePrice(): bool
    {
        return $this->prices()
            ->where('price_type', 'wholesale')
            ->where('is_active', true)
            ->where('amount', '>', 0)
            ->where('min_qty', '>', 0)
            ->exists();
    }

    /**
     * @return \Illuminate\Support\Collection<int, ProductPrice>
     */
    protected function activePriceRows(string $type, string $currencyCode = 'AFN')
    {
        $this->loadMissing('prices');

        $today = now()->toDateString();
        $code = strtoupper($currencyCode);

        return $this->prices
            ->filter(function (ProductPrice $p) use ($type, $code, $today) {
                if ($p->price_type !== $type || strtoupper((string) $p->currency_code) !== $code || ! $p->is_active) {
                    return false;
                }
                if ($p->valid_from && $p->valid_from->toDateString() > $today) {
                    return false;
                }
                if ($p->valid_to && $p->valid_to->toDateString() < $today) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    public function getSalePriceAfnAttribute(): float
    {
        $row = $this->activePriceRows('sale', 'AFN')->sortBy('min_qty')->first();

        return $row ? (float) $row->amount : 0.0;
    }

    public function getPurchasePriceAfnAttribute(): float
    {
        $row = $this->activePriceRows('purchase', 'AFN')->sortBy('min_qty')->first();

        return $row ? (float) $row->amount : 0.0;
    }

    public function getWholesalePriceAfnAttribute(): float
    {
        $row = $this->activePriceRows('wholesale', 'AFN')
            ->where('min_qty', '>', 0)
            ->sortBy('min_qty')
            ->first();

        return $row ? (float) $row->amount : 0.0;
    }

    public function getWholesaleMinQtyAttribute(): int
    {
        $row = $this->activePriceRows('wholesale', 'AFN')
            ->where('min_qty', '>', 0)
            ->sortBy('min_qty')
            ->first();

        return $row ? (int) $row->min_qty : 0;
    }

    public function resolvedCatalogCurrencyId(): ?int
    {
        $sale = $this->prices()
            ->where('price_type', 'sale')
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if ($sale?->currency_id) {
            return (int) $sale->currency_id;
        }

        $any = $this->prices()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        return $any?->currency_id ? (int) $any->currency_id : null;
    }
}
