<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'customers';

    protected $fillable = [
        'name',
        'phone',
        'address',
        'notes',

        // Credit / Due
        'current_due_afn',
        'credit_limit_afn',
        'last_sale_at',

        // Status
        'status',
        'trash',

        // Relations
        'created_by',
    ];

    protected $casts = [
        'current_due_afn'   => 'decimal:2',
        'credit_limit_afn'  => 'decimal:2',
        'last_sale_at'      => 'datetime',
        'trash'             => 'boolean',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'deleted_at'        => 'datetime',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class, 'customer_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerTransaction::class, 'customer_id')
            ->orderByDesc('id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'customer_id');
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
        return $query->where('status', 'active');
    }

    public function scopeWithDue($query)
    {
        return $query->where('current_due_afn', '>', 0);
    }

    /* =========================================================
     |  Helpers
     ========================================================= */

    /**
     * آیا مشتری از سقف اعتبار عبور کرده؟
     */
    public function hasExceededCreditLimit(): bool
    {
        if ($this->credit_limit_afn <= 0) {
            return false;
        }

        return $this->current_due_afn > $this->credit_limit_afn;
    }

    /**
     * باقی‌مانده اعتبار قابل استفاده
     */
    public function availableCreditAfn(): float
    {
        if ($this->credit_limit_afn <= 0) {
            return 0;
        }

        return max(0, (float) $this->credit_limit_afn - (float) $this->current_due_afn);
    }

    /**
     * به‌روزرسانی مبلغ قرض فعلی
     */
    public function updateCurrentDue(float $newBalance): void
    {
        $this->update(['current_due_afn' => $newBalance]);
    }
}