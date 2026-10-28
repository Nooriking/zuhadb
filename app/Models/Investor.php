<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

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
     |  ✅ حذف شد — دیگر user_id را NULL نمی‌کنیم
     |
     |  قبلاً booted() داشتیم که وقتی investor به trash می‌رفت،
     |  user_id را NULL می‌کرد. این باعث می‌شد investment بعدی
     |  نتواند investor قبلی را پیدا کند و investor جدید بسازد.
     |
     |  حالا user_id حفظ می‌شود و با unique index در DB،
     |  از ساخت investor تکراری جلوگیری می‌شود.
     ========================================================= */

    /* =========================================================
     |  ✅ Helper — پیدا کردن یا ساختن investor برای user
     ========================================================= */

    /**
     * یک investor برای user پیدا می‌کند یا می‌سازد.
     *
     * - اگر investor فعال وجود دارد → همان را برمی‌گرداند
     * - اگر investor در trash است → آن را restore می‌کند
     * - اگر هیچ‌کدام نیست → یکی می‌سازد
     *
     * @param  int    $userId
     * @param  array  $extraData  داده‌های اضافی برای ساخت investor جدید
     * @return self
     */
    public static function findOrCreateForUser(int $userId, array $extraData = []): self
    {
        // ۱. اول دنبال investor فعال بگرد
        $investor = self::query()
            ->where('user_id', $userId)
            ->where('trash', false)
            ->first();

        if ($investor) {
            return $investor;
        }

        // ۲. دنبال investor در trash بگرد (با ignoring SoftDeletes)
        $trashedInvestor = self::withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)
            ->where('user_id', $userId)
            ->where('trash', true)
            ->first();

        if ($trashedInvestor) {
            // Restore و آپدیت اطلاعات
            $trashedInvestor->update([
                'trash'      => false,
                'deleted_at' => null,
            ]);

            return $trashedInvestor->fresh();
        }

        // ۳. اگر هیچ‌کدام نبود، یکی بساز
        $user = \App\Models\User::find($userId);

        return self::create(array_merge([
            'user_id'    => $userId,
            'full_name'  => $user?->name ?? 'Unknown',
            'phone'      => $user?->phone,
            'email'      => $user?->email,
            'status'     => 'active',
            'trash'      => false,
            'created_by' => auth()->id(),
        ], $extraData));
    }

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

    /** Purchase rows (products with variant_id) financed via investor_id. */
    public function purchaseRows(): HasMany
    {
        return $this->hasMany(Product::class, 'investor_id')->whereNotNull('variant_id');
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
        return $query->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)
                     ->where('trash', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /* =========================================================
     |  Wallet Helpers
     ========================================================= */

    public function getOrCreateWallet(): InvestorWallet
    {
        return $this->wallet()->firstOrCreate(
            ['investor_id' => $this->id],
            [
                'capital_balance_afn'      => 0,
                'profit_balance_afn'       => 0,
                'total_invested_afn'       => 0,
                'total_profit_earned_afn'  => 0,
                'total_withdrawn_afn'      => 0,
            ]
        );
    }

    public function getTotalBalanceAfnAttribute(): float
    {
        if (! $this->wallet) {
            return 0;
        }
        return (float) $this->wallet->capital_balance_afn + (float) $this->wallet->profit_balance_afn;
    }

    /* =========================================================
     |  ✅ Investment Type Helpers (Cash vs Goods)
     ========================================================= */

    public function hasCashCapital(): bool
    {
        $wallet = $this->wallet ?? $this->getOrCreateWallet();

        return (float) $wallet->capital_balance_afn > 0.01;
    }

    public function isGoodsInvestor(): bool
    {
        if ($this->hasCashCapital()) {
            return false;
        }

        return $this->batches()
            ->where('remaining_quantity', '>', 0)
            ->exists();
    }

    public function getActiveGoodsValueAfnAttribute(): float
    {
        return (float) $this->batches()
            ->where('remaining_quantity', '>', 0)
            ->sum(DB::raw('remaining_quantity * unit_cost_afn'));
    }

    public function getActiveGoodsCountAttribute(): int
    {
        return (int) $this->batches()
            ->where('remaining_quantity', '>', 0)
            ->sum('remaining_quantity');
    }

    public function getCashInvestmentsAttribute()
    {
        return $this->investments()
            ->whereIn('status', ['active', 'partially_used'])
            ->where('available_amount_afn', '>', 0)
            ->get();
    }

    public function getAvailableCashAfnAttribute(): float
    {
        return (float) $this->investments()
            ->whereIn('status', ['active', 'partially_used'])
            ->sum('available_amount_afn');
    }
}