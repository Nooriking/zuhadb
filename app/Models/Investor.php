<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Investor identity is a User with role=investor.
 *
 * Financial tables retain the column name `investor_id`, which now references
 * `users.id` (this model's primary key).
 *
 * Compatibility:
 * - `full_name` maps to users.name
 * - `user_id` accessor returns this user's id (self) for legacy policy checks
 */
class Investor extends User
{
    protected $table = 'users';

    /**
     * Extra aliases accepted when creating/updating via legacy investor forms/tests.
     *
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'name',
        'full_name',
        'username',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'trash',
        'user_id',
    ];

    protected $appends = [
        'two_factor_enabled',
        'full_name',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('role_investor', function (Builder $builder) {
            $builder->where($builder->getModel()->getTable().'.role', User::ROLE_INVESTOR);
        });

        static::creating(function (Investor $model) {
            $model->role = User::ROLE_INVESTOR;

            if (empty($model->password)) {
                $model->password = Str::random(40);
            }

            if ($model->status === null || $model->status === '') {
                $model->status = 'active';
            }

            if ($model->trash === null) {
                $model->trash = false;
            }
        });
    }

    /* =========================================================
     |  Compatibility accessors
     ========================================================= */

    public function getFullNameAttribute(): string
    {
        return (string) ($this->attributes['name'] ?? '');
    }

    public function setFullNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value;
    }

    /* =========================================================
     |  Relationships (investor_id → users.id)
     ========================================================= */

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

    /**
     * @deprecated Investor identity is the user. Kept for transitional callsites.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'id');
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /* =========================================================
     |  Helpers
     ========================================================= */

    /**
     * Resolve the investor identity for a user id (must already have investor role).
     */
    public static function forUserId(int $userId): ?self
    {
        return static::query()->whereKey($userId)->first();
    }

    /**
     * @deprecated Use the user with role=investor directly. Kept for transitional callers.
     */
    public static function findOrCreateForUser(int $userId, array $extraData = []): self
    {
        $investor = static::query()->whereKey($userId)->first();
        if ($investor) {
            return $investor;
        }

        $user = User::query()->findOrFail($userId);

        if ($user->role !== User::ROLE_INVESTOR) {
            $user->update(['role' => User::ROLE_INVESTOR]);
        }

        return static::query()->whereKey($userId)->firstOrFail();
    }

    public function getOrCreateWallet(): InvestorWallet
    {
        return $this->wallet()->firstOrCreate(
            ['investor_id' => $this->id],
            [
                'capital_balance_afn' => 0,
                'profit_balance_afn' => 0,
                'total_invested_afn' => 0,
                'total_profit_earned_afn' => 0,
                'total_withdrawn_afn' => 0,
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
