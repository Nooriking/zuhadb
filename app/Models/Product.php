<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';
    protected $primaryKey = 'id';

    /* =========================================================
     |  Fillable
     ========================================================= */

    protected $fillable = [
        // Product info
        'brand_id',
        'user_id',
        'name',
        'gender',
        'status',
        'description',
        'base_unit_name',
        'pack_unit_name',
        'units_per_pack',

        // Purchase info
        'variant_id',
        'investor_id',
        'supplier_id',
        'invoice_number',
        'purchase_date',
        'exchange_rate',
        'quantity',
        'remaining_quantity',
        'purchase_price_afn',
        'wholesale_price_afn',
        'wholesale_min_qty',

        // ✅ Investor profit
        'profit_per_unit',
        'line_total',
        'line_profit',

        // ✅ Store profit (جدید)
        'store_profit_per_unit',
        'line_store_profit',

        'bill_image',
        'notes',
        'payment_status',

        // Meta
        'trash',
    ];

    /* =========================================================
     |  Casts
     ========================================================= */

    protected $casts = [
        'trash'                  => 'boolean',
        'purchase_date'          => 'date',
        'exchange_rate'          => 'decimal:4',
        'quantity'               => 'integer',
        'remaining_quantity'     => 'integer',
        'purchase_price_afn'     => 'decimal:2',
        'wholesale_price_afn'    => 'decimal:2',
        'wholesale_min_qty'      => 'integer',

        // ✅ Investor profit
        'profit_per_unit'        => 'decimal:2',
        'line_total'             => 'decimal:2',
        'line_profit'            => 'decimal:2',

        // ✅ Store profit (جدید)
        'store_profit_per_unit'  => 'decimal:2',
        'line_store_profit'      => 'decimal:2',
        'units_per_pack'         => 'decimal:6',

        'created_at'             => 'datetime',
        'updated_at'             => 'datetime',
        'deleted_at'             => 'datetime',
    ];

    protected $appends = [
        'display_name',
        'is_active',
        'is_trashed',
        'is_purchase',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class, 'investor_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id')
            ->where('trash', false);
    }

    public function purchaseRows(): HasMany
    {
        return $this->hasMany(Product::class, 'name', 'name')
            ->whereNotNull('variant_id')
            ->where('brand_id', $this->brand_id);
    }

    public function barcodes(): HasManyThrough
    {
        return $this->hasManyThrough(
            ProductBarcode::class,
            ProductVariant::class,
            'product_id',
            'variant_id',
            'id',
            'id'
        );
    }

    /* =========================================================
     |  Scopes
     ========================================================= */

    public function scopeNotTrashed(Builder $query): Builder
    {
        return $query->where('trash', false);
    }

    /**
     * ✅ scope اصلاح‌شده — نام از trashed به onlyTrash تغییر یافت
     * تا با SoftDeletes تداخل نکند.
     */
    public function scopeOnlyTrash(Builder $query): Builder
    {
        return $query
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('trash', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 'inactive');
    }

    public function scopeByBrand(Builder $query, int $brandId): Builder
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeByGender(Builder $query, string $gender): Builder
    {
        return $query->where('gender', $gender);
    }

    public function hasUnitConversion(): bool
    {
        return filled($this->base_unit_name)
            && filled($this->pack_unit_name)
            && $this->units_per_pack !== null
            && (float) $this->units_per_pack > 0;
    }

    public function conversionLabel(): ?string
    {
        if (! $this->hasUnitConversion()) {
            return null;
        }

        $factor = rtrim(rtrim(number_format((float) $this->units_per_pack, 6, '.', ''), '0'), '.');

        return '1 '.$this->pack_unit_name.' = '.$factor.' '.$this->base_unit_name;
    }

    /**
     * Inventory and sales store quantities in the base unit.
     */
    public function quantityToBaseUnits(int $quantity, string $unit = 'base'): int
    {
        $unit = $unit === 'pack' ? 'pack' : 'base';

        if ($unit === 'base') {
            return $quantity;
        }

        if (! $this->hasUnitConversion()) {
            throw ValidationException::withMessages([
                'quantity_unit' => 'This product has no pack-to-base conversion defined.',
            ]);
        }

        if (function_exists('bcmul')) {
            $raw = bcmul((string) $quantity, (string) $this->units_per_pack, 6);
        } else {
            $raw = number_format($quantity * (float) $this->units_per_pack, 6, '.', '');
        }

        if (function_exists('bccomp') && bccomp($raw, (string) (int) $raw, 6) !== 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Pack quantity must convert to a whole number of '.$this->base_unit_name.'.',
            ]);
        }

        $base = (int) $raw;
        if ($base < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Converted quantity must be at least 1 '.$this->base_unit_name.'.',
            ]);
        }

        return $base;
    }

    public function scopeMainProducts(Builder $query): Builder
    {
        return $query->whereNull('variant_id');
    }

    public function scopePurchases(Builder $query): Builder
    {
        return $query->whereNotNull('variant_id');
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereHas('variants', function ($q) {
            $q->where('stock_quantity', '>', 0)->notTrashed();
        });
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->whereDoesntHave('variants', function ($q) {
            $q->where('stock_quantity', '>', 0)->notTrashed();
        });
    }

    public function scopePurchasedBetween(Builder $query, $from, $to): Builder
    {
        return $query->where('purchase_date', '>=', $from)
                     ->where('purchase_date', '<=', $to)
                     ->whereNotNull('variant_id');
    }

    public function scopeWithUnpaidPurchases(Builder $query): Builder
    {
        return $query->whereNotNull('variant_id')
            ->where(function ($q) {
                $q->where('payment_status', '!=', 'paid')
                  ->orWhereNull('payment_status');
            });
    }

    /** ✅ فقط purchase row هایی که مال دوکان هستند (بدون investor) */
    public function scopeStoreOwned(Builder $query): Builder
    {
        return $query->whereNotNull('variant_id')->whereNull('investor_id');
    }

    /** ✅ فقط purchase row هایی که مال سرمایه‌گذار هستند */
    public function scopeInvestorOwned(Builder $query): Builder
    {
        return $query->whereNotNull('variant_id')->whereNotNull('investor_id');
    }

    public static function supplierCredits(): array
    {
        $rows = SupplierInvoice::query()
            ->with('supplier:id,name')
            ->where('due_afn', '>', 0)
            ->orderByDesc('purchase_date')
            ->get();

        $suppliers = $rows
            ->groupBy('supplier_id')
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'id' => $first->supplier_id,
                    'supplier' => $first->supplier?->name ?? 'No supplier',
                    'amount' => round((float) $group->sum('due_afn'), 2),
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('amount')
            ->values();

        return [
            'total' => round((float) $rows->sum('due_afn'), 2),
            'suppliers' => $suppliers,
            'items' => $rows->take(12)->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->invoice_no,
                'supplier' => $row->supplier?->name,
                'amount' => (float) $row->due_afn,
                'date' => optional($row->purchase_date)->toDateString(),
                'invoice' => $row->invoice_no,
            ])->values(),
        ];
    }

    /* =========================================================
     |  Accessors
     ========================================================= */

    public function getDisplayNameAttribute(): string
    {
        $parts = array_filter([
            $this->brand?->name,
            $this->name,
        ]);

        return implode(' — ', $parts) ?: '—';
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function getIsTrashedAttribute(): bool
    {
        return (bool) $this->trash;
    }

    public function getIsPurchaseAttribute(): bool
    {
        return $this->variant_id !== null;
    }

    public function getTotalStockAttribute(): int
    {
        if ($this->variant_id !== null) {
            return (int) $this->remaining_quantity;
        }

        return (int) $this->variants()
            ->where('trash', false)
            ->sum('stock_quantity');
    }

    public function getIsInStockAttribute(): bool
    {
        return $this->getTotalStockAttribute() > 0;
    }

    public function getTotalPurchaseAfnAttribute(): float
    {
        if ($this->variant_id !== null) {
            return (float) $this->purchase_price_afn;
        }

        return (float) Product::where('name', $this->name)
            ->where('brand_id', $this->brand_id)
            ->whereNotNull('variant_id')
            ->where('trash', false)
            ->sum('purchase_price_afn');
    }

    public function getTotalProfitAfnAttribute(): float
    {
        if ($this->variant_id !== null) {
            return (float) $this->line_profit;
        }

        return (float) Product::where('name', $this->name)
            ->where('brand_id', $this->brand_id)
            ->whereNotNull('variant_id')
            ->where('trash', false)
            ->sum('line_profit');
    }

    // ✅ سود کل دوکان (جدید)
    public function getTotalStoreProfitAfnAttribute(): float
    {
        if ($this->variant_id !== null) {
            return (float) $this->line_store_profit;
        }

        return (float) Product::where('name', $this->name)
            ->where('brand_id', $this->brand_id)
            ->whereNotNull('variant_id')
            ->where('trash', false)
            ->sum('line_store_profit');
    }

    public function getPurchaseCountAttribute(): int
    {
        if ($this->variant_id !== null) {
            return 1;
        }

        return Product::where('name', $this->name)
            ->where('brand_id', $this->brand_id)
            ->whereNotNull('variant_id')
            ->where('trash', false)
            ->count();
    }

    public function getVariantsCountAttribute(): int
    {
        if ($this->variant_id !== null) {
            return 0;
        }

        return $this->variants()->where('trash', false)->count();
    }

    /* =========================================================
     |  Helpers
     ========================================================= */

    public function canBeSoftDeleted(): bool
    {
        return ! $this->trash;
    }

    public function canBeRestored(): bool
    {
        return (bool) $this->trash;
    }

    public function refreshStock(): void
    {
        $this->variants()->get()->each(function (ProductVariant $variant) {
            $totalStock = Product::where('variant_id', $variant->id)
                ->where('trash', false)
                ->sum('remaining_quantity');

            $variant->update([
                'stock_quantity' => (int) $totalStock,
            ]);
        });
    }

    public function moveToTrash(): void
    {
        $this->update([
            'trash'   => true,
            'user_id' => auth()->id(),
        ]);

        $this->variants()->update([
            'trash'   => true,
            'user_id' => auth()->id(),
        ]);

        $variantIds = $this->variants()->pluck('id')->all();
        if (! empty($variantIds)) {
            Product::whereIn('variant_id', $variantIds)
                ->update(['trash' => true, 'user_id' => auth()->id()]);
        }
    }

    public function restoreFromTrash(): void
    {
        $this->update([
            'trash'   => false,
            'user_id' => auth()->id(),
        ]);

        $this->variants()->update([
            'trash'   => false,
            'user_id' => auth()->id(),
        ]);

        $variantIds = $this->variants()->pluck('id')->all();
        if (! empty($variantIds)) {
            Product::whereIn('variant_id', $variantIds)
                ->update(['trash' => false, 'user_id' => auth()->id()]);
        }
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /* =========================================================
     |  Boot
     ========================================================= */

    protected static function booted(): void
    {
        static::saved(function (Product $product) {
            if ($product->variant_id && $product->supplier_id) {
                SupplierInvoice::syncFromProduct($product);
            }
        });

        static::deleting(function (Product $product) {
            if ($product->isForceDeleting()) {
                return;
            }

            if ($product->variant_id !== null) {
                return;
            }

            $product->variants()->update([
                'trash'   => true,
                'user_id' => auth()->id(),
            ]);

            $variantIds = $product->variants()->pluck('id')->all();
            if (! empty($variantIds)) {
                Product::whereIn('variant_id', $variantIds)
                    ->update(['trash' => true, 'user_id' => auth()->id()]);
            }
        });
    }
}