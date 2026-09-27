<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventories';

    protected $fillable = [
        'branch_id',
        'variant_id',
        'quantity',
        'reserved_quantity',
        'available_quantity',
        'average_cost_afn',
        'last_movement_at',
        'user_id',
        'trash',
    ];

    protected $casts = [
        'quantity'           => 'integer',
        'reserved_quantity'  => 'integer',
        'available_quantity' => 'integer',
        'average_cost_afn'   => 'decimal:2',
        'last_movement_at'   => 'datetime',
        'trash'              => 'boolean',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    /* Relationships */

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /* Scopes */

    public function scopeNotTrashed($query)
    {
        return $query->where('trash', false);
    }

    public function scopeLowStock($query)
    {
        return $query->whereHas('variant', function ($q) {
            $q->whereColumn('inventories.quantity', '<=', 'product_variants.minimum_stock');
        });
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', '<=', 0);
    }

    /* =========================================================
     |  Static Helpers — هسته‌ی مدیریت انبار
     ========================================================= */

    /**
     * دریافت یا ساخت رکورد Inventory برای (شعبه، واریانت)
     */
    public static function getOrCreate(int $branchId, int $variantId): self
    {
        return self::firstOrCreate(
            ['branch_id' => $branchId, 'variant_id' => $variantId],
            [
                'quantity'           => 0,
                'reserved_quantity'  => 0,
                'available_quantity' => 0,
                'average_cost_afn'   => 0,
                'trash'              => false,
            ]
        );
    }

    /**
     * افزایش موجودی (ورود کالا)
     */
    public static function increase(
        int $branchId,
        int $variantId,
        int $qty,
        string $type,
        ?int $batchId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        float $unitCostAfn = 0
    ): self {
        return DB::transaction(function () use ($branchId, $variantId, $qty, $type, $batchId, $referenceType, $referenceId, $notes, $unitCostAfn) {
            $inventory = self::getOrCreate($branchId, $variantId);

            $before = $inventory->quantity;
            $after  = $before + $qty;

            // محاسبه‌ی میانگین قیمت تمام‌شده (Weighted Average)
            $newAvgCost = $inventory->average_cost_afn;
            if ($qty > 0 && $unitCostAfn > 0) {
                $totalValue = ($before * $inventory->average_cost_afn) + ($qty * $unitCostAfn);
                $newAvgCost = $after > 0 ? $totalValue / $after : 0;
            }

            $inventory->update([
                'quantity'           => $after,
                'available_quantity' => $after - $inventory->reserved_quantity,
                'average_cost_afn'   => $newAvgCost,
                'last_movement_at'   => now(),
                'user_id'            => Auth::id(),
            ]);

            InventoryTransaction::create([
                'branch_id'       => $branchId,
                'variant_id'      => $variantId,
                'batch_id'        => $batchId,
                'type'            => $type,
                'quantity'        => $qty,
                'before_quantity' => $before,
                'after_quantity'  => $after,
                'reference_type'  => $referenceType,
                'reference_id'    => $referenceId,
                'notes'           => $notes,
                'created_by'      => Auth::id(),
            ]);

            $fresh = $inventory->fresh();
            self::warnIfLow($fresh);

            return $fresh;
        });
    }

    /**
     * کاهش موجودی (خروج کالا)
     */
    public static function decrease(
        int $branchId,
        int $variantId,
        int $qty,
        string $type,
        ?int $batchId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null
    ): self {
        return DB::transaction(function () use ($branchId, $variantId, $qty, $type, $batchId, $referenceType, $referenceId, $notes) {
            $inventory = self::getOrCreate($branchId, $variantId);
            $inventory = self::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();

            $before = $inventory->quantity;

            if ($qty > $before) {
                throw new \RuntimeException(
                    "Insufficient stock for variant #{$variantId} in branch #{$branchId}. Available: {$before}, requested: {$qty}."
                );
            }

            $after = $before - $qty;

            $inventory->update([
                'quantity'           => $after,
                'available_quantity' => $after - $inventory->reserved_quantity,
                'last_movement_at'   => now(),
                'user_id'            => Auth::id(),
            ]);

            InventoryTransaction::create([
                'branch_id'       => $branchId,
                'variant_id'      => $variantId,
                'batch_id'        => $batchId,
                'type'            => $type,
                'quantity'        => -$qty,
                'before_quantity' => $before,
                'after_quantity'  => $after,
                'reference_type'  => $referenceType,
                'reference_id'    => $referenceId,
                'notes'           => $notes,
                'created_by'      => Auth::id(),
            ]);

            $fresh = $inventory->fresh();
            self::warnIfLow($fresh);

            return $fresh;
        });
    }

    /**
     * تنظیم دستی موجودی
     */
    public static function adjust(
        int $branchId,
        int $variantId,
        int $newQty,
        ?string $notes = null
    ): self {
        return DB::transaction(function () use ($branchId, $variantId, $newQty, $notes) {
            if ($newQty < 0) {
                throw new \InvalidArgumentException('Inventory quantity cannot be negative.');
            }

            $inventory = self::getOrCreate($branchId, $variantId);
            $inventory = self::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();

            $before = $inventory->quantity;
            $diff   = $newQty - $before;

            $inventory->update([
                'quantity'           => $newQty,
                'available_quantity' => $newQty - $inventory->reserved_quantity,
                'last_movement_at'   => now(),
                'user_id'            => Auth::id(),
            ]);

            InventoryTransaction::create([
                'branch_id'       => $branchId,
                'variant_id'      => $variantId,
                'type'            => 'adjustment',
                'quantity'        => $diff,
                'before_quantity' => $before,
                'after_quantity'  => $newQty,
                'notes'           => $notes,
                'created_by'      => Auth::id(),
            ]);

            $fresh = $inventory->fresh();
            self::warnIfLow($fresh);

            return $fresh;
        });
    }

    private static function warnIfLow(self $inventory): void
    {
        $left = (int) $inventory->quantity;
        if ($left <= 0 || $left > 2) {
            return;
        }

        $variant = $inventory->variant()->with('product:id,name')->first();
        $name = $variant?->product?->name ?? 'Product';
        $body = "{$name} has {$left} left. Variant #{$inventory->variant_id}.";

        $admins = User::query()
            ->notTrashed()
            ->where('status', 'active')
            ->whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])
            ->get();

        foreach ($admins as $admin) {
            $already = ActivityNotification::query()
                ->where('user_id', $admin->id)
                ->whereNull('read_at')
                ->where('section', 'inventory')
                ->where('body', $body)
                ->exists();

            if ($already) {
                continue;
            }

            ActivityNotification::create([
                'user_id' => $admin->id,
                'section' => 'inventory',
                'title' => 'Low stock: 2 or fewer left',
                'body' => $body,
                'url' => '/inventory',
            ]);
        }
    }

    /* Accessors */

    public function getIsLowStockAttribute(): bool
    {
        if (! $this->variant) return false;
        return $this->quantity <= $this->variant->minimum_stock;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return $this->quantity <= 0;
    }
}