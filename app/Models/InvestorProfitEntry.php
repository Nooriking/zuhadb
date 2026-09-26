<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvestorProfitEntry extends Model
{
    use HasFactory;

    protected $table = 'investor_profit_entries';

    protected $fillable = [
        'investor_id',
        'investment_id',
        'investment_product_allocation_id',
        'sale_id',
        'sale_item_id',
        'quantity',
        'profit_per_unit_afn',
        'profit_amount_afn',
        'entry_type',
        'status',
        'earned_at',
        'paid_at',
        'paid_by',
        'notes',
    ];

    protected $casts = [
        'quantity'            => 'integer',
        'profit_per_unit_afn' => 'decimal:2',
        'profit_amount_afn'   => 'decimal:2',
        'earned_at'                => 'datetime',
        'paid_at'                  => 'datetime',
        'created_at'               => 'datetime',
        'updated_at'               => 'datetime',
    ];

    /* =========================================================
     |  Labels
     ========================================================= */

    public const STATUS_LABELS = [
        'pending'   => 'Pending',
        'paid'      => 'Paid',
        'cancelled' => 'Cancelled',
    ];

    public const ENTRY_TYPE_LABELS = [
        'profit'     => 'Profit',
        'loss'       => 'Loss',
        'adjustment' => 'Adjustment',
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

    public function productAllocation(): BelongsTo
    {
        return $this->belongsTo(InvestmentProductAllocation::class, 'investment_product_allocation_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /* =========================================================
     |  Scopes
     ========================================================= */

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeForInvestor($query, int $investorId)
    {
        return $query->where('investor_id', $investorId);
    }

    public function scopeForInvestment($query, int $investmentId)
    {
        return $query->where('investment_id', $investmentId);
    }

    /* =========================================================
     |  Accessors
     ========================================================= */

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getEntryTypeLabelAttribute(): string
    {
        return self::ENTRY_TYPE_LABELS[$this->entry_type] ?? $this->entry_type;
    }

    /* =========================================================
     |  Static Helpers
     ========================================================= */

    /**
     * ثبت سود یک سرمایه‌گذار برای یک فروش.
     *
     * @param  int         $investorId
     * @param  int|null    $investmentId
     * @param  int|null    $productAllocationId
     * @param  int         $saleId
     * @param  int         $saleItemId
     * @param  int         $quantity
     * @param  float       $profitPerUnitAfn
     * @param  float|null  $profitPerUnitCurrency
     * @param  int|null    $currencyId
     * @param  float|null  $exchangeRate
     * @param  string|null $notes
     */
    public static function recordProfit(
        int $investorId,
        ?int $investmentId,
        ?int $productAllocationId,
        int $saleId,
        int $saleItemId,
        int $quantity,
        float $profitPerUnitAfn,
        ?float $profitPerUnitCurrency = null,
        ?int $currencyId = null,
        ?float $exchangeRate = null,
        ?string $notes = null
    ): self {
        return DB::transaction(function () use (
            $investorId,
            $investmentId,
            $productAllocationId,
            $saleId,
            $saleItemId,
            $quantity,
            $profitPerUnitAfn,
            $profitPerUnitCurrency,
            $currencyId,
            $exchangeRate,
            $notes
        ) {
            $investment = $investmentId ? Investment::find($investmentId) : null;

            if (! $investment) {
                $investment = Investment::query()
                    ->where('investor_id', $investorId)
                    ->active()
                    ->where('trash', false)
                    ->orderByDesc('id')
                    ->first();
                $investmentId = $investment?->id;
            }

            $profitAmountAfn = $profitPerUnitAfn * $quantity;

            $entry = self::create([
                'investor_id'                      => $investorId,
                'investment_id'                    => $investmentId,
                'investment_product_allocation_id' => $productAllocationId,
                'sale_id'                          => $saleId,
                'sale_item_id'                     => $saleItemId,
                'quantity'                         => $quantity,
                'profit_per_unit_afn'              => $profitPerUnitAfn,
                'profit_amount_afn'                => $profitAmountAfn,
                'entry_type'                       => 'profit',
                'status'                           => 'pending',
                'earned_at'                        => now(),
                'notes'                            => $notes,
            ]);

            if ($investment) {
                $investment->update([
                    'profit_amount_afn' => (float) $investment->profit_amount_afn + $profitAmountAfn,
                ]);
            }

            $investor = Investor::find($investorId);
            if ($investor) {
                $wallet = $investor->getOrCreateWallet();
                $wallet->update([
                    'profit_balance_afn'      => (float) $wallet->profit_balance_afn + $profitAmountAfn,
                    'total_profit_earned_afn' => (float) $wallet->total_profit_earned_afn + $profitAmountAfn,
                    'last_transaction_at'     => now(),
                ]);
            }

            InvestorTransaction::record(
                investorId:  $investorId,
                type:        'profit_earned',
                amountAfn:   $profitAmountAfn,
                balanceType: 'profit',
                options:     [
                    'investment_id'  => $investmentId,
                    'reference_type' => Sale::class,
                    'reference_id'   => $saleId,
                    'description'    => "Profit from sale #{$saleId} (entry #{$entry->id})",
                ]
            );

            return $entry;
        });
    }

    /**
     * علامت‌گذاری یک entry به‌عنوان پرداخت‌شده.
     */
    public function markAsPaid(?string $notes = null): void
    {
        DB::transaction(function () use ($notes) {
            $this->update([
                'status'  => 'paid',
                'paid_at' => now(),
                'paid_by' => Auth::id(),
                'notes'   => $notes ? ($this->notes . "\n" . $notes) : $this->notes,
            ]);
        });
    }

    /**
     * لغو یک entry.
     */
    public function cancel(?string $reason = null): void
    {
        if ($this->status === 'paid') {
            throw new \Exception('Cannot cancel a paid entry.');
        }

        DB::transaction(function () use ($reason) {
            /* ═══ برگرداندن مبلغ از wallet ═══ */
            $wallet = $this->investor?->wallet;

            if ($wallet) {
                $wallet->update([
                    'profit_balance_afn'      => max(0, (float) $wallet->profit_balance_afn - (float) $this->profit_amount_afn),
                    'total_profit_earned_afn' => max(0, (float) $wallet->total_profit_earned_afn - (float) $this->profit_amount_afn),
                    'last_transaction_at'     => now(),
                ]);
            }

            /* ═══ برگرداندن مبلغ از investment ═══ */
            if ($this->investment) {
                $this->investment->update([
                    'profit_amount_afn' => max(0, (float) $this->investment->profit_amount_afn - (float) $this->profit_amount_afn),
                ]);
            }

            $this->update([
                'status' => 'cancelled',
                'notes'  => $reason ? ($this->notes . "\nCancelled: " . $reason) : $this->notes,
            ]);
        });
    }
}