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
        'investment_id',
        'batch_code',
        'invoice_number',
        'purchase_date',
        'payment_status',
        'bill_image',
        'notes',
        'purchase_currency_id',
        'purchase_amount_currency',
        'quantity',
        'remaining_quantity',
        'unit_cost_currency',
        'exchange_rate',
        'unit_cost_afn',
        'total_cost_afn',
        'profit_per_unit_afn',
        'sale_price_afn',
        'store_profit_per_unit_afn',
        'received_date',
        'status',
        'user_id',
        'trash',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'remaining_quantity' => 'integer',
        'purchase_amount_currency' => 'decimal:2',
        'unit_cost_currency' => 'decimal:4',
        'exchange_rate' => 'decimal:8',
        'unit_cost_afn' => 'decimal:2',
        'total_cost_afn' => 'decimal:2',
        'profit_per_unit_afn' => 'decimal:2',
        'sale_price_afn' => 'decimal:2',
        'store_profit_per_unit_afn' => 'decimal:2',
        'purchase_date' => 'date',
        'received_date' => 'date',
        'trash' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class, 'investment_id');
    }

    public function purchaseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'purchase_currency_id');
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

    /** Investor via the linked investment (users.role = investor). */
    public function getInvestorIdAttribute(): ?int
    {
        if (array_key_exists('investor_id', $this->attributes) && $this->attributes['investor_id'] !== null) {
            return (int) $this->attributes['investor_id'];
        }

        $this->loadMissing('investment:id,investor_id');

        return $this->investment?->investor_id ? (int) $this->investment->investor_id : null;
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
        return $query->whereNotNull('investment_id');
    }

    public function scopeStoreOwned($query)
    {
        return $query->whereNull('investment_id');
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
        $prefix = 'B-'.date('Ymd');
        $last = self::where('batch_code', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->value('batch_code');

        if ($last) {
            $lastNum = (int) substr($last, -4);

            return $prefix.'-'.str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        }

        return $prefix.'-0001';
    }

    /**
     * Create a batch using the exchange_rates board for the purchase currency → AFN rate.
     */
    public static function createWithSnapshot(array $data): self
    {
        $service = app(\App\Services\ExchangeService::class);
        $from = strtoupper((string) ($data['currency_code'] ?? 'AFN'));
        unset($data['currency_code']);

        $pair = $service->snapshot($from, 'AFN');

        if ($from !== 'AFN' && empty($data['exchange_rate'])) {
            $data['exchange_rate'] = $pair['rate'];
        } elseif ($from === 'AFN' && empty($data['exchange_rate'])) {
            $data['exchange_rate'] = '1.00000000';
        }

        if ($from !== 'AFN' && isset($data['unit_cost_currency'])) {
            $data['unit_cost_afn'] = $service->convert(
                (string) $data['unit_cost_currency'],
                $from,
                'AFN'
            );
        }

        return static::create($data);
    }

    public function isDepleted(): bool
    {
        return $this->remaining_quantity <= 0;
    }

    public function isInvestorFunded(): bool
    {
        return ! is_null($this->investment_id);
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

    public function getIsStoreOwnedAttribute(): bool
    {
        return $this->investment_id === null;
    }

    public function getTotalStoreProfitAfnAttribute(): float
    {
        return (float) $this->store_profit_per_unit_afn * $this->quantity;
    }

    public function getRealizedStoreProfitAfnAttribute(): float
    {
        return (float) $this->store_profit_per_unit_afn * $this->sold_quantity;
    }

    public function getRemainingCostAfnAttribute(): float
    {
        return (float) bcmul((string) $this->remaining_quantity, (string) $this->unit_cost_afn, 2);
    }

    public function getExpectedStoreProfitAfnAttribute(): float
    {
        return (float) bcmul((string) $this->remaining_quantity, (string) ($this->store_profit_per_unit_afn ?? 0), 2);
    }

    /* =========================================================
     |  Stock Operations
     ========================================================= */

    public function reduceStock(int $qty): void
    {
        $newRemaining = max(0, $this->remaining_quantity - $qty);

        $this->update([
            'remaining_quantity' => $newRemaining,
            'status' => $newRemaining === 0 ? 'depleted' : 'active',
        ]);
    }

    public function restoreStock(int $qty): void
    {
        $newRemaining = min($this->quantity, $this->remaining_quantity + $qty);

        $this->update([
            'remaining_quantity' => $newRemaining,
            'status' => $newRemaining > 0 ? 'active' : $this->status,
        ]);
    }

    /* =========================================================
     |  FIFO COST CALCULATION
     ========================================================= */

    /**
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
     *         investment_id: int|null,
     *         profit_per_unit_afn: float,
     *         store_profit_per_unit_afn: float
     *     }>
     * }
     */
    public static function calculateFifoCost(int $branchId, int $variantId, int $qty): array
    {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero.');
        }

        $remaining = $qty;
        $totalCost = '0';
        $breakdown = [];

        $batches = self::query()
            ->with('investment:id,investor_id')
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
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (int) $batch->remaining_quantity);
            $costPerUnit = (string) $batch->unit_cost_afn;
            $lineCost = bcmul((string) $take, $costPerUnit, 2);
            $totalCost = bcadd($totalCost, $lineCost, 2);

            $breakdown[] = [
                'batch_id' => $batch->id,
                'batch_code' => $batch->batch_code,
                'qty' => $take,
                'cost_per_unit_afn' => (float) $costPerUnit,
                'line_cost_afn' => (float) $lineCost,
                'investor_id' => $batch->investor_id,
                'investment_id' => $batch->investment_id,
                'profit_per_unit_afn' => (float) $batch->profit_per_unit_afn,
                'store_profit_per_unit_afn' => (float) $batch->store_profit_per_unit_afn,
                'sale_price_afn' => (float) $batch->sale_price_afn,
            ];

            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new \Exception(
                "Insufficient stock for variant #{$variantId}: requested {$qty}, missing {$remaining}"
            );
        }

        $costPerUnitAfn = bcdiv($totalCost, (string) $qty, 8);

        return [
            'cost_afn' => (float) $totalCost,
            'cost_per_unit_afn' => (float) $costPerUnitAfn,
            'breakdown' => $breakdown,
        ];
    }

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

    protected static function booted(): void
    {
        static::saving(function (ProductBatch $batch) {
            if ($batch->quantity && $batch->unit_cost_afn !== null && $batch->unit_cost_afn !== '') {
                $batch->total_cost_afn = bcmul((string) $batch->quantity, (string) $batch->unit_cost_afn, 2);
            }
        });
    }
}
