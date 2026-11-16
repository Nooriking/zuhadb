<?php

namespace App\Models;

use App\Support\Money;
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
        'currency_id',
        'currency_code',
        'currency_symbol',
        'exchange_rate',
        'quantity',
        'profit_per_unit_currency',
        'profit_per_unit_afn',
        'profit_amount_currency',
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
        'exchange_rate'            => 'decimal:8',
        'profit_per_unit_currency' => 'decimal:2',
        'profit_per_unit_afn'      => 'decimal:2',
        'profit_amount_currency'   => 'decimal:2',
        'profit_amount_afn'        => 'decimal:2',
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

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
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

            $existing = self::query()
                ->where('sale_item_id', $saleItemId)
                ->where('investment_product_allocation_id', $productAllocationId)
                ->where('entry_type', 'profit')
                ->where('status', '!=', 'cancelled')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $profitAmountAfn = Money::round(
                Money::mul((string) $profitPerUnitAfn, (string) $quantity, 8),
                2
            );
            $ledger = Currency::ledger();
            // Prefer the batch-configured investor profit currency — independent of purchase currency.
            $profitCurrency = $currencyId
                ? Currency::query()->notTrashed()->find($currencyId)
                : null;
            $snapCurrencyId = $profitCurrency?->id ?? $investment?->currency_id ?? $ledger?->id;
            $snapCode = $profitCurrency?->code ?? $investment?->currency_code ?? $ledger?->code;
            $snapSymbol = $profitCurrency?->symbol ?? $investment?->currency_symbol ?? $ledger?->symbol;
            if ($exchangeRate !== null && (float) $exchangeRate > 0) {
                $snapRate = $exchangeRate;
            } elseif ($profitCurrency && $ledger && $profitCurrency->id === $ledger->id) {
                $snapRate = 1.0;
            } else {
                $snapRate = $investment?->exchange_rate ?? ($ledger ? 1.0 : null);
            }
            $profitPerUnitOriginal = $profitPerUnitCurrency ?? $profitPerUnitAfn;
            $profitAmountOriginal = Money::round(
                Money::mul((string) $profitPerUnitOriginal, (string) $quantity, 8),
                2
            );

            $entry = self::create([
                'investor_id'                      => $investorId,
                'investment_id'                    => $investmentId,
                'investment_product_allocation_id' => $productAllocationId,
                'sale_id'                          => $saleId,
                'sale_item_id'                     => $saleItemId,
                'currency_id'                      => $snapCurrencyId,
                'currency_code'                    => $snapCode,
                'currency_symbol'                  => $snapSymbol,
                'exchange_rate'                    => $snapRate,
                'quantity'                         => $quantity,
                'profit_per_unit_currency'         => $profitPerUnitOriginal,
                'profit_per_unit_afn'              => $profitPerUnitAfn,
                'profit_amount_currency'           => $profitAmountOriginal,
                'profit_amount_afn'                => $profitAmountAfn,
                'entry_type'                       => 'profit',
                'status'                           => 'pending',
                'earned_at'                        => now(),
                'notes'                            => $notes,
            ]);

            if ($investment) {
                $investment->update([
                    'profit_amount_afn' => Money::round(
                        Money::add((string) $investment->profit_amount_afn, (string) $profitAmountAfn, 8),
                        2
                    ),
                ]);
            }

            $investor = Investor::find($investorId);
            if ($investor) {
                $wallet = $investor->getOrCreateWallet();
                $wallet->update([
                    'profit_balance_afn'      => Money::round(
                        Money::add((string) $wallet->profit_balance_afn, (string) $profitAmountAfn, 8),
                        2
                    ),
                    'total_profit_earned_afn' => Money::round(
                        Money::add((string) $wallet->total_profit_earned_afn, (string) $profitAmountAfn, 8),
                        2
                    ),
                    'last_transaction_at'     => now(),
                ]);
            }

            InvestorTransaction::record(
                investorId:  $investorId,
                type:        'profit_earned',
                amountAfn:   $profitAmountAfn,
                balanceType: 'profit',
                options:     [
                    'investment_id'    => $investmentId,
                    'currency_id'      => $snapCurrencyId,
                    'currency_code'    => $snapCode,
                    'currency_symbol'  => $snapSymbol,
                    'exchange_rate'    => $snapRate,
                    'amount_currency'  => $profitAmountOriginal,
                    'reference_type'   => Sale::class,
                    'reference_id'     => $saleId,
                    'description'      => "Profit from sale #{$saleId} (entry #{$entry->id})",
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
                    'profit_balance_afn'      => Money::round(
                        Money::maxZero(Money::sub((string) $wallet->profit_balance_afn, (string) $this->profit_amount_afn, 8)),
                        2
                    ),
                    'total_profit_earned_afn' => Money::round(
                        Money::maxZero(Money::sub((string) $wallet->total_profit_earned_afn, (string) $this->profit_amount_afn, 8)),
                        2
                    ),
                    'last_transaction_at'     => now(),
                ]);
            }

            /* ═══ برگرداندن مبلغ از investment ═══ */
            if ($this->investment) {
                $this->investment->update([
                    'profit_amount_afn' => Money::round(
                        Money::maxZero(Money::sub((string) $this->investment->profit_amount_afn, (string) $this->profit_amount_afn, 8)),
                        2
                    ),
                ]);
            }

            $this->update([
                'status' => 'cancelled',
                'notes'  => $reason ? ($this->notes . "\nCancelled: " . $reason) : $this->notes,
            ]);
        });
    }

    /**
     * Immutable reversal for a returned quantity. Never deletes the original entry.
     * Works for pending (partial mutate + adjustment) and paid (adjustment only).
     */
    public function recordReturnReversal(int $quantity, string $reason = 'Reversed by sale return'): self
    {
        $quantity = max(0, $quantity);
        if ($quantity <= 0) {
            return $this;
        }

        return DB::transaction(function () use ($quantity, $reason) {
            $perUnit = (string) $this->profit_per_unit_afn;
            $reverseAmount = Money::round(Money::mul($perUnit, (string) $quantity, 8), 2);
            $entryQty = (int) $this->quantity;

            if ($this->status === 'pending') {
                if ($quantity >= $entryQty) {
                    $this->cancel($reason);

                    return $this->fresh() ?? $this;
                }

                $newQty = $entryQty - $quantity;
                // Reverse original-currency total using historical profit_per_unit_currency —
                // never subtract AFN ledger amount from the original-currency column.
                $perUnitCurrency = (string) ($this->profit_per_unit_currency ?? $perUnit);
                $reverseAmountCurrency = Money::round(
                    Money::mul($perUnitCurrency, (string) $quantity, 8),
                    2
                );
                $wallet = $this->investor?->getOrCreateWallet();
                if ($wallet) {
                    $wallet->update([
                        'profit_balance_afn' => Money::round(
                            Money::maxZero(Money::sub((string) $wallet->profit_balance_afn, $reverseAmount, 8)),
                            2
                        ),
                        'total_profit_earned_afn' => Money::round(
                            Money::maxZero(Money::sub((string) $wallet->total_profit_earned_afn, $reverseAmount, 8)),
                            2
                        ),
                        'last_transaction_at' => now(),
                    ]);
                }
                if ($this->investment) {
                    $this->investment->update([
                        'profit_amount_afn' => Money::round(
                            Money::maxZero(Money::sub((string) $this->investment->profit_amount_afn, $reverseAmount, 8)),
                            2
                        ),
                    ]);
                }
                $this->update([
                    'quantity' => $newQty,
                    'profit_amount_afn' => Money::round(
                        Money::sub((string) $this->profit_amount_afn, $reverseAmount, 8),
                        2
                    ),
                    'profit_amount_currency' => Money::round(
                        Money::sub(
                            (string) ($this->profit_amount_currency ?? $this->profit_amount_afn),
                            $reverseAmountCurrency,
                            8
                        ),
                        2
                    ),
                    'notes' => trim(($this->notes ?? '')."\nPartial reverse {$quantity} units on return"),
                ]);

                return $this->fresh() ?? $this;
            }

            $perUnitCurrency = (string) ($this->profit_per_unit_currency ?? $perUnit);

            // Paid (or other non-pending): create immutable adjustment; do not mutate original.
            $reversal = self::create([
                'investor_id' => $this->investor_id,
                'investment_id' => $this->investment_id,
                'investment_product_allocation_id' => $this->investment_product_allocation_id,
                'sale_id' => $this->sale_id,
                'sale_item_id' => $this->sale_item_id,
                'currency_id' => $this->currency_id,
                'currency_code' => $this->currency_code,
                'currency_symbol' => $this->currency_symbol,
                'exchange_rate' => $this->exchange_rate,
                'quantity' => $quantity,
                'profit_per_unit_currency' => Money::mul($perUnitCurrency, '-1', 8),
                'profit_per_unit_afn' => Money::mul($perUnit, '-1', 8),
                'profit_amount_currency' => Money::mul(
                    Money::round(Money::mul($perUnitCurrency, (string) $quantity, 8), 2),
                    '-1',
                    2
                ),
                'profit_amount_afn' => Money::mul($reverseAmount, '-1', 2),
                'entry_type' => 'adjustment',
                'status' => 'paid',
                'earned_at' => now(),
                'paid_at' => now(),
                'notes' => $reason.' (reversal of entry #'.$this->id.')',
            ]);

            if ($this->investment) {
                $this->investment->update([
                    'profit_amount_afn' => Money::round(
                        Money::maxZero(Money::sub((string) $this->investment->profit_amount_afn, $reverseAmount, 8)),
                        2
                    ),
                ]);
            }

            $investor = Investor::find($this->investor_id);
            if ($investor) {
                $wallet = $investor->getOrCreateWallet();
                $wallet->update([
                    'profit_balance_afn' => Money::round(
                        Money::maxZero(Money::sub((string) $wallet->profit_balance_afn, $reverseAmount, 8)),
                        2
                    ),
                    'total_profit_earned_afn' => Money::round(
                        Money::maxZero(Money::sub((string) $wallet->total_profit_earned_afn, $reverseAmount, 8)),
                        2
                    ),
                    'last_transaction_at' => now(),
                ]);
            }

            InvestorTransaction::record(
                investorId: (int) $this->investor_id,
                type: 'adjustment',
                amountAfn: $reverseAmount,
                balanceType: 'profit',
                options: [
                    'investment_id' => $this->investment_id,
                    'currency_id' => $this->currency_id,
                    'currency_code' => $this->currency_code,
                    'currency_symbol' => $this->currency_symbol,
                    'exchange_rate' => $this->exchange_rate,
                    'amount_currency' => round((float) ($this->profit_per_unit_currency ?? $perUnit) * $quantity, 2),
                    'reference_type' => Sale::class,
                    'reference_id' => $this->sale_id,
                    'description' => $reason.' for profit entry #'.$this->id,
                ]
            );

            return $reversal;
        });
    }
}