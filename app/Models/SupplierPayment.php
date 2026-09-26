<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPayment extends Model
{
    use HasFactory;
    protected $fillable = [
        'supplier_id',
        'receipt_number',
        'amount_afn',
        'balance_after',
        'payment_date',
        'exchange_rate',
        'payment_method',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'amount_afn' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'payment_date' => 'date',
        'exchange_rate' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public static function nextReceiptNumber(): string
    {
        $prefix = 'SP-'.now()->format('Ymd').'-';
        $last = static::query()
            ->where('receipt_number', 'like', $prefix.'%')
            ->orderByDesc('receipt_number')
            ->value('receipt_number');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
