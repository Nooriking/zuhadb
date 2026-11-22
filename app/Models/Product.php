<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    protected $primaryKey = 'id';

    protected $fillable = [
        'brand_id',
        'user_id',
        'name',
        'gender',
        'status',
        'description',
        'trash',
    ];

    protected $casts = [
        'trash' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'display_name',
        'is_active',
        'is_trashed',
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

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'product_id');
    }

    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id')
            ->where('trash', false);
    }

    /** Batches belonging to this product's variants. */
    public function batches(): HasManyThrough
    {
        return $this->hasManyThrough(
            ProductBatch::class,
            ProductVariant::class,
            'product_id',
            'variant_id',
            'id',
            'id'
        );
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

    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereHas('variants.inventories', function ($q) {
            $q->where('available_quantity', '>', 0);
        });
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->whereDoesntHave('variants.inventories', function ($q) {
            $q->where('available_quantity', '>', 0);
        });
    }

    public function scopeStoreOwned(Builder $query): Builder
    {
        return $query->whereHas('batches', fn ($q) => $q->whereNull('investment_id'));
    }

    public function scopeInvestorOwned(Builder $query): Builder
    {
        return $query->whereHas('batches', fn ($q) => $q->whereNotNull('investment_id'));
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

    public function getTotalStockAttribute(): int
    {
        $variantIds = $this->variants()->where('trash', false)->pluck('id');

        if ($variantIds->isEmpty()) {
            return 0;
        }

        return (int) Inventory::query()
            ->whereIn('variant_id', $variantIds)
            ->where('trash', false)
            ->sum('available_quantity');
    }

    public function getIsInStockAttribute(): bool
    {
        return $this->getTotalStockAttribute() > 0;
    }

    public function getTotalPurchaseAfnAttribute(): float
    {
        $variantIds = $this->variants()->pluck('id');

        return (float) ProductBatch::query()
            ->whereIn('variant_id', $variantIds)
            ->where('trash', false)
            ->sum('total_cost_afn');
    }

    public function getTotalProfitAfnAttribute(): float
    {
        $variantIds = $this->variants()->pluck('id');

        return (float) ProductBatch::query()
            ->whereIn('variant_id', $variantIds)
            ->where('trash', false)
            ->get()
            ->sum(fn (ProductBatch $b) => (float) bcmul((string) $b->profit_per_unit_afn, (string) $b->quantity, 2));
    }

    public function getTotalStoreProfitAfnAttribute(): float
    {
        $variantIds = $this->variants()->pluck('id');

        return (float) ProductBatch::query()
            ->whereIn('variant_id', $variantIds)
            ->where('trash', false)
            ->get()
            ->sum(fn (ProductBatch $b) => (float) bcmul((string) ($b->store_profit_per_unit_afn ?? 0), (string) $b->quantity, 2));
    }

    public function getPurchaseCountAttribute(): int
    {
        $variantIds = $this->variants()->pluck('id');

        return (int) ProductBatch::query()
            ->whereIn('variant_id', $variantIds)
            ->where('trash', false)
            ->count();
    }

    public function getVariantsCountAttribute(): int
    {
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

    public function moveToTrash(): void
    {
        $this->update([
            'trash' => true,
            'user_id' => auth()->id(),
        ]);

        $this->variants()->update([
            'trash' => true,
            'user_id' => auth()->id(),
        ]);

        $variantIds = $this->variants()->pluck('id')->all();
        if (! empty($variantIds)) {
            ProductBatch::query()
                ->whereIn('variant_id', $variantIds)
                ->update(['trash' => true]);
        }
    }

    public function restoreFromTrash(): void
    {
        $this->update([
            'trash' => false,
            'user_id' => auth()->id(),
        ]);

        $this->variants()->update([
            'trash' => false,
            'user_id' => auth()->id(),
        ]);

        $variantIds = $this->variants()->pluck('id')->all();
        if (! empty($variantIds)) {
            ProductBatch::query()
                ->whereIn('variant_id', $variantIds)
                ->update(['trash' => false]);
        }
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    protected static function booted(): void
    {
        static::deleting(function (Product $product) {
            if ($product->isForceDeleting()) {
                return;
            }

            $product->variants()->update([
                'trash' => true,
                'user_id' => auth()->id(),
            ]);

            $variantIds = $product->variants()->pluck('id')->all();
            if (! empty($variantIds)) {
                ProductBatch::query()
                    ->whereIn('variant_id', $variantIds)
                    ->update(['trash' => true]);
            }
        });
    }
}
