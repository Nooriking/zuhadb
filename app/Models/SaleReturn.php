<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class SaleReturn extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sale_returns';

    protected $fillable = [
        'sale_id',
        'branch_id',
        'customer_id',
        'return_number',

        // Amounts
        'subtotal_afn',
        'total_afn',

        // Customer Due Impact
        'previous_due_afn',
        'new_due_afn',

        // Refund
        'refund_amount_afn',
        'refund_method',

        // Details
        'reason',
        'return_date',
        'status',

        // Audit
        'created_by',
        'trash',
    ];

    protected $casts = [
        'subtotal_afn'       => 'decimal:2',
        'total_afn'          => 'decimal:2',
        'previous_due_afn'   => 'decimal:2',
        'new_due_afn'        => 'decimal:2',
        'refund_amount_afn'  => 'decimal:2',
        'return_date'        => 'date',
        'trash'              => 'boolean',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
        'deleted_at'         => 'datetime',
    ];

    /* =========================================================
     |  Constants
     ========================================================= */

    const STATUS_DRAFT     = 'draft';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    const REFUND_METHODS = [
        'cash'        => 'Cash',
        'card'        => 'Card',
        'bank'        => 'Bank Transfer',
        'mobile'      => 'Mobile Money',
        'credit_note' => 'Credit note',
        'other'       => 'Other',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class, 'sale_return_id');
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

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /* =========================================================
     |  Helpers — Auto-Generate Return Number
     ========================================================= */

    public static function generateReturnNumber(): string
    {
        return DB::transaction(function () {
            $prefix = 'RET-' . date('Ymd');

            $last = self::where('return_number', 'like', "{$prefix}-%")
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('return_number');

            $nextNum = $last ? ((int) substr($last, -4)) + 1 : 1;

            return $prefix . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        });
    }

    /* =========================================================
     |  Accessors
     ========================================================= */

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT     => 'Draft',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            default                => ucfirst($this->status ?? 'unknown'),
        };
    }

    public function getRefundMethodLabelAttribute(): ?string
    {
        if (! $this->refund_method) return null;
        return self::REFUND_METHODS[$this->refund_method]
            ?? ucfirst(str_replace('_', ' ', $this->refund_method));
    }

    /* =========================================================
     |  Business Helpers
     ========================================================= */

    public function hasRefund(): bool
    {
        return (float) $this->refund_amount_afn > 0;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * محاسبه اختلاف قرض مشتری قبل و بعد از برگشتی
     */
    public function getDueReductionAttribute(): float
    {
        return max(0, (float) $this->previous_due_afn - (float) $this->new_due_afn);
    }
}