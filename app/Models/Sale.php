<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sales';

    protected $fillable = [
        'branch_id',
        'customer_id',
        'customer_name_snapshot',
        'customer_phone_snapshot',

        'invoice_number',
        'receipt_no',

        // Amounts
        'subtotal_afn',
        'discount_afn',
        'total_afn',

        // Due Flow
        'previous_due_afn',
        'new_sale_amount_afn',
        'grand_total_due_afn',
        'received_now_afn',
        'remaining_due_afn',
        'due_date',

        // Payments
        'payment_method',
        'paid_afn',
        'due_afn',

        // Profit
        'total_cost_afn',
        'total_profit_afn',
        'total_investor_profit_afn',
        'store_profit_afn',

        // Dates & Status
        'sale_date',
        'payment_status',
        'status',

        // Printing
        'printed_count',
        'last_printed_at',

        // Misc
        'notes',
        'created_by',
        'trash',
    ];

    protected $casts = [
        'subtotal_afn'               => 'decimal:2',
        'discount_afn'               => 'decimal:2',
        'total_afn'                  => 'decimal:2',

        'previous_due_afn'           => 'decimal:2',
        'new_sale_amount_afn'        => 'decimal:2',
        'grand_total_due_afn'        => 'decimal:2',
        'received_now_afn'           => 'decimal:2',
        'remaining_due_afn'          => 'decimal:2',
        'due_date'                   => 'date',

        'paid_afn'                   => 'decimal:2',
        'due_afn'                    => 'decimal:2',

        'total_cost_afn'             => 'decimal:2',
        'total_profit_afn'           => 'decimal:2',
        'total_investor_profit_afn'  => 'decimal:2',
        'store_profit_afn'           => 'decimal:2',

        'sale_date'                  => 'date',
        'printed_count'              => 'integer',
        'last_printed_at'            => 'datetime',
        'trash'                      => 'boolean',
        'created_at'                 => 'datetime',
        'updated_at'                 => 'datetime',
        'deleted_at'                 => 'datetime',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

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
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class, 'sale_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'sale_id');
    }

    public function profitEntries(): HasMany
    {
        return $this->hasMany(InvestorProfitEntry::class, 'sale_id');
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
        return $query->where('status', 'completed');
    }

    public function scopeWithRemainingDue($query)
    {
        return $query->where('remaining_due_afn', '>', 0);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('payment_status', ['unpaid', 'partial']);
    }

    /* =========================================================
     |  Helpers — Auto-Generate Numbers
     ========================================================= */

    public static function generateInvoiceNumber(): string
    {
        return DB::transaction(function () {
            $prefix = 'INV-' . date('Ymd');

            $last = self::where('invoice_number', 'like', "{$prefix}-%")
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('invoice_number');

            $nextNum = $last ? ((int) substr($last, -4)) + 1 : 1;

            return $prefix . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        });
    }

    public static function generateReceiptNumber(): string
    {
        return DB::transaction(function () {
            $prefix = 'RCP-' . date('Ymd');

            $last = self::where('receipt_no', 'like', "{$prefix}-%")
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('receipt_no');

            $nextNum = $last ? ((int) substr($last, -4)) + 1 : 1;

            return $prefix . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        });
    }

    /* =========================================================
     |  Accessors
     ========================================================= */

    public function getCustomerDisplayNameAttribute(): string
    {
        return $this->customer_name_snapshot
            ?? $this->customer?->name
            ?? 'Walk-in';
    }

    public function getCustomerDisplayPhoneAttribute(): ?string
    {
        return $this->customer_phone_snapshot
            ?? $this->customer?->phone;
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            'cash'   => 'Cash',
            'card'   => 'Card',
            'bank'   => 'Bank Transfer',
            'mobile' => 'Mobile Payment',
            'credit' => 'Credit',
            'other'  => 'Other',
            default  => ucfirst($this->payment_method ?? 'unknown'),
        };
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'unpaid'  => 'Unpaid',
            'partial' => 'Partial',
            'paid'    => 'Paid',
            default   => ucfirst($this->payment_status ?? 'unknown'),
        };
    }

    /* =========================================================
     |  Business Helpers
     ========================================================= */

    /**
     * محاسبه مجموع مبلغ قابل پرداخت این فروش + قرض قبلی
     */
    public function calculateGrandTotalDue(): float
    {
        return (float) $this->previous_due_afn + (float) $this->new_sale_amount_afn;
    }

    /**
     * محاسبه باقی‌مانده قرض بعد از پرداخت
     */
    public function calculateRemainingDue(): float
    {
        return max(0, $this->calculateGrandTotalDue() - (float) $this->received_now_afn);
    }

    /**
     * آیا این فروش باقی‌مانده دارد؟
     */
    public function hasRemainingDue(): bool
    {
        return (float) $this->remaining_due_afn > 0;
    }

    public function isPrinted(): bool
    {
        return $this->printed_count > 0;
    }

    public function markAsPrinted(?int $userId = null): void
    {
        $this->increment('printed_count');
        $this->update(['last_printed_at' => now()]);
    }
}