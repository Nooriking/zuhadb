<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestorWallet extends Model
{
    use HasFactory;

    protected $table = 'investor_wallets';

    protected $fillable = [
        'investor_id',
        'currency_id',
        'capital_balance_currency',
        'capital_balance_afn',
        'profit_balance_currency',
        'profit_balance_afn',
        'total_invested_afn',
        'total_profit_earned_afn',
        'total_withdrawn_afn',
        'last_transaction_at',
    ];

    protected $casts = [
        'capital_balance_currency' => 'decimal:2',
        'capital_balance_afn'      => 'decimal:2',
        'profit_balance_currency'  => 'decimal:2',
        'profit_balance_afn'       => 'decimal:2',
        'total_invested_afn'       => 'decimal:2',
        'total_profit_earned_afn'  => 'decimal:2',
        'total_withdrawn_afn'      => 'decimal:2',
        'last_transaction_at'      => 'datetime',
        'created_at'               => 'datetime',
        'updated_at'               => 'datetime',
    ];

    /* Relationships */

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class, 'investor_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function getTotalBalanceAfnAttribute(): float
    {
        return (float) $this->capital_balance_afn + (float) $this->profit_balance_afn;
    }
}