<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Receipt extends Model
{
    use HasFactory;

    protected $table = 'receipts';

    protected $fillable = [
        'customer_id',
        'sale_id',
        'sale_payment_id',
        'receipt_number',
        'amount_afn',
        'remaining_due_afn',
        'receipt_type',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount_afn'         => 'decimal:2',
        'remaining_due_afn'  => 'decimal:2',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    /* =========================================================
     |  Constants
     ========================================================= */

    const TYPE_SALE_INVOICE    = 'sale_invoice';
    const TYPE_PAYMENT_RECEIPT = 'payment_receipt';

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function salePayment(): BelongsTo
    {
        return $this->belongsTo(SalePayment::class, 'sale_payment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* =========================================================
     |  Scopes
     ========================================================= */

    public function scopeSaleInvoices($query)
    {
        return $query->where('receipt_type', self::TYPE_SALE_INVOICE);
    }

    public function scopePaymentReceipts($query)
    {
        return $query->where('receipt_type', self::TYPE_PAYMENT_RECEIPT);
    }

    /* =========================================================
     |  Accessors
     ========================================================= */

    public function getReceiptTypeLabelAttribute(): string
    {
        return match ($this->receipt_type) {
            self::TYPE_SALE_INVOICE    => 'Sale Invoice',
            self::TYPE_PAYMENT_RECEIPT => 'Payment Receipt',
            default                    => ucfirst(str_replace('_', ' ', $this->receipt_type ?? 'unknown')),
        };
    }

    /* =========================================================
     |  Helpers — Auto-Generate Receipt Number
     ========================================================= */

    public static function generateReceiptNumber(): string
    {
        return DB::transaction(function () {
            $prefix = 'RCP-' . date('Ymd');

            $last = self::where('receipt_number', 'like', "{$prefix}-%")
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('receipt_number');

            $nextNum = $last ? ((int) substr($last, -4)) + 1 : 1;

            return $prefix . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        });
    }
}