<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Investment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'investments';

    protected $fillable = [
        'investor_id',
        'source_type',              // ✅ اضافه شد — cash | goods
        'parent_investment_id',
        'investment_number',
        'currency_id',
        'currency_code',
        'currency_symbol',
        'exchange_rate',
        'capital_amount_currency',
        'capital_amount_afn',
        'available_amount_currency',
        'available_amount_afn',
        'allocated_amount_afn',
        'profit_amount_currency',
        'profit_amount_afn',
        'withdrawn_amount_afn',
        'investment_date',
        'status',
        'notes',
        'created_by',
        'user_id',
        'trash',
    ];

    protected $casts = [
        'exchange_rate'             => 'decimal:8',
        'capital_amount_currency'   => 'decimal:2',
        'capital_amount_afn'        => 'decimal:2',
        'available_amount_currency' => 'decimal:2',
        'available_amount_afn'      => 'decimal:2',
        'allocated_amount_afn'      => 'decimal:2',
        'profit_amount_currency'    => 'decimal:2',
        'profit_amount_afn'         => 'decimal:2',
        'withdrawn_amount_afn'  => 'decimal:2',
        'investment_date'       => 'date',
        'trash'                 => 'boolean',
        'created_at'            => 'datetime',
        'updated_at'            => 'datetime',
        'deleted_at'            => 'datetime',
    ];

    /* =========================================================
     |  Status Labels
     ========================================================= */

    public const STATUS_LABELS = [
        'active'         => 'Active',
        'partially_used' => 'Partially Used',
        'fully_used'     => 'Fully Used',
        'withdrawn'      => 'Withdrawn',
        'closed'         => 'Closed',
    ];

    /* =========================================================
     |  ✅ Source Type Labels (Cash vs Goods)
     ========================================================= */

    public const SOURCE_TYPES = [
        'cash'  => 'Cash',
        'goods' => 'Goods',
    ];

    public function getSourceTypeLabelAttribute(): string
    {
        return self::SOURCE_TYPES[$this->source_type] ?? $this->source_type;
    }

    public function isCashInvestment(): bool
    {
        return $this->source_type === 'cash';
    }

    public function isGoodsInvestment(): bool
    {
        return $this->source_type === 'goods';
    }

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class, 'investor_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Investment::class, 'parent_investment_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(Investment::class, 'parent_investment_id');
    }

    public function purchaseAllocations(): HasMany
    {
        return $this->hasMany(InvestmentPurchaseAllocation::class, 'investment_id');
    }

    public function productAllocations(): HasMany
    {
        return $this->hasMany(InvestmentProductAllocation::class, 'investment_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InvestorTransaction::class, 'investment_id');
    }

    public function profitEntries(): HasMany
    {
        return $this->hasMany(InvestorProfitEntry::class, 'investment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
        return $query->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)
                     ->where('trash', true);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'partially_used']);
    }

    public function scopeWithAvailableCapital($query)
    {
        return $query->where('available_amount_afn', '>', 0)
                     ->whereIn('status', ['active', 'partially_used'])
                     ->where('trash', false);
    }

    public function scopeForInvestor($query, int $investorId)
    {
        return $query->where('investor_id', $investorId);
    }

    public function scopeCash($query)
    {
        return $query->where('source_type', 'cash');
    }

    public function scopeGoods($query)
    {
        return $query->where('source_type', 'goods');
    }

    /* =========================================================
     |  Static Helpers
     ========================================================= */

    public static function generateInvestmentNumber(): string
    {
        $prefix = 'INV-' . date('Ymd');
        $last = self::where('investment_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->value('investment_number');

        if ($last) {
            $lastNum = (int) substr($last, -4);
            return $prefix . '-' . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        }

        return $prefix . '-0001';
    }

    /* =========================================================
     |  Status Management
     ========================================================= */

    public function refreshStatus(): void
    {
        $available = (float) $this->available_amount_afn;
        $allocated = (float) $this->allocated_amount_afn;

        if ($available <= 0.01 && $allocated > 0) {
            $status = 'fully_used';
        } elseif ($allocated > 0) {
            $status = 'partially_used';
        } else {
            $status = 'active';
        }

        $this->update(['status' => $status]);
    }

    /* =========================================================
     |  Capital Helpers
     ========================================================= */

    public function hasAvailableCapital(): bool
    {
        return (float) $this->available_amount_afn > 0.01;
    }

    public function getProgressPercentAttribute(): float
    {
        $capital = (float) $this->capital_amount_afn;
        if ($capital <= 0) return 0;

        $allocated = (float) $this->allocated_amount_afn;
        return round(($allocated / $capital) * 100, 1);
    }

    public function toAfn(float $amountAfn): float
    {
        return $amountAfn;
    }

    public function toCurrency(float $amountInAfn): float
    {
        return $amountInAfn;
    }

    /* =========================================================
     |  Purchase Allocation
     ========================================================= */

    public function allocateToPurchase(
        int $purchaseId,
        float $allocatedAmountAfn,
        ?string $notes = null
    ): InvestmentPurchaseAllocation {
        return DB::transaction(function () use ($purchaseId, $allocatedAmountAfn, $notes) {
            $availableAfn = (float) $this->available_amount_afn;

            if ($allocatedAmountAfn > $availableAfn + 0.01) {
                throw new \Exception(
                    "Investment available balance is {$availableAfn} USD, "
                    . "but you tried to allocate {$allocatedAmountAfn} USD."
                );
            }

            $allocation = InvestmentPurchaseAllocation::create([
                'investment_id'        => $this->id,
                'purchase_id'          => $purchaseId,
                'allocated_amount_afn' => $allocatedAmountAfn,
                'notes'                => $notes,
                'created_by'           => Auth::id(),
            ]);

            $this->update([
                'available_amount_afn' => $availableAfn - $allocatedAmountAfn,
                'allocated_amount_afn' => (float) $this->allocated_amount_afn + $allocatedAmountAfn,
                'user_id'              => Auth::id(),
            ]);

            $this->refreshStatus();

            return $allocation;
        });
    }

    /* =========================================================
     |  Stats Accessors
     ========================================================= */

    public function getTotalProfitPaidAttribute(): float
    {
        return (float) $this->profitEntries()
            ->where('status', 'paid')
            ->sum('profit_amount_afn');
    }

    public function getTotalProfitPendingAttribute(): float
    {
        return (float) $this->profitEntries()
            ->where('status', 'pending')
            ->sum('profit_amount_afn');
    }

    public function getTotalBatchesAttribute(): int
    {
        return $this->productAllocations()->count();
    }

    public function getTotalFundedQuantityAttribute(): int
    {
        return (int) $this->productAllocations()->sum('funded_quantity');
    }

    public function getTotalSoldQuantityAttribute(): int
    {
        return (int) $this->productAllocations()->sum('sold_quantity');
    }

    public function getTotalRemainingQuantityAttribute(): int
    {
        return (int) $this->productAllocations()->sum('remaining_quantity');
    }

    public function isFullySold(): bool
    {
        return $this->total_remaining_quantity <= 0;
    }
}