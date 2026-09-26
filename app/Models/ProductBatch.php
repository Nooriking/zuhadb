<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductBatch extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_batches';

    protected $fillable = [
        'branch_id',
        'variant_id',
        'purchase_item_id',        // ⭐ FK → products.id
        'investor_id',
        'batch_code',
        'quantity',
        'remaining_quantity',
        'unit_cost_afn',
        'total_cost_afn',
        'profit_per_unit_afn',
        'received_date',
        'status',
        'user_id',
        'trash',
    ];

    protected $casts = [
        'quantity'             => 'integer',
        'remaining_quantity'   => 'integer',
        'unit_cost_afn'        => 'decimal:2',
        'total_cost_afn'       => 'decimal:2',
        'profit_per_unit_afn'  => 'decimal:2',
        'received_date'             => 'date',
        'trash'                     => 'boolean',
        'created_at'                => 'datetime',
        'updated_at'                => 'datetime',
        'deleted_at'                => 'datetime',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * ⭐ purchase_item_id به جدول products اشاره میکند
     * (چون purchase rows در products ذخیره میشوند)
     */
    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'purchase_item_id');
    }

    /**
     * ⭐ alias برای وضوح بیشتر
     */
    public function purchaseProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'purchase_item_id');
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class, 'investor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'batch_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'batch_id');
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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->where('trash', false);
    }

    public function scopeInvestorFunded($query)
    {
        return $query->whereNotNull('investor_id');
    }

    public function scopeAvailableForSale($query, int $branchId, int $variantId)
    {
        return $query->where('branch_id', $branchId)
            ->where('variant_id', $variantId)
            ->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->where('trash', false)
            ->orderBy('received_date')
            ->orderBy('id');
    }

    /* =========================================================
     |  Helpers
     ========================================================= */

    public static function generateBatchCode(): string
    {
        $prefix = 'B-' . date('Ymd');
        $last = self::where('batch_code', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->value('batch_code');

        if ($last) {
            $lastNum = (int) substr($last, -4);
            return $prefix . '-' . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        }

        return $prefix . '-0001';
    }

    public function isDepleted(): bool
    {
        return $this->remaining_quantity <= 0;
    }

    public function isInvestorFunded(): bool
    {
        return ! is_null($this->investor_id);
    }

    public function getSoldQuantityAttribute(): int
    {
        return $this->quantity - $this->remaining_quantity;
    }

    public function getTotalInvestorProfitAfnAttribute(): float
    {
        return (float) $this->profit_per_unit_afn * $this->quantity;
    }

    public function getRealizedInvestorProfitAfnAttribute(): float
    {
        return (float) $this->profit_per_unit_afn * $this->sold_quantity;
    }

    /**
     * کم کردن موجودی Batch هنگام فروش
     */
    public function reduceStock(int $qty): void
    {
        $newRemaining = max(0, $this->remaining_quantity - $qty);

        $this->update([
            'remaining_quantity' => $newRemaining,
            'status'             => $newRemaining === 0 ? 'depleted' : 'active',
        ]);
    }

    /**
     * بازگرداندن موجودی هنگام مرجوعی
     */
    public function restoreStock(int $qty): void
    {
        $newRemaining = min($this->quantity, $this->remaining_quantity + $qty);

        $this->update([
            'remaining_quantity' => $newRemaining,
            'status'             => $newRemaining > 0 ? 'active' : $this->status,
        ]);
    }

    /* =========================================================
     |  ⭐ FIFO COST CALCULATION
     ========================================================= */

    /**
     * محاسبه هزینه FIFO برای یک variant در یک شعبه
     *
     * @return array{
     *     cost_afn: float,
     *     cost_per_unit_afn: float,
     *     breakdown: array<int, array{
     *         batch_id: int,
     *         batch_code: string,
     *         qty: int,
     *         cost_per_unit_afn: float,
     *         line_cost_afn: float,
     *         investor_id: int|null,
     *         profit_per_unit_afn: float
     *     }>
     * }
     *
     * @throws \Exception اگر موجودی کافی نباشد
     */
    public static function calculateFifoCost(int $branchId, int $variantId, int $qty): array
    {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero.');
        }

        $remaining = $qty;
        $totalCost = 0;
        $breakdown = [];

        $batches = self::query()
            ->where('branch_id', $branchId)
            ->where('variant_id', $variantId)
            ->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->where('trash', false)
            ->orderBy('received_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;

            $take = min($remaining, $batch->remaining_quantity);

            $costPerUnit = (float) $batch->unit_cost_afn;
            $lineCost    = $take * $costPerUnit;

            $totalCost += $lineCost;

            $breakdown[] = [
                'batch_id'            => $batch->id,
                'batch_code'          => $batch->batch_code,
                'qty'                 => $take,
                'cost_per_unit_afn'   => $costPerUnit,
                'line_cost_afn'       => $lineCost,
                'investor_id'         => $batch->investor_id,
                'profit_per_unit_afn' => (float) $batch->profit_per_unit_afn,
            ];

            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new \Exception(
                "Insufficient stock for variant #{$variantId}: requested {$qty}, missing {$remaining}"
            );
        }

        return [
            'cost_afn'          => $totalCost,
            'cost_per_unit_afn' => $totalCost / $qty,
            'breakdown'         => $breakdown,
        ];
    }

    /**
     * بررسی اینکه آیا موجودی کافی برای یک variant وجود دارد یا نه
     */
    public static function hasEnoughStock(int $branchId, int $variantId, int $qty): bool
    {
        $available = self::query()
            ->where('branch_id', $branchId)
            ->where('variant_id', $variantId)
            ->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->where('trash', false)
            ->sum('remaining_quantity');

        return $available >= $qty;
    }

    /**
     * مجموع موجودی قابل فروش یک variant در یک شعبه
     */
    public static function getAvailableQuantity(int $branchId, int $variantId): int
    {
        return (int) self::query()
            ->where('branch_id', $branchId)
            ->where('variant_id', $variantId)
            ->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->where('trash', false)
            ->sum('remaining_quantity');
    }
}