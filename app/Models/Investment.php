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
        'parent_investment_id',
        'investment_number',
        'capital_amount_afn',
        'available_amount_afn',
        'allocated_amount_afn',
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
        'capital_amount_afn'   => 'decimal:2',
        'available_amount_afn' => 'decimal:2',
        'allocated_amount_afn' => 'decimal:2',
        'profit_amount_afn'    => 'decimal:2',
        'withdrawn_amount_afn'      => 'decimal:2',
        'investment_date'           => 'date',
        'trash'                     => 'boolean',
        'created_at'                => 'datetime',
        'updated_at'                => 'datetime',
        'deleted_at'                => 'datetime',
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
     |  Relationships
     ========================================================= */

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class, 'investor_id');
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
        return $query->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)->where('trash', true);
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

    /* =========================================================
     |  Static Helpers
     ========================================================= */

    /**
     * تولید شماره یکتای Investment
     */
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

    /**
     * به‌روزرسانی وضعیت بر اساس موجودی و تخصیص.
     */
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

    /**
     * آیا این Investment هنوز پول قابل تخصیص دارد؟
     */
    public function hasAvailableCapital(): bool
    {
        return (float) $this->available_amount_afn > 0.01;
    }

    /**
     * درصد استفاده از سرمایه (۰ تا ۱۰۰)
     */
    public function getProgressPercentAttribute(): float
    {
        $capital = (float) $this->capital_amount_afn;
        if ($capital <= 0) return 0;

        $allocated = (float) $this->allocated_amount_afn;
        return round(($allocated / $capital) * 100, 1);
    }

    /**
     * تبدیل مبلغ ارز به AFN
     */
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

    /**
     * تخصیص سرمایه به یک خرید.
     *
     * @throws \Exception
     */
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

    /**
     * مجموع سود پرداخت شده
     */
    public function getTotalProfitPaidAttribute(): float
    {
        return (float) $this->profitEntries()
            ->where('status', 'paid')
            ->sum('profit_amount_afn');
    }

    /**
     * مجموع سود در انتظار پرداخت
     */
    public function getTotalProfitPendingAttribute(): float
    {
        return (float) $this->profitEntries()
            ->where('status', 'pending')
            ->sum('profit_amount_afn');
    }

    /**
     * تعداد Batchهای این Investment
     */
    public function getTotalBatchesAttribute(): int
    {
        return $this->productAllocations()->count();
    }

    /**
     * مجموع تعداد محصولات خریداری‌شده با این سرمایه
     */
    public function getTotalFundedQuantityAttribute(): int
    {
        return (int) $this->productAllocations()->sum('funded_quantity');
    }

    /**
     * مجموع تعداد فروخته‌شده
     */
    public function getTotalSoldQuantityAttribute(): int
    {
        return (int) $this->productAllocations()->sum('sold_quantity');
    }

    /**
     * مجموع تعداد باقی‌مانده
     */
    public function getTotalRemainingQuantityAttribute(): int
    {
        return (int) $this->productAllocations()->sum('remaining_quantity');
    }

    /**
     * آیا این Investment تمام محصولاتش فروخته شده؟
     */
    public function isFullySold(): bool
    {
        return $this->total_remaining_quantity <= 0;
    }
}