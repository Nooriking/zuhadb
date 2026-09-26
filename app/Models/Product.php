<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';
    protected $primaryKey = 'id';

    /* =========================================================
     |  Fillable — بدون image, currency_id, purchase_number, unit_cost
     ========================================================= */

    protected $fillable = [
        // Product info
        'brand_id',
        'user_id',
        'name',
        'gender',
        'status',
        'description',
        // ❌ 'image' حذف شد

        // Purchase info
        'variant_id',
        'investor_id',
        'supplier_id',
        // ❌ 'currency_id' حذف شد
        // ❌ 'purchase_number' حذف شد
        'invoice_number',
        'purchase_date',
        'exchange_rate',
        'quantity',
        'remaining_quantity',
        // ❌ 'unit_cost' حذف شد
        'purchase_price_afn',
        'wholesale_price_afn',
        'wholesale_min_qty',
        'profit_per_unit',
        'line_total',
        'line_profit',
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
        'trash'               => 'boolean',
        'purchase_date'       => 'date',
        'exchange_rate'       => 'decimal:4',
        'quantity'            => 'integer',
        'remaining_quantity'  => 'integer',
        'purchase_price_afn'  => 'decimal:2',
        'wholesale_price_afn' => 'decimal:2',
        'wholesale_min_qty'   => 'integer',
        'profit_per_unit'     => 'decimal:2',
        'line_total'          => 'decimal:2',
        'line_profit'         => 'decimal:2',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
        'deleted_at'          => 'datetime',
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

    // ❌ currency() حذف شد

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

    public function scopeTrashed(Builder $query): Builder
    {
        return $query->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)->where('trash', true);
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

    public function scopeWithUnpaidPurchases(Builder $query): Builder
    {
        return $query->whereNotNull('variant_id')
            ->where(function ($q) {
                $q->where('payment_status', '!=', 'paid')
                  ->orWhereNull('payment_status');
            });
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