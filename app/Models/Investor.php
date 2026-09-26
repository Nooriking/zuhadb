<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Investor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'investors';

    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'email',
        'address',
        'notes',
        'status',
        'trash',
        'created_by',
    ];

    protected $casts = [
        'trash'      => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(InvestorWallet::class, 'investor_id');
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class, 'investor_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InvestorTransaction::class, 'investor_id');
    }

    public function profitEntries(): HasMany
    {
        return $this->hasMany(InvestorProfitEntry::class, 'investor_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(InvestorWithdrawal::class, 'investor_id');
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'investor_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class, 'investor_id');
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

    /* =========================================================
     |  Helpers
     ========================================================= */

    /**
     * کیف پول را می‌سازد اگر وجود ندارد.
     */
    public function getOrCreateWallet(): InvestorWallet
    {
        return $this->wallet()->firstOrCreate(
            ['investor_id' => $this->id],
            [
                'capital_balance_afn' => 0,
                'profit_balance_afn'  => 0,
                'total_invested_afn'       => 0,
                'total_profit_earned_afn'  => 0,
                'total_withdrawn_afn'      => 0,
            ]
        );
    }

    public function getTotalBalanceAfnAttribute(): float
    {
        if (! $this->wallet) return 0;
        return (float) $this->wallet->capital_balance_afn + (float) $this->wallet->profit_balance_afn;
    }
}