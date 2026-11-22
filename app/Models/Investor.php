<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Investor identity is a User with role=investor.
 *
 * Financial tables retain the column name `investor_id`, which references users.id.
 */
class Investor extends User
{
    protected $table = 'users';

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

    public function walletBalances(): HasMany
    {
        return $this->hasMany(InvestorWalletBalance::class, 'investor_id');
    }

    /** @deprecated Use walletBalances() */
    public function investorWalletBalances(): HasMany
    {
        return $this->walletBalances();
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class, 'investor_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InvestorTransaction::class, 'investor_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(InvestorWithdrawal::class, 'investor_id');
    }

    public function batches(): HasManyThrough
    {
        return $this->hasManyThrough(
            ProductBatch::class,
            Investment::class,
            'investor_id',
            'investment_id',
            'id',
            'id'
        );
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'id');
    }

    /* =========================================================
     |  Helpers
     ========================================================= */

    public static function forUserId(int $userId): ?self
    {
        return static::query()->whereKey($userId)->first();
    }

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

    public function balanceFor(string $currencyCode = 'AFN'): InvestorWalletBalance
    {
        return InvestorWalletBalance::forInvestor($this->id, $currencyCode);
    }

    /** @deprecated Prefer balanceFor(); kept for transitional controllers. */
    public function getOrCreateWallet(): InvestorWallet
    {
        return InvestorWallet::forInvestor($this->id);
    }

    public function wallet(): InvestorWallet
    {
        return $this->getOrCreateWallet();
    }

    public function getTotalBalanceAfnAttribute(): float
    {
        return (float) $this->walletBalances()->sum(DB::raw('capital_balance_afn + profit_balance_afn'));
    }

    public function hasCashCapital(): bool
    {
        return (float) $this->walletBalances()->sum('capital_balance_afn') > 0.01;
    }

    public function isGoodsInvestor(): bool
    {
        if ($this->hasCashCapital()) {
            return false;
        }

        return $this->batches()
            ->where('product_batches.remaining_quantity', '>', 0)
            ->exists();
    }

    public function getActiveGoodsValueAfnAttribute(): float
    {
        return (float) $this->batches()
            ->where('product_batches.remaining_quantity', '>', 0)
            ->sum(DB::raw('product_batches.remaining_quantity * product_batches.unit_cost_afn'));
    }

    public function getActiveGoodsCountAttribute(): int
    {
        return (int) $this->batches()
            ->where('product_batches.remaining_quantity', '>', 0)
            ->sum('product_batches.remaining_quantity');
    }

    public function getCashInvestmentsAttribute()
    {
        return $this->investments()
            ->where('status', 'active')
            ->get();
    }

    public function getAvailableCashAfnAttribute(): float
    {
        return (float) $this->walletBalances()->sum('capital_balance_afn');
    }
}
