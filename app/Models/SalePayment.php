<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    use HasFactory;

    protected $table = 'sale_payments';

    protected $fillable = [
        'sale_id',
        'customer_id',
        'amount_afn',
        'ledger_currency_code',
        'ledger_currency_symbol',
        'ledger_decimal_places',
        'remaining_due_afn',
        'payment_method',
        'reference_number',
        'notes',
        'paid_at',
        'received_by',
    ];

    protected $casts = [
        'amount_afn'         => 'decimal:2',
        'ledger_decimal_places' => 'integer',
        'remaining_due_afn'  => 'decimal:2',
        'paid_at'            => 'datetime',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    public const METHODS = [
        'cash'   => 'Cash',
        'card'   => 'Card',
        'bank'   => 'Bank Transfer',
        'mobile' => 'Mobile Money',
        'other'  => 'Other',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /* =========================================================
     |  Accessors
     ========================================================= */

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::METHODS[$this->payment_method] ?? ucfirst($this->payment_method ?? 'unknown');
    }
}