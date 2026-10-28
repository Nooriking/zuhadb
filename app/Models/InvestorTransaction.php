<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InvestorTransaction extends Model
{
    use HasFactory;

    protected $table = 'investor_transactions';

    protected $fillable = [
        'investor_id',
        'investment_id',
        'currency_id',
        'currency_code',
        'currency_symbol',
        'exchange_rate',
        'type',
        'amount_currency',
        'amount_afn',
        'balance_type',
        'balance_before_afn',
        'balance_after_afn',
        'reference_type',
        'reference_id',
        'description',
        'transaction_date',
        'created_by',
    ];

    protected $appends = ['type_label'];

    protected $casts = [
        'exchange_rate'       => 'decimal:8',
        'amount_currency'     => 'decimal:2',
        'amount_afn'          => 'decimal:2',
        'balance_before_afn'  => 'decimal:2',
        'balance_after_afn'   => 'decimal:2',
        'transaction_date'    => 'date',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    /* =========================================================
     |  Type Labels
     ========================================================= */

    public const TYPE_LABELS = [
        'capital_deposit'   => 'Capital Deposit',
        'capital_withdrawn' => 'Capital Withdrawn',
        'profit_earned'     => 'Profit Earned',
        'profit_withdrawn'  => 'Profit Withdrawn',
        'reinvestment'      => 'Reinvestment',
        'adjustment'        => 'Adjustment',
        'loss'              => 'Loss',
    ];

    public const TYPE_COLORS = [
        'capital_deposit'   => 'green',
        'capital_withdrawn' => 'red',
        'profit_earned'     => 'blue',
        'profit_withdrawn'  => 'orange',
        'reinvestment'      => 'purple',
        'adjustment'        => 'gray',
        'loss'              => 'red',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class, 'investor_id');
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class, 'investment_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference');
    }

    /* =========================================================
     |  Scopes
     ========================================================= */

    public function scopeForInvestor($query, int $investorId)
    {
        return $query->where('investor_id', $investorId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeDeposits($query)
    {
        return $query->whereIn('type', ['capital_deposit', 'profit_earned', 'reinvestment']);
    }

    public function scopeWithdrawals($query)
    {
        return $query->whereIn('type', ['capital_withdrawn', 'profit_withdrawn']);
    }

    /* =========================================================
     |  Accessors
     ========================================================= */

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function getIsIncomingAttribute(): bool
    {
        return in_array($this->type, ['capital_deposit', 'profit_earned', 'reinvestment']);
    }

    public function getIsOutgoingAttribute(): bool
    {
        return in_array($this->type, ['capital_withdrawn', 'profit_withdrawn', 'loss']);
    }

    /* =========================================================
     |  ⭐ Static Helpers
     ========================================================= */

    /**
     * ثبت یک تراکنش سرمایهگذار
     *
     * @param  int    $investorId
     * @param  string $type            capital_deposit | profit_earned | ...
     * @param  float  $amountAfn
     * @param  string $balanceType     capital | profit
     * @param  array  $options         investment_id, currency_id, exchange_rate, reference_type, reference_id, description, amount_currency, transaction_date
     */
    public static function record(
        int $investorId,
        string $type,
        float $amountAfn,
        string $balanceType = 'profit',
        array $options = []
    ): self {
        $investor = Investor::find($investorId);

        $balanceBeforeAfn = 0;
        $balanceAfterAfn  = $amountAfn;

        if ($investor) {
            $wallet = $investor->getOrCreateWallet();

            $balanceBeforeAfn = match ($balanceType) {
                'capital' => (float) $wallet->capital_balance_afn,
                'profit'  => (float) $wallet->profit_balance_afn,
                default   => (float) ($wallet->balance_afn ?? 0),
            };

            $isIncoming = in_array($type, [
                'capital_deposit',
                'profit_earned',
                'reinvestment',
            ], true);

            $balanceAfterAfn = $isIncoming
                ? $balanceBeforeAfn + $amountAfn
                : max(0, $balanceBeforeAfn - $amountAfn);
        }

        return self::create([
            'investor_id'        => $investorId,
            'investment_id'      => $options['investment_id']    ?? null,
            'currency_id'        => $options['currency_id']      ?? null,
            'currency_code'      => $options['currency_code']    ?? null,
            'currency_symbol'    => $options['currency_symbol']  ?? null,
            'exchange_rate'      => $options['exchange_rate']    ?? null,
            'type'               => $type,
            'amount_currency'    => $options['amount_currency']  ?? $amountAfn,
            'amount_afn'         => $amountAfn,
            'balance_type'       => $balanceType,
            'balance_before_afn' => $balanceBeforeAfn,
            'balance_after_afn'  => $balanceAfterAfn,
            'reference_type'     => $options['reference_type']   ?? null,
            'reference_id'       => $options['reference_id']     ?? null,
            'description'        => $options['description']      ?? null,
            'transaction_date'   => $options['transaction_date'] ?? now()->toDateString(),
            'created_by'         => auth()->id(),
        ]);
    }
}